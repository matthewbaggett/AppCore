<?php

namespace Gone\AppCore\Filters;

class FilterCondition
{
    const CONDITION_EQUAL                 = '=';
    const CONDITION_NOT_EQUAL             = '!=';
    const CONDITION_GREATER_THAN          = '>';
    const CONDITION_LESS_THAN             = '<';
    const CONDITION_GREATER_THAN_OR_EQUAL = '>=';
    const CONDITION_LESS_THAN_OR_EQUAL    = '<=';
    const CONDITION_LIKE                  = 'LIKE';
    const CONDITION_NOT_LIKE              = 'NOT LIKE';
    const CONDITION_IN                    = 'IN';
    const CONDITION_NOT_IN                = 'NOT IN';
}
