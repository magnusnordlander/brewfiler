<?php
declare(strict_types=1);

namespace App;


class RingBuffer
{
    /**
     * @var int
     */
    private $size;

    /**
     * @var array
     */
    private $data;

    public function __construct(int $size)
    {
        $this->size = $size;
        $this->data = [];
    }

    public function add($data): void
    {
        $this->data[] = $data;

        if (count($this->data) > $this->size) {
            array_shift($this->data);
        }
    }

    public function addMultiple(array $data): void
    {
        $this->data = array_merge($this->data, array_values($data));

        if (count($this->data) > $this->size) {
            array_shift($this->data);
        }
    }

    public function first()
    {
        return $this->data[0];
    }

    public function firstMatching(callable $callback)
    {
        foreach($this->data as $datum) {
            if ($callback($datum)) {
                return $datum;
            }
        }

        return null;
    }

    public function lastNotMatching(callable $callback)
    {
        $prev = null;
        foreach($this->data as $datum) {
            if ($callback($datum)) {
                return $prev;
            }

            $prev = $datum;
        }

        return null;
    }

    public function isFull(): bool
    {
        return count($this->data) >= $this->size;
    }

    public function avg(?callable $callback = null): ?float
    {
        if (count($this->data) === 0) {
            return null;
        }

        $data = $this->data;
        if ($callback) {
            $data = array_map($callback, $data);
        }

        return array_sum($data) / count($data);
    }
}
