<?php

namespace Segura\AppCore;

use Pimple\Container as PimpleContainer;
use Slim\Exception\ContainerException;
use Slim\Exception\ContainerException as SlimContainerException;
use Slim\Exception\ContainerValueNotFoundException;

class Container extends \Slim\Container
{
    public function __construct(array $values = [])
    {
        parent::__construct($values);
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws ContainerValueNotFoundException No entry was found for this identifier.
     * @throws ContainerException              Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        if (!$this->offsetExists($id) && class_exists($id)) {
            $reflection = new \ReflectionClass($id);
            $params     = [];
            if ($reflection->getConstructor() !== null && count($reflection->getConstructor()->getParameters()) > 0) {
                foreach ($reflection->getConstructor()->getParameters() as $order => $parameter) {
                    if ($parameter->getClass()) {
                        $params[$order] = $this->get($parameter->getClass()->getName());
                    }
                }
            }
            $entity = $reflection->newInstanceArgs($params);

            return $entity;
        }
        try {
            return $this->offsetGet($id);
        } catch (\InvalidArgumentException $exception) {
            if ($this->exceptionThrownByContainer($exception)) {
                throw new SlimContainerException(
                    sprintf('Container error while retrieving "%s"', $id),
                    null,
                    $exception
                );
            } else {
                throw $exception;
            }
        }
    }

    public function has($id)
    {
        if (!$this->offsetExists($id) && class_exists($id)) {
            return true;
        }
        return $this->offsetExists($id);
    }

    /**
     * Tests whether an exception needs to be recast for compliance with Container-Interop.  This will be if the
     * exception was thrown by Pimple.
     *
     * @param \InvalidArgumentException $exception
     *
     * @return bool
     */
    private function exceptionThrownByContainer(\InvalidArgumentException $exception)
    {
        $trace = $exception->getTrace()[0];

        return $trace['class'] === PimpleContainer::class && $trace['function'] === 'offsetGet';
    }
}
