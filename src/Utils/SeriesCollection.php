<?php


namespace App\Utils;


class SeriesCollection
{
    /**
     * @var array
     */
    private array $series;

    public function __construct(array $series)
    {
        $this->series = $series;
    }

    /**
     * @param string $key
     * @param string $value
     * @return $this
     */
    public function where(string $key, string $value): static
    {
        $allSeries = [];

        foreach ($this->series as $series) {
            if ($series[$key] == $value) {
                $allSeries[] = $series;
            }
        }

        return new SeriesCollection($allSeries);
    }

    public function sum($key, $actual): int
    {
        $sum = 0;

        foreach ($this->series as $series) {
            if ($actual) {
                $sum += intval(count($series[str_replace('_count', '', $key) . 's']));
            } else {
                $sum += intval($series[$key]);
            }
        }

        return $sum;
    }

    public function count(): int
    {
        return (int) count($this->series);
    }

    public function get(): array
    {
        return $this->series;
    }

    public function exists(): bool
    {
        return ! empty($this->series);
    }

    public function first()
    {
        return $this->exists() ? $this->series[0] : null;
    }

    public function add($series): void
    {
        $this->series[$series['slug']] = $series;
    }
}
