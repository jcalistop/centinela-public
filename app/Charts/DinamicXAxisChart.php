<?php

namespace App\Charts;

use ArielMejiaDev\LarapexCharts\LarapexChart;

class DinamicXAxisChart
{
    public function __construct(protected LarapexChart $chart) {}

    public function build(string $chartType, string $data): LarapexChart
    {
        $chart = $this->chart->$chartType();
        $series = json_decode($data, true);
        foreach ($series as $key => $values) {
            $chart->addData(array_values($values), $key);
        }
        $chart->setXAxis(array_keys(reset($series)))
            ->setGrid();

        return $chart;
    }
}
