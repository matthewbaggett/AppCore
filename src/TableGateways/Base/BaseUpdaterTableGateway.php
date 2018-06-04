<?php
namespace Segura\AppCore\TableGateways\Base;

use \Segura\AppCore\Abstracts\Model;
use \Segura\AppCore\Abstracts\TableGateway as AbstractTableGateway;
use \Segura\AppCore\Db;
use \Segura\AppCore\Models;
use \Zend\Db\Adapter\AdapterInterface;
use \Zend\Db\ResultSet\ResultSet;

abstract class BaseUpdaterTableGateway extends AbstractTableGateway
{
    protected $table = 'updater';

    protected $database = 'Default';

    protected $model = Models\UpdaterModel::class;

    /** @var \Faker\Generator */
    protected $faker;

    /** @var Db */
    private $databaseConnector;

    private $databaseAdaptor;


    /**
     * AbstractTableGateway constructor.
     *
     * @param Db $databaseConnector
     */
    public function __construct(
        \Faker\Generator $faker,
        Db $databaseConnector
    ) {
        $this->faker             = $faker;
        $this->databaseConnector = $databaseConnector;

        /** @var $adaptor AdapterInterface */
        // @todo rename all uses of 'adaptor' to 'adapter'. I cannot spell - MB
        $this->databaseAdaptor = $this->databaseConnector->getDatabase($this->database);
        $resultSetPrototype    = new ResultSet(ResultSet::TYPE_ARRAYOBJECT, new $this->model);
        return parent::__construct($this->table, $this->databaseAdaptor, null, $resultSetPrototype);
    }

    /**
     * @return Models\UpdaterModel
     */
    public function getNewMockModelInstance()
    {
        $newUpdaterData = [
            // dateApplied. Type = datetime. PHPType = string. Has no related objects.
            'dateApplied' => $this->faker->dateTime()->format("Y-m-d H:i:s"), // @todo: Make datetime fields accept DateTime objects instead of strings. - MB
            // file. Type = text. PHPType = string. Has no related objects.
            'file' => substr($this->faker->text(500 >= 5 ? 500 : 5), 0, 500),
            // id. Type = int. PHPType = int. Has no related objects.
            'id' => null,
        ];
        $newUpdater = $this->getNewModelInstance($newUpdaterData);
        return $newUpdater;
    }

    /**
     * @param array $data
     *
     * @return Models\UpdaterModel
     */
    public function getNewModelInstance(array $data = [])
    {
        return parent::getNewModelInstance($data);
    }

    /**
     * @param Models\UpdaterModel $model
     *
     * @return Models\UpdaterModel
     */
    public function save(Model $model)
    {
        return parent::save($model);
    }
}
