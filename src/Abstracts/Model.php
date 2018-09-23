<?php
namespace Segura\AppCore\Abstracts;

use Camel\CaseTransformer;
use Camel\Format;
use Segura\AppCore\Interfaces\ModelInterface;

abstract class Model implements ModelInterface
{
    protected $_primary_keys       = [];
    protected $_autoincrement_keys = [];

    protected $_original;


    public function __construct(array $data = [])
    {
        if ($data) {
            $this->exchangeArray($data);
        }
    }

    /**
     * @return array
     */
    public function __toArray()
    {
        $array = [];

        $transformer = new CaseTransformer(new Format\StudlyCaps(), new Format\StudlyCaps());

        foreach ($this->getListOfProperties() as $property) {
            $getFunction                               = "get{$property}";
            $currentValue                              = $this->$getFunction();
            $array[$transformer->transform($property)] = $currentValue;
        }

        return array_merge($array);
    }

    /**
     * @return array
     */
    public function __toRawArray()
    {
        $array = [];

        $transformer = new CaseTransformer(new Format\StudlyCaps(), new Format\StudlyCaps());

        foreach ($this->getListOfProperties() as $property) {
            $currentValue                              = $this->$property;
            $array[$transformer->transform($property)] = $currentValue;
        }

        return array_merge($array);
    }

    /**
     * @return array
     */
    public function __toPublicArray() : array
    {
        $publicArray = [];
        foreach ($this->getListOfProperties() as $property) {
            $publicArray[ucfirst($property)] = $this->$property;
        }
        return $publicArray;
    }

    public function __serialize() : string
    {
        return json_encode(
            $this->__toPublicArray(),
            JSON_PRETTY_PRINT
        );
    }

    public function __pre_save()
    {
        // Stub function to be overridden.
    }

    public function __post_save()
    {
        // Stub function to be overridden.
    }

    public static function factory(array $data = [])
    {
        $class = get_called_class();
        return new $class($data);
    }

    /**
     * @return \Interop\Container\ContainerInterface
     */
    public function getDIContainer()
    {
        return App::Container();
    }

    /**
     * @param array $data
     *
     * @return Model $this
     */
    public function exchangeArray(array $data)
    {
        $transformer = new CaseTransformer(new Format\CamelCase(), new Format\StudlyCaps());
        
        foreach ($data as $key => $value) {
            $method           = 'set' . $transformer->transform($key);
            $originalProperty = $transformer->transform($key);

            if (method_exists($this, $method)) {
                if (is_numeric($value) && constant(get_called_class() . '::' . "TYPE_" . strtoupper($key)) == 'int') {
                    $value = doubleval($value);
                }
                $this->$method($value);
                #echo "Writing into \$this->{$originalProperty}: \"{$value}\"\n";
                $this->_original[$originalProperty] = $value;
            }
        }
        
        return $this;
    }

    /**
     * Return primary key values in an associative array.
     *
     * @return array
     */
    public function getPrimaryKeys()
    {
        $primaryKeyValues = [];
        foreach ($this->_primary_keys as $primary_key) {
            $getFunction                    = "get{$primary_key}";
            $primaryKeyValues[$primary_key] = $this->$getFunction();
        }
        return $primaryKeyValues;
    }

    /**
     * Return autoincrement key values in an associative array.
     *
     * @return array
     */
    public function getAutoIncrementKeys()
    {
        $autoIncrementKeyValues = [];
        foreach ($this->_autoincrement_keys as $autoincrement_key) {
            $getFunction                                = "get{$autoincrement_key}";
            $autoIncrementKeyValues[$autoincrement_key] = $this->$getFunction();
        }
        return $autoIncrementKeyValues;
    }


    /**
     * Returns true if the primary key isn't null.
     *
     * @return bool
     */
    public function hasPrimaryKey()
    {
        $notNull = false;
        foreach ($this->getPrimaryKeys() as $primaryKey) {
            if ($primaryKey != null) {
                $notNull = true;
            }
        }
        return $notNull;
    }

    public function getListOfProperties()
    {
        throw new \Exception("getListOfProperties in Abstract Model should never be used.");
    }

    /**
     * Returns whether or not the data has been modified inside this model.
     */
    public function hasDirtyProperties() : bool
    {
        return count($this->getListOfDirtyProperties()) > 0;
    }

    /**
     * Returns an array of dirty properties.
     */
    public function getListOfDirtyProperties() : array
    {
        $transformer     = new CaseTransformer(new Format\CamelCase(), new Format\StudlyCaps());
        $dirtyProperties = [];
        foreach ($this->getListOfProperties() as $property) {
            $originalProperty = $transformer->transform($property);
            #echo "Writing into \$this->{$originalProperty}: getListOfDirtyProperties\n";
            if (!isset($this->_original[$originalProperty]) || $this->$property != $this->_original[$originalProperty]) {
                $dirtyProperties[$property] = [
                    'before' => isset($this->_original[$originalProperty]) ? $this->_original[$originalProperty] : null,
                    'after'  => $this->$property,
                ];
            }
        }
        return $dirtyProperties;
    }

    protected function getProtectedMethods()
    {
        return ['getPrimaryKeys', 'getProtectedMethods', 'getDIContainer'];
    }
}
