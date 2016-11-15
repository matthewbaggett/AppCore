<?php
namespace Segura\AppCore\Filters;

use Segura\AppCore\Exceptions\FilterDecodeException;

class Filter
{
    protected $limit;
    protected $offset;
    protected $wheres;
    protected $order;
    protected $orderDirection;

    /**
     * @return mixed
     */
    public function getOrderDirection()
    {
        return $this->orderDirection;
    }

    /**
     * @param mixed $orderDirection
     *
     * @throws FilterDecodeException
     *
     * @return Filter
     */
    public function setOrderDirection($orderDirection)
    {
        if (!in_array(strtoupper($orderDirection), ['ASC', 'DESC'])) {
            throw new FilterDecodeException("Failed to decode Filter Order, Direction unknown: {$orderDirection} must be ASC|DESC");
        }
        $this->orderDirection = strtoupper($orderDirection);
        return $this;
    }

    /**
     * @param $header
     *
     * @throws FilterDecodeException
     */
    public function parseFromHeader($header)
    {
        foreach ($header as $key => $value) {
            switch ($key) {
                case 'limit':
                    $this->setLimit($value);
                    break;
                case 'offset':
                    $this->setOffset($value);
                    break;
                case 'wheres':
                    $this->setWheres($value);
                    break;
                case 'order':
                    $this->parseOrder($value);
                    break;
                default:
                    throw new FilterDecodeException("Failed to decode Filter, unknown key: {$key}");
            }
        }
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param mixed $limit
     *
     * @return Filter
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param mixed $offset
     *
     * @return Filter
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getWheres()
    {
        return $this->wheres;
    }

    /**
     * @param mixed $wheres
     *
     * @return Filter
     */
    public function setWheres($wheres)
    {
        $this->wheres = $wheres;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @param mixed $order
     *
     * @return Filter
     */
    public function setOrder($order)
    {
        $this->order = $order;
        return $this;
    }

    public function parseOrder($orderArray)
    {
        if (isset($orderArray['column']) && isset($orderArray['direction'])) {
            $this
                ->setOrder($orderArray['column'])
                ->setOrderDirection($orderArray['direction']);
        } else {
            throw new FilterDecodeException("Could not find properties 'column' or 'direction' of the order array given.");
        }
    }
}
