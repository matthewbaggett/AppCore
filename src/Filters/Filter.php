<?php
namespace Segura\AppCore\Filters;

use Segura\AppCore\Exceptions\FilterDecodeException;
use Zend\Db\Sql\Expression;

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
    public function setOrderDirection($orderDirection) : self
    {
        if (!in_array(strtoupper($orderDirection), ['ASC', 'DESC', 'RAND'])) {
            throw new FilterDecodeException("Failed to decode Filter Order, Direction unknown: {$orderDirection} must be ASC|DESC|RAND");
        }
        $this->orderDirection = strtoupper($orderDirection);
        return $this;
    }

    /**
     * @param $header
     *
     * @throws FilterDecodeException
     */
    public function parseFromHeader($header) : self
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
        return $this;
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
    public function setLimit($limit) : self
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
    public function setOffset($offset) : self
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
    public function setWheres($wheres) : self
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
    public function setOrder($order) : self
    {
        $this->order = $order;
        return $this;
    }

    public function setOrderRandom() : self
    {
        $this->setOrder(new Expression('RAND()'));
        return $this;
    }

    public function parseOrder($orderArray) : self
    {
        if (in_array(strtolower($orderArray['column']), ['rand', 'random', 'rand()'])) {
            $this->setOrderRandom();
        } elseif (isset($orderArray['column']) && isset($orderArray['direction'])) {
            $this->setOrder($orderArray['column']);

            if (isset($orderArray['direction'])) {
                $this->setOrderDirection($orderArray['direction']);
            }
        } else {
            throw new FilterDecodeException("Could not find properties 'column' or 'direction' of the order array given.");
        }

        return $this;
    }
}
