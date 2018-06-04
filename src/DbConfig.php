<?php

namespace Segura\AppCore;

class DbConfig implements \ArrayAccess, \Iterator
{
    private $configs;
    private $position = 0;

    private static $persistantAdapterPool = [];

    public function __construct()
    {
        $this->position = 0;
    }

    public function __toArray()
    {
        return $this->configs;
    }

    public function set($name, $array)
    {
        $this->configs[$name] = $array;
    }

    /**
     * @return Adapter[]
     */
    public function getAdapterPool()
    {
        $adapterPool = [];
        foreach ($this->configs as $name => $dbConfig) {
            $key = $name . ":" . crc32(implode(":", $dbConfig));
            if(!isset(self::$persistantAdapterPool[$key])){
                self::$persistantAdapterPool[$key] = new Adapter($dbConfig);
            }
            $adapterPool[$name] = self::$persistantAdapterPool[$key];
        }
        return $adapterPool;
    }

    public function offsetExists($offset)
    {
        return isset($this->configs[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->configs[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->configs[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->configs[$offset]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->configs[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->configs[$this->position]);
    }
}
