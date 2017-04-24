<?php
namespace Segura\AppCore\Twig\Extensions;

class FilterAlphanumericOnlyTwigExtension extends \Twig_Extension
{
    public function getName()
    {
        return 'Filter Alphanumeric Only Twig Extension';
    }
    
    public function getFilters()
    {
        $filters = [];
        $methods = ['filteralphaonly'];
        foreach ($methods as $method) {
            $filters[$method] = new \Twig_Filter($method, [$this, $method]);
        }
        return $filters;
    }
    
    public function filteralphaonly($string)
    {
        return preg_replace("/[^a-z0-9_]+/i", "", $string);
    }
}
