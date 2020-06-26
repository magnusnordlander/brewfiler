<?php
declare(strict_types=1);

namespace App\Entity;


use App\Time\PythonDateParser;

class BrewDataPoint
{
    /**
     * @var \DateTimeInterface
     */
    private $timestamp;
    /**
     * @var float
     */
    private $relativeTime;
    /**
     * @var float
     */
    private $boilerTemperature;
    /**
     * @var float
     */
    private $groupTemperature;
    /**
     * @var float
     */
    private $weight;

    public static function createFromRecord(array $record, array $meta)
    {


        return new static(
            PythonDateParser::createDateTimeImmutable($record['Time']),
            $record['Time'] - $meta['start_time'],
            (float)($record['Boiler Temperature'] ?? $record['Temperature']),
            isset($record['Group Temperature']) ? (float)$record['Group Temperature'] : null,
            (float)$record['Weight']
        );
    }

    public function __construct(
        \DateTimeInterface $timestamp,
        float $relativeTime,
        float $boilerTemperature,
        ?float $groupTemperature,
        float $weight
    ) {
        $this->timestamp = $timestamp;
        $this->relativeTime = $relativeTime;
        $this->boilerTemperature = $boilerTemperature;
        $this->groupTemperature = $groupTemperature;
        $this->weight = $weight;
    }

    public function getTimestamp(): \DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getRelativeTime(): float
    {
        return $this->relativeTime;
    }

    public function getBoilerTemperature(): float
    {
        return $this->boilerTemperature;
    }

    public function getGroupTemperature(): ?float
    {
        return $this->groupTemperature;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }
}
