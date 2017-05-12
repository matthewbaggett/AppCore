<?php
namespace Segura\AppCore\Services;

use Segura\AppCore\Abstracts\Service as AbstractService;
use Segura\AppCore\Exceptions\TableGatewayRecordNotFoundException;
use Segura\AppCore\Interfaces\ServiceInterface as ServiceInterface;
use Segura\AppCore\Models;
use Segura\AppCore\Models\UpdaterModel;
use Segura\AppCore\TableGateways;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Select;

class UpdaterService extends AbstractService implements ServiceInterface
{
    public function updateAlreadyApplied($file)
    {
        try {
            $this->getByField(UpdaterModel::FIELD_FILE, $file);
            return true;
        } catch (TableGatewayRecordNotFoundException $exception) {
            return false;
        }
    }


    // Related Objects Table Gateways

    // Remote Constraints Table Gateways

    // Self Table Gateway
    /** @var TableGateways\UpdaterTableGateway */
    protected $updaterTableGateway;

    /**
     * Constructor.
     *
     * @param TableGateways\UpdaterTableGateway $updaterTableGateway
     */
    public function __construct(
        TableGateways\UpdaterTableGateway $updaterTableGateway
    ) {
        $this->updaterTableGateway = $updaterTableGateway;
    }

    public function getNewTableGatewayInstance() : TableGateways\UpdaterTableGateway
    {
        return $this->updaterTableGateway;
    }

    public function getNewModelInstance($dataExchange = []) : Models\UpdaterModel
    {
        return $this->updaterTableGateway->getNewModelInstance($dataExchange);
    }

    /**
     * @param int|null              $limit
     * @param int|null              $offset
     * @param array|\Closure[]|null $wheres
     * @param string|null           $order
     * @param string|null           $orderDirection
     *
     * @return Models\UpdaterModel[]
     */
    public function getAll(
        int $limit = null,
        int $offset = null,
        array $wheres = null,
        string $order = null,
        string $orderDirection = null
    ) {
        $updaterTable              = $this->getNewTableGatewayInstance();
        list($allUpdaters, $count) = $updaterTable->fetchAll(
            $limit,
            $offset,
            $wheres,
            $order,
            $orderDirection !== null ? $orderDirection : Select::ORDER_ASCENDING
        );
        $return = [];

        if ($allUpdaters instanceof ResultSet) {
            foreach ($allUpdaters as $updater) {
                $return[] = $updater;
            }
        }
        return $return;
    }

    /**
     * @param int $id
     *
     * @throws \Segura\AppCore\Exceptions\TableGatewayException
     * @throws \Segura\AppCore\Exceptions\TableGatewayRecordNotFoundException
     *
     * @return Models\UpdaterModel
     */
    public function getById(int $id) : Models\UpdaterModel
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        return $updaterTable->getById($id);
    }

    /**
     * @param string $field
     * @param $value
     * @param $orderBy string Field to sort by
     * @param $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @throws \Segura\AppCore\Exceptions\TableGatewayException
     * @throws \Segura\AppCore\Exceptions\TableGatewayRecordNotFoundException
     *
     * @return Models\UpdaterModel
     */
    public function getByField(string $field, $value, $orderBy = null, $orderDirection = Select::ORDER_ASCENDING) : Models\UpdaterModel
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        return $updaterTable->getByField($field, $value, $orderBy, $orderDirection);
    }

    /**
     * @param string $field
     * @param $value
     * @param $limit int
     * @param $orderBy string Field to sort by
     * @param $orderDirection string Direction to sort (Select::ORDER_ASCENDING || Select::ORDER_DESCENDING)
     *
     * @throws \Segura\AppCore\Exceptions\TableGatewayException
     * @throws \Segura\AppCore\Exceptions\TableGatewayRecordNotFoundException
     *
     * @return Models\UpdaterModel[]
     */
    public function getManyByField(string $field, $value, int $limit = null, $orderBy = null, $orderDirection = Select::ORDER_ASCENDING) : array
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        return $updaterTable->getManyByField($field, $value, $limit, $orderBy, $orderDirection);
    }

    /**
     * @throws \Segura\AppCore\Exceptions\TableGatewayException
     *
     * @return Models\UpdaterModel
     */
    public function getRandom() : Models\UpdaterModel
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        return $updaterTable->fetchRandom();
    }

    /**
     * @param $dataExchange
     *
     * @return array|\ArrayObject|null
     */
    public function createFromArray($dataExchange)
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        $updater      = $this->getNewModelInstance($dataExchange);
        return $updaterTable->save($updater);
    }

    /**
     * @param int $id
     *
     * @return int
     */
    public function deleteByID($id) : int
    {
        /** @var TableGateways\UpdaterTableGateway $updaterTable */
        $updaterTable = $this->getNewTableGatewayInstance();
        return $updaterTable->delete(['id' => $id]);
    }

    public function getTermPlural() : string
    {
        return 'Updaters';
    }

    public function getTermSingular() : string
    {
        return 'Updater';
    }

    /**
     * @returns Models\UpdaterModel
     */
    public function getMockObject()
    {
        return $this->getNewTableGatewayInstance()->getNewMockModelInstance();
    }
}
