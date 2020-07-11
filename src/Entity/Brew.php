<?php
declare(strict_types=1);

namespace App\Entity;

use App\RingBuffer;
use App\Time\PythonDateParser;
use League\Csv\Reader;

class Brew
{
    const DEFAULT_START_SIGMA = 0.3;
    const DEFAULT_STOP_SIGMA = 0.2;

    /**
     * @var array
     */
    private $meta;

    /**
     * @var BrewDataPoint[]
     */
    private $datapoints;
    /**
     * @var string
     */
    private $id;

    public function __construct(string $id, string $metaFile, ?string $dataFile)
    {
        $this->metaFile = $metaFile;
        $this->meta = json_decode(file_get_contents($this->metaFile), true);

        if (!isset($this->meta['start_time'])) {
            throw new \Exception("Meta file missing start time");
        }

        if ($dataFile) {
            $data = Reader::createFromPath($dataFile);
            $data->setHeaderOffset(0);
            $this->datapoints = array_map(function(array $record): BrewDataPoint {
                return BrewDataPoint::createFromRecord($record, $this->meta);
            }, iterator_to_array($data->getRecords()));
        } else {
            $this->datapoints = [];
        }
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        $startTime = $this->getStartTime()->format('Y-m-d H:i');

        $name = sprintf("%s (%s sec)", $startTime, round($this->getTotalBrewTime()));

        if ($this->getCoffee()) {
            $name .= ": ".$this->getCoffee();
        }

        return $name;
    }

    public function updateMeta(): void
    {
        file_put_contents($this->metaFile, json_encode($this->meta));
    }

    public function getCoffee(): ?string
    {
        return $this->meta['coffee'] ?? null;
    }

    public function setCoffee(?string $coffee)
    {
        $this->meta['coffee'] = $coffee;
    }

    public function getGrindSize(): ?int
    {
        return $this->meta['grind_size'] ?? null;
    }

    public function setGrindSize(?int $grindSize)
    {
        $this->meta['grind_size'] = $grindSize;
    }

    public function getDose(): ?float
    {
        return $this->meta['dose'] ?? null;
    }

    public function setDose(?float $dose)
    {
        $this->meta['dose'] = $dose;
    }

    public function getTastingNotes(): ?string
    {
        return $this->meta['tasting_notes'] ?? null;
    }

    public function setTastingNotes(?string $tastingNotes)
    {
        $this->meta['tasting_notes'] = $tastingNotes;
    }

    public function setRating(?int $rating)
    {
        $this->meta['rating'] = $rating;
    }

    public function getRating(): ?int
    {
        return $this->meta['rating'] ?? null;
    }

    public function setBasket(?string $basket)
    {
        $this->meta['basket'] = $basket;
    }

    public function getBasket(): ?string
    {
        return $this->meta['basket'] ?? null;
    }

    public function getTotalBrewTime(): float
    {
        return $this->getRelativeStopTime();
    }

    public function getDripTime(): ?float
    {
        $firstDrip = $this->getFirstDrip(self::DEFAULT_START_SIGMA);
        $lastDrip = $this->getLastDrip(self::DEFAULT_STOP_SIGMA);

        if ($firstDrip && $lastDrip) {
            return $lastDrip->getRelativeTime() - $firstDrip->getRelativeTime();
        }

        return null;
    }

    public function getDripFlow(): ?float
    {
        $firstDrip = $this->getFirstDrip(self::DEFAULT_START_SIGMA);
        $lastDrip = $this->getLastDrip(self::DEFAULT_STOP_SIGMA);

        if ($firstDrip && $lastDrip) {
            $time = $lastDrip->getRelativeTime() - $firstDrip->getRelativeTime();
            $weight = $lastDrip->getWeight() - $firstDrip->getWeight();

            if ($time) {
                return $weight/$time;
            }
        }

        return null;
    }

    public function getPostStopFlow(float $sigma = self::DEFAULT_STOP_SIGMA): ?float
    {
        $finalWeight = $this->getFinalWeight();

        if ($finalWeight) {
            return $finalWeight - $this->meta['stop_weight'];
        }

        return null;
    }

    public function getPostStopTime(): ?float
    {
        $lastDrip = $this->getLastDrip();

        if (!$lastDrip) {
            return null;
        }

        return $lastDrip->getRelativeTime() - $this->getRelativeStopTime();
    }

    private function getBaselineWeight(): float
    {
        $firstFive = array_slice($this->datapoints, 0, 5);

        if (count($firstFive) === 0) {
            return 0;
        }

        return array_sum(array_map(function(BrewDataPoint $point) { return $point->getWeight(); }, $firstFive)) / count($firstFive);
    }

    public function getTareWeight(float $sigma = self::DEFAULT_START_SIGMA): float
    {
        return $this->getBaselineWeight();
    }

    public function getFinalWeight(float $sigma = self::DEFAULT_STOP_SIGMA, bool $tare = true): ?float
    {
        $tareWeight = 0;
        if ($tare) {
            $tareWeight = $this->getTareWeight(self::DEFAULT_START_SIGMA);
        }

        $lastDrip = $this->getLastDrip($sigma);

        if ($lastDrip) {
            return $lastDrip->getWeight() - $tareWeight;
        }

        return null;
    }

    public function getTargetRatioDenominator(): ?float
    {
        if ($this->getDose()) {
            return $this->meta['target_weight'] / $this->getDose();
        }

        return null;
    }

    public function getFinalRatioDenominator(): ?float
    {
        $finalWeight = $this->getFinalWeight();

        if ($finalWeight && $this->getDose()) {
            return $finalWeight / $this->getDose();
        }

        return null;
    }

    public function firstDripTime(): ?\DateTimeInterface
    {
        $firstDrip = $this->getFirstDrip(self::DEFAULT_START_SIGMA);

        if ($firstDrip) {
            return $firstDrip->getTimestamp();
        }

        return null;
    }

    private function getFirstDrip(float $sigma): ?BrewDataPoint
    {
        $buffer = new RingBuffer(7);
        $baseline = $this->getBaselineWeight();
        foreach ($this->datapoints as $datapoint) {
            $buffer->add($datapoint);

            if ($buffer->avg(function(BrewDataPoint $point) { return $point->getWeight(); }) > $baseline + $sigma) {
                $lastNotMatching = $buffer->lastNotMatching(function(BrewDataPoint $point) use ($baseline) { return $point->getWeight() > $baseline; });

                return $lastNotMatching ?? $buffer->first();
            }
        }

        return null;
    }

    public function lastDripTime(): ?\DateTimeInterface
    {
        $firstDrip = $this->getLastDrip(self::DEFAULT_STOP_SIGMA);

        if ($firstDrip) {
            return $firstDrip->getTimestamp();
        }

        return null;
    }

    private function getLastDrip(float $sigma = self::DEFAULT_STOP_SIGMA): ?BrewDataPoint
    {

        $prevPoint = null;
        $secPrevPoint = null;
        $thdPrevPoint = null;

        $firstDripFound = false;

        $rollingAvg = 0;

        foreach ($this->datapoints as $datapoint) {
            if ($prevPoint instanceof BrewDataPoint && $secPrevPoint instanceof BrewDataPoint && $thdPrevPoint instanceof BrewDataPoint) {
                $rollingAvg = ($datapoint->getWeight() + $prevPoint->getWeight() + $secPrevPoint->getWeight())/3;
                $diff = $rollingAvg - $thdPrevPoint->getWeight();

                if ($diff > self::DEFAULT_START_SIGMA) {
                    $firstDripFound = true;
                }

                if ($firstDripFound && $diff < self::DEFAULT_STOP_SIGMA) {
                    return $thdPrevPoint;
                }
            }

            $thdPrevPoint = $secPrevPoint;
            $secPrevPoint = $prevPoint;
            $prevPoint = $datapoint;
        }

        return null;
    }

    public function getStartTime(): \DateTimeInterface
    {
        return PythonDateParser::createDateTimeImmutable($this->meta['start_time']);
    }

    public function getStopTime(): \DateTimeInterface
    {
        return PythonDateParser::createDateTimeImmutable($this->meta['stop_time']);
    }

    private function getRelativeStopTime(): float
    {
        return $this->meta['stop_time'] - $this->meta['start_time'];
    }

    public function getPreinfusionUsed(): bool
    {
        return $this->meta['preinfusion'];
    }

    public function getAbsolutePreinfusionTime(): ?\DateTimeInterface
    {
        if (!$this->getPreinfusionUsed()) {
            return null;
        }

        $startTime = $this->getStartTime();
        return $startTime->modify('+'.($this->meta['preinfusion_time']*1000).' ms');
    }

    public function getAbsoluteDwellTime(): ?\DateTimeInterface
    {
        if (!$this->getPreinfusionUsed()) {
            return null;
        }

        $preinfusionTime = $this->getAbsolutePreinfusionTime();
        if (!$preinfusionTime) {
            return null;
        }

        return $preinfusionTime->modify('+'.($this->meta['dwell_time']*1000).' ms');
    }

    /**
     * @return BrewDataPoint[]
     */
    public function getDatapoints(): array
    {
        return $this->datapoints;
    }

    public function hasGroupTemperature()
    {
        foreach ($this->getDatapoints() as $datapoint) {
            return $datapoint->getGroupTemperature() !== null;
        }

        return false;
    }

    public function getStopWeight(): float
    {
        return $this->meta['stop_weight'];
    }

    public function getTargetWeight(): float
    {
        return $this->meta['target_weight'];
    }
}
