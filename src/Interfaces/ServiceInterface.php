<?php
namespace Segura\AppCore\Interfaces;

interface ServiceInterface
{
    /**
     * @param int|null    $limit
     * @param int|null    $offset
     * @param array|null  $wheres
     * @param string|null $order
     * @param string|null $orderDirection
     *
     * @return ModelInterface[]
     */
    public function getAll(
        int $limit = null,
        int $offset = null,
        array $wheres = null,
        string $order = null,
        string $orderDirection = null
    );

    public function getById(int $id);

    public function getByField(string $field, $value);

    public function getRandom();
}
