<?php
namespace Segura\AppCore\Abstracts;

abstract class Service
{
    abstract public function getNewModelInstance();

    abstract public function getTermPlural() : string;

    abstract public function getTermSingular() : string;
}
