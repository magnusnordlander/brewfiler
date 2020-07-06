<?php
declare(strict_types=1);

namespace App\Entity;

use App\Time\PythonDateParser;
use League\Csv\Reader;

class Brew
{
    const DEFAULT_SIGMA = 0.1;

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

    public function getTotalBrewTime(): float
    {
        return $this->meta['stop_time'] - $this->meta['start_time'];
    }

    public function getDripTime(float $sigma = self::DEFAULT_SIGMA): ?float
    {
        $firstDrip = $this->getFirstDrip($sigma);
        $lastDrip = $this->getLastDrip($sigma);

        if ($firstDrip && $lastDrip) {
            return $lastDrip->getRelativeTime() - $firstDrip->getRelativeTime();
        }

        return null;
    }

    public function getDripFlow(float $sigma = self::DEFAULT_SIGMA): ?float
    {
        $firstDrip = $this->getFirstDrip($sigma);
        $lastDrip = $this->getLastDrip($sigma);

        if ($firstDrip && $lastDrip) {
            $time = $lastDrip->getRelativeTime() - $firstDrip->getRelativeTime();
            $weight = $lastDrip->getWeight() - $firstDrip->getWeight();

            if ($time) {
                return $weight/$time;
            }
        }

        return null;
    }

    public function getPostStopFlow(float $sigma = self::DEFAULT_SIGMA): ?float
    {
        $finalWeight = $this->getFinalWeight();

        if ($finalWeight) {
            return $finalWeight - $this->meta['stop_weight'];
        }

        return null;
    }

    public function getFinalWeight(float $sigma = self::DEFAULT_SIGMA, bool $tare = true): ?float
    {
        $tareWeight = 0;
        if ($tare) {
            $firstDrip = $this->getFirstDrip($sigma);
            if (!$firstDrip) {
                return null;
            }
            $tareWeight = $firstDrip->getWeight();
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

    private function getFirstDrip(float $sigma): ?BrewDataPoint
    {
        $prevPoint = null;
        foreach ($this->datapoints as $datapoint) {
            if ($prevPoint && $datapoint->getWeight() - $prevPoint->getWeight() > $sigma) {
                return $prevPoint;
            }

            $prevPoint = $datapoint;
        }

        return null;
    }

    private function getLastDrip(float $sigma): ?BrewDataPoint
    {
        $prevPoint = null;
        $secPrevPoint = null;
        $thdPrevPoint = null;

        $firstDripFound = false;

        foreach ($this->datapoints as $datapoint) {
            if ($prevPoint && $thdPrevPoint) {
                $diff = $datapoint->getWeight() - $prevPoint->getWeight();
                $thdDiff = $datapoint->getWeight() - $thdPrevPoint->getWeight();
                if ($diff > $sigma) {
                    $firstDripFound = true;
                }

                if ($firstDripFound && $thdDiff < $sigma) {
                    return $prevPoint;
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
}
