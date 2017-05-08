<?php

namespace Segura\AppCore\Interfaces;

interface QueryStatisticInterface
{
    public function getCallPoints(): array;
    public function setCallPoints(array $callPoints);

    public function getSql(): string;

    public function setSql(string $sql);

    public function getTime(): float;

    public function setTime(float $time);
}
