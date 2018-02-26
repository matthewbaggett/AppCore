<?php
namespace Segura\AppCore\Abstracts;

use Segura\AppCore\Exceptions\TableGatewayException;
use Segura\AppCore\Filters\FilterCondition;
use Segura\AppCore\ZendSql;
use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Predicate;
use Zend\Db\Sql\Predicate\PredicateInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Where;
use Zend\Db\TableGateway\TableGateway as ZendTableGateway;

abstract class TableGateway extends ZendTableGateway
{
    public function __construct($table, AdapterInterface $adapter, $features = null, $resultSetPrototype = null, $sql = null)
    {
        $this->adapter = $adapter;
        $this->table   = $table;

        if (!$sql) {
            $sql = new ZendSql($this->adapter, $this->table);
        }
        parent::__construct($table, $adapter, $features, $resultSetPrototype, $sql);
    }

    protected $model;

    /**
     * @param Model $model
     *
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null
     */
    public function save(Model $model)
    {
        $model->__pre_save();

        $pk = $model->getPrimaryKeys();

        $pkIsBlank = true;
        foreach ($pk as $key => $value) {
            if (!is_null($value)) {
                $pkIsBlank = false;
            }
        }

        try {
            /** @var Model $oldModel */
            $oldModel = $this->select($pk)->current();
            if ($pkIsBlank || !$oldModel) {
                $pk = $this->saveInsert($model);
                if (!is_array($pk)) {
                    $pk = ['id' => $pk];
                }
            } else {
                $this->saveUpdate($model, $oldModel);
            }
            $updatedModel = $this->getByPrimaryKey($pk);

            // Update the primary key fields on the existant $model object, because we may still be referencing this.
            // While it feels a bit yucky to magically mutate the model object, it is expected behaviour.
            foreach ($model->getPrimaryKeys() as $key => $value) {
                $setter = "set{$key}";
                $getter = "get{$key}";
                $model->$setter($updatedModel->$getter());
            }

            $model->__post_save();

            return $updatedModel;
        } catch (InvalidQueryException $iqe) {
            throw new InvalidQueryException(
                "While trying to call " . get_class() . "->save(): ... " .
                $iqe->getMessage() . "\n\n" .
                substr(var_export($model, true), 0, 1024) . "\n\n",
                $iqe->getCode(),
                $iqe
            );
        }
    }

    /**
     * @param Model $model
     *
     * @return int|null
     */
    public function saveInsert(Model $model)
    {
        $data = $model->__toRawArray();
        $this->insert($data);

        if ($model->hasPrimaryKey()) {
            return $model->getPrimaryKeys();
        } else {
            return $this->getLastInsertValue();
        }
    }


    /**
     * @param Model $model
     * @param Model $oldModel
     *
     * @return int
     */
    public function saveUpdate(Model $model, Model $oldModel)
    {
        return $this->update(
            $model->__toRawArray(),
            $model->getPrimaryKeys(),
            $oldModel->__toRawArray()
        );
    }

    /**
     * @param array $data
     * @param null  $id
     *
     * @return int
     */
    public function insert($data, &$id = null)
    {
        return parent::insert($data);
    }

    /**
     * @param array       $data
     * @param null        $where
     * @param array|Model $oldData
     *
     * @return int
     */
    public function update($data, $where = null, $oldData = [])
    {
        #!\Kint::dump($data);exit;
        return parent::update($data, $where);
    }

    /**
     * This method is only supposed to be used by getListAction.
     *
     * @param int    $limit     Number to limit to
     * @param int    $offset    Offset of limit statement. Is ignored if limit not set.
     * @param array  $wheres    Array of conditions to filter by.
     * @param string $order     Column to order on
     * @param string $direction Direction to order on (SELECT::ORDER_ASCENDING|SELECT::ORDER_DESCENDING)
     *
     * @return array [ResultSet,int] Returns an array of resultSet,total_found_rows
     */
    public function fetchAll(
        int $limit = null,
        int $offset = null,
        array $wheres = null,
        string $order = null,
        string $direction = Select::ORDER_ASCENDING
    ) {
        /** @var Select $select */
        $select = $this->getSql()->select();

        if ($limit !== null && is_numeric($limit)) {
            $select->limit(intval($limit));
            if ($offset !== null && is_numeric($offset)) {
                $select->offset($offset);
            }
        }
        //\Kint::dump($limit, $offset, $wheres, $order, $direction);
        if ($wheres != null) {
            foreach ($wheres as $conditional) {
                if ($conditional instanceof \Closure) {
                    $select->where($conditional);
                } else {
                    $spec = function (Where $where) use ($conditional) {
                        switch ($conditional['condition']) {
                            case FilterCondition::CONDITION_EQUAL:
                                $where->equalTo($conditional['column'], $conditional['value']);
                                break;
                            case FilterCondition::CONDITION_GREATER_THAN:
                                $where->greaterThan($conditional['column'], $conditional['value']);
                                break;
                            case FilterCondition::CONDITION_GREATER_THAN_OR_EQUAL:
                                $where->greaterThanOrEqualTo($conditional['column'], $conditional['value']);
                                break;
                            case FilterCondition::CONDITION_LESS_THAN:
                                $where->lessThan($conditional['column'], $conditional['value']);
                                break;
                            case FilterCondition::CONDITION_LESS_THAN_OR_EQUAL:
                                $where->lessThanOrEqualTo($conditional['column'], $conditional['value']);
                                break;#
                            case FilterCondition::CONDITION_LIKE:
                                $where->like($conditional['column'], $conditional['value']);
                                break;
                            default:
                                // @todo better exception plz.
                                throw new \Exception("Cannot work out what conditional {$conditional['condition']} is supposed to do in Zend... Probably unimplemented?");
                        }
                    };
                    $select->where($spec);
                }
            }
        }

        if ($order !== null) {
            $select->order("{$order} {$direction}");
        }

        $resultSet = $this->selectWith($select);

        $quantifierSelect = $select
            ->reset(Select::LIMIT)
            ->reset(Select::COLUMNS)
            ->reset(Select::OFFSET)
            ->reset(Select::ORDER)
            ->reset(Select::COMBINE)
            ->columns(['total' => new Expression('COUNT(*)')]);

        /* execute the select and extract the total */
        $row = $this->getSql()
            ->prepareStatementForSqlObject($quantifierSelect)
            ->execute()
            ->current();
        $total = (int)$row['total'];

        return [$resultSet, $total];
    }

    /**
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null
     */
    public function fetchRandom()
    {
        $resultSet = $this->select(function (Select $select) {
            $select->order(new Expression('RAND()'))->limit(1);
        });

        if (0 == count($resultSet)) {
            throw new TableGatewayException("No data found in table!");
        }

        return $resultSet->current();
    }

    /**
     * @param array|Select $where
     * @param array|string $order
     * @param int          $offset
     *
     * @throws TableGatewayException
     *
     * @return array|\ArrayObject|null|Model
     */
    public function fetchRow($where = null, $order = null, $offset = null)
    {
        if ($where instanceof Select) {
            $resultSet = $this->selectWith($where);
        } else {
            $resultSet = $this->select(function (Select $select) use ($where, $order, $offset) {
                if (!is_null($where)) {
                    $select->where($where);
                }
                if (!is_null($order)) {
                    $select->order($order);
                }
                if (!is_null($offset)) {
                    $select->offset($offset);
                }
                $select->limit(1);
            });
        }

        return (count($resultSet) > 0) ? $resultSet->current() : null;
    }

    /**
     * @param Where[]|PredicateInterface[] $wheres
     *
     * @return int
     */
    public function getCount($wheres = [])
    {
        $select = $this->getSql()->select();
        $select->columns(['total' => new Expression('IFNULL(COUNT(*),0)')]);
        if (count($wheres) > 0) {
            foreach ($wheres as $where) {
                $select->where($where);
            }
        }

        $row = $this->getSql()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return !is_null($row) ? $row['total'] : 0;
    }

    /**
     * @param string                       $field
     * @param Where[]|PredicateInterface[] $wheres
     *
     * @return int
     */
    public function getCountUnique(string $field, $wheres = [])
    {
        $select = $this->getSql()->select();
        $select->columns(['total' => new Expression('DISTINCT ' . $field)]);
        if (count($wheres) > 0) {
            foreach ($wheres as $where) {
                $select->where($where);
            }
        }

        $row = $this->getSql()
            ->prepareStatementForSqlObject($select)
            ->execute()
            ->current();

        return !is_null($row) ? $row['total'] : 0;
    }

    public function getPrimaryKeys()
    {
        /** @var Model $oModel */
        $oModel = $this->getNewMockModelInstance();
        return array_keys($oModel->getPrimaryKeys());
    }

    public function getAutoIncrementKeys()
    {
        /** @var Model $oModel */
        $oModel = $this->getNewMockModelInstance();
        return array_keys($oModel->getAutoIncrementKeys());
    }

    /**
     * Returns an array of all primary keys on the table keyed by the column.
     *
     * @return array
     */
    public function getHighestPrimaryKey()
    {
        $highestPrimaryKeys = [];
        foreach ($this->getPrimaryKeys() as $primaryKey) {
            $Select = $this->getSql()->select();
            $Select->columns(['max' => new Expression("MAX({$primaryKey})")]);
            $row = $this->getSql()
                ->prepareStatementForSqlObject($Select)
                ->execute()
                ->current();

            $highestPrimaryKey               = !is_null($row) ? $row['max'] : 0;
            $highestPrimaryKeys[$primaryKey] = $highestPrimaryKey;
        }
        return $highestPrimaryKeys;
    }

    /**
     * Returns an array of all autoincrement keys on the table keyed by the column.
     *
     * @return array
     */
    public function getHighestAutoincrementKey()
    {
        $highestAutoIncrementKeys = [];
        foreach ($this->getPrimaryKeys() as $autoIncrementKey) {
            $Select = $this->getSql()->select();
            $Select->columns(['max' => new Expression("MAX({$autoIncrementKey})")]);
            $row = $this->getSql()
                ->prepareStatementForSqlObject($Select)
                ->execute()
                ->current();

            $highestAutoIncrementKey                     = !is_null($row) ? $row['max'] : 0;
            $highestAutoIncrementKeys[$autoIncrementKey] = $highestAutoIncrementKey;
        }
        return $highestAutoIncrementKeys;
    }

    /**
     * @param $id
     *
     * @throws TableGatewayException
     *
     * @return Model|false
     */
    public function getById($id)
    {
        try {
            return $this->getByField('id', $id);
        } catch (TableGatewayException $tge) {
            throw new TableGatewayException("Cannot find {$this->getModelName()} record by ID '{$id}'");
        }
    }

    /**
     * @param $field
     * @param $value
     * @param $orderBy string Field to sort by
     * @param $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @return array|\ArrayObject|null
     */
    public function getByField($field, $value, $orderBy = null, $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();

        $select->where([$field => $value]);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        $select->limit(1);

        $resultSet = $this->selectWith($select);

        $row = $resultSet->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * @param string $field
     * @param $value
     * @param $limit int
     * @param $orderBy string Field to sort by
     * @param $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @return array|\ArrayObject|null
     */
    public function getManyByField(string $field, $value, int $limit = null, string $orderBy = null, string $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();

        $select->where([$field => $value]);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }

        if ($limit) {
            $select->limit($limit);
        }

        $resultSet = $this->selectWith($select);

        $results = [];
        if ($resultSet->count() == 0) {
            return null;
        } else {
            for ($i = 0; $i < $resultSet->count(); $i++) {
                $row       = $resultSet->current();
                $results[] = $row;
                $resultSet->next();
            }
        }
        return $results;
    }

    public function countByField(string $field, $value)
    {
        $select = $this->sql->select();
        $select->where([$field => $value]);
        $select->columns([
            new Expression('COUNT(*) as count')
        ]);
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result    = $statement->execute();

        $data = $result->current();

        return $data['count'];
    }

    /**
     * @param array $primaryKeys
     *
     * @return array|\ArrayObject|null
     */
    public function getByPrimaryKey(array $primaryKeys)
    {
        $row = $this->select($primaryKeys)->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * Get single matching object
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $keyValue
     * @param null                                                     $orderBy
     * @param string                                                   $orderDirection
     *
     * @return array|\ArrayObject|null
     */
    public function getMatching($keyValue = [], $orderBy = null, $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();
        $select->where($keyValue);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        $select->limit(1);

        $resultSet = $this->selectWith($select);

        $row = $resultSet->current();
        if (!$row) {
            return null;
        }
        return $row;
    }

    /**
     * Get many matching objects
     *
     * @param Where|\Closure|string|array|Predicate\PredicateInterface $keyValue
     * @param null                                                     $orderBy
     * @param string                                                   $orderDirection
     *
     * @return array|\ArrayObject|null
     */
    public function getManyMatching($keyValue = [], $orderBy = null, $orderDirection = Select::ORDER_ASCENDING)
    {
        $select = $this->sql->select();
        $select->where($keyValue);
        if ($orderBy) {
            $select->order("{$orderBy} {$orderDirection}");
        }
        $resultSet = $this->selectWith($select);

        $results = [];
        if ($resultSet->count() == 0) {
            return null;
        } else {
            for ($i = 0; $i < $resultSet->count(); $i++) {
                $row       = $resultSet->current();
                $results[] = $row;
                $resultSet->next();
            }
        }
        return $results;
    }

    /**
     * @param array $data
     *
     * @return Model
     */
    public function getNewModelInstance(array $data = [])
    {
        $model = $this->model;
        return new $model($data);
    }

    /**
     * @param Select $select
     *
     * @return Model[]
     */
    public function getBySelect(Select $select)
    {
        $resultSet = $this->executeSelect($select);
        $return    = [];
        foreach ($resultSet as $result) {
            $return[] = $result;
        }
        return $return;
    }

    /**
     * @param Select $select
     *
     * @return Model[]
     */
    public function getBySelectRaw(Select $select)
    {
        $resultSet = $this->executeSelect($select);
        $return    = [];
        while ($result = $resultSet->getDataSource()->current()) {
            $return[] = $result;
            $resultSet->getDataSource()->next();
        }
        return $return;
    }

    /**
     * @return string
     */
    protected function getModelName()
    {
        $modelName = explode("\\", $this->model);
        $modelName = end($modelName);
        $modelName = str_replace("Model", "", $modelName);
        return $modelName;
    }
}
