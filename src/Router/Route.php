<?php
namespace Gone\AppCore\Router;

use Slim\App;

class Route
{
    const ACCESS_PUBLIC = 'public';
    const ACCESS_PRIVATE = 'private';

    protected $name;
    protected $callback;
    protected $SDKClass;
    protected $SDKFunction;
    protected $SDKTemplate = "callback";
    protected $routerPattern;
    protected $httpEndpoint;
    protected $httpMethod = "GET";
    protected $weight     = 0;
    protected $singular;
    protected $plural;
    protected $properties;
    protected $propertyData = [];
    protected $propertyOptions;
    protected $exampleEntity;
    protected $exampleEntityFinderFunction;
    protected $callbackProperties = [];
    protected $access = self::ACCESS_PUBLIC;

    public static function Factory() : Route
    {
        return new Route();
    }

    /**
     * @return array
     */
    public function getCallbackProperties(): array
    {
        return $this->callbackProperties;
    }

    /**
     * @param array $callbackProperties
     *
     * @return Route
     */
    public function setCallbackProperties(array $callbackProperties): Route
    {
        $this->callbackProperties = [];
        foreach ($callbackProperties as $name => $property){
            $this->populateCallbackProperty($name,$property);
        }
        return $this;
    }

    /**
     * @param $name
     * @param bool $mandatory
     * @param null $default
     *
     * @return $this
     */
    public function addCallbackProperty(string $name, bool $mandatory = false, $default = null)
    {
        return $this->populateCallbackProperty($name,[
            'isMandatory' => $mandatory,
            'default'     => $default,
        ]);
    }

    /**
     * @param string $name
     * @param array  $property
     */
    public function populateCallbackProperty(string $name,array $property){
        $property["name"] = $name;
        $this->callbackProperties[$name] = array_merge(
            [
                "in" => null,
                "description" => null,
                "isMandatory" => null,
                "default" => null,
                "type" => null,
                "examples" => [],
            ],
            $property
        );
        return $this;
    }

    /**
     * @return string
     */
    public function getSDKTemplate(): string
    {
        return $this->SDKTemplate;
    }

    /**
     * @param string $SDKTemplate
     *
     * @return Route
     */
    public function setSDKTemplate(string $SDKTemplate): Route
    {
        $this->SDKTemplate = $SDKTemplate;
        return $this;
    }

    public function getUniqueIdentifier()
    {
        return implode(
            "::",
            [
                $this->getRouterPattern(),
                $this->getHttpMethod(),
                "Weight={$this->getWeight()}",
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
    public function setHttpMethod($httpMethod) : Route
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    public function getWeight() : int
    {
        return $this->weight;
    }

    public function setWeight(int $weight) : Route
    {
        $this->weight = $weight;
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
    public function setRouterPattern($routerPattern) : Route
    {
        $this->routerPattern = $routerPattern;
        return $this;
    }

    /**
     * @param callable $finderFunction
     *
     * @return Route
     */
    public function setExampleEntityFindFunction(callable $finderFunction) : Route
    {
        $this->exampleEntityFinderFunction = $finderFunction;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExampleEntity()
    {
        if (!$this->exampleEntity && $this->exampleEntityFinderFunction) {
            $function = $this->exampleEntityFinderFunction;
            $this->exampleEntity = $function();
        }
        return $this->exampleEntity;
    }

    /**
     * @param mixed $exampleEntity
     *
     * @return Route
     */
    public function setExampleEntity($exampleEntity) : Route
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
    public function setName($name) : Route
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSDKClass()
    {
        return $this->SDKClass;
    }

    /**
     * @param mixed $SDKClass
     *
     * @return Route
     */
    public function setSDKClass($SDKClass) : Route
    {
        $this->SDKClass = $SDKClass;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSDKFunction()
    {
        return $this->SDKFunction;
    }

    /**
     * @param mixed $function
     *
     * @return Route
     */
    public function setSDKFunction($function) : Route
    {
        $this->SDKFunction = $function;
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
    public function setSingular($singular) : Route
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
    public function setPlural($plural) : Route
    {
        $this->plural = $plural;
        return $this;
    }

    public function getPropertyData(){
        return $this->propertyData;
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
    public function setProperties($properties) : Route
    {
        $this->properties = [];
        foreach ($properties as $name => $type) {
            if(is_numeric($name)){
                $this->properties[] = $type;
            } else {
                $this->properties[] = $name;
                $this->propertyData[$name]["type"] = $type;
            }
        }
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPropertyOptions()
    {
        return $this->propertyOptions;
    }

    /**
     * @param mixed $propertyOptions
     *
     * @return Route
     */
    public function setPropertyOptions($propertyOptions)
    {
        $this->propertyOptions = [];
        foreach ($propertyOptions as $name => $options) {
            $this->propertyOptions[$name] = $options;
            $this->propertyData[$name]["options"] = $options;
        }
        return $this;
    }

    /**
     * @param App $app
     *
     * @return \Slim\App
     */
    public function populateRoute(App $app) : App
    {
        #echo "Populating: {$this->getHttpMethod()} {$this->getRouterPattern()}\n";
        $mapping = $app->map(
            [$this->getHttpMethod()],
            $this->getRouterPattern(),
            $this->getCallback()
        );

        $mapping->setName($this->getName() ? $this->getName() : "Unnamed Route");
        $mapping->setArgument('access', $this->getAccess());
        return $app;
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
    public function setCallback($callback) : Route
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
    public function setHttpEndpoint($httpEndpoint) : Route
    {
        $this->httpEndpoint = $httpEndpoint;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccess()
    {
        return $this->access;
    }

    /**
     * @param string $access
     *
     * @return Route
     */
    public function setAccess($access = self::ACCESS_PUBLIC) : Route
    {
        $this->access = $access;
        return $this;
    }
}
