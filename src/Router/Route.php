<?php
namespace Segura\AppCore\Router;

use Slim\App;

class Route {

    protected $name;
    protected $callback;
    protected $class;
    protected $function;
    protected $template = "callback";
    protected $routerPattern;
    protected $httpEndpoint;
    protected $httpMethod = "GET";
    protected $singular;
    protected $plural;
    protected $properties;
    protected $exampleEntity;

    public static function Factory()
    {
        return new Route();
    }

    /**
     * @return string
     */
    public function getTemplate(): string
    {
        return $this->template;
    }

    /**
     * @param string $template
     *
     * @return Route
     */
    public function setTemplate(string $template): Route
    {
        $this->template = $template;
        return $this;
    }

    public function getUniqueIdentifier()
    {
        return implode(
            "::",
            [
                $this->getHttpMethod(),
                $this->getRouterPattern()
            ]
        );
    }

    /**
     * @return mixed
     */
    public function getHttpMethod()
    {
        return $this->httpMethod;
    }

    /**
     * @param mixed $httpMethod
     *
     * @return Route
     */
    public function setHttpMethod($httpMethod)
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRouterPattern()
    {
        return $this->routerPattern;
    }

    /**
     * @param mixed $routerPattern
     *
     * @return Route
     */
    public function setRouterPattern($routerPattern)
    {
        $this->routerPattern = $routerPattern;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExampleEntity()
    {
        return $this->exampleEntity;
    }

    /**
     * @param mixed $exampleEntity
     *
     * @return Route
     */
    public function setExampleEntity($exampleEntity)
    {
        $this->exampleEntity = $exampleEntity;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     *
     * @return Route
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param mixed $class
     *
     * @return Route
     */
    public function setClass($class)
    {
        $this->class = $class;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getFunction()
    {
        return $this->function;
    }

    /**
     * @param mixed $function
     *
     * @return Route
     */
    public function setFunction($function)
    {
        $this->function = $function;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSingular()
    {
        return $this->singular;
    }

    /**
     * @param mixed $singular
     *
     * @return Route
     */
    public function setSingular($singular)
    {
        $this->singular = $singular;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPlural()
    {
        return $this->plural;
    }

    /**
     * @param mixed $plural
     *
     * @return Route
     */
    public function setPlural($plural)
    {
        $this->plural = $plural;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param mixed $properties
     *
     * @return Route
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * @param App $app
     *
     * @return \Slim\Interfaces\RouteInterface
     */
    public function populateRoute(App $app){
        return $app->map(
            [$this->getHttpMethod()],
            $this->getRouterPattern(),
            $this->getCallback()
        );
    }

    /**
     * @return mixed
     */
    public function getCallback()
    {
        return $this->callback;
    }

    /**
     * @param mixed $callback
     *
     * @return Route
     */
    public function setCallback($callback)
    {
        $this->callback = $callback;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getHttpEndpoint()
    {
        return $this->httpEndpoint;
    }

    /**
     * @param mixed $httpEndpoint
     *
     * @return Route
     */
    public function setHttpEndpoint($httpEndpoint)
    {
        $this->httpEndpoint = $httpEndpoint;
        return $this;
    }

}