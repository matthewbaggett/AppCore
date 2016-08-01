<?php
namespace Segura\AppCore\Abstracts;

abstract class Service
{

    abstract function getNewModelInstance();

    abstract function getTermPlural() : string;
    abstract function getTermSingular() : string;
}
