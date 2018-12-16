<?php
namespace Gone\AppCore\Models\Base;

use Gone\AppCore\Abstracts\Model as AbstractModel;
use Gone\AppCore\App;
use Gone\AppCore\Interfaces\ModelInterface as ModelInterface;
use Gone\AppCore\Models;
use Gone\AppCore\TableGateways;

/********************************************************
 *             ___                         __           *
 *            / _ \___ ____  ___ ____ ____/ /           *
 *           / // / _ `/ _ \/ _ `/ -_) __/_/            *
 *          /____/\_,_/_//_/\_, /\__/_/ (_)             *
 *                         /___/                        *
 *                                                      *
 * Anything in this file is prone to being overwritten! *
 *                                                      *
 * This file was programatically generated. To modify   *
 * this classes behaviours, do so in the class that     *
 * extends this, or modify the Zenderator Template!     *
 ********************************************************/
abstract class BaseUpdaterModel extends AbstractModel implements ModelInterface
{

    // Declare what fields are available on this object
    const FIELD_ID          = 'id';
    const FIELD_FILE        = 'file';
    const FIELD_DATEAPPLIED = 'dateApplied';

    const TYPE_ID = 'int';
    const TYPE_FILE = 'text';
    const TYPE_DATEAPPLIED = 'datetime';

    // Constants defined by ENUMs

    protected $_primary_keys = ['id'];

    protected $_autoincrement_keys = ['id'];

    protected $id;
    protected $file;
    protected $dateApplied;

    /**
     * @param array $data An array of a UpdaterModel's properties.
     * @returns Models\UpdaterModel
     */
    public static function factory(array $data = [])
    {
        return parent::factory($data);
    }

    /**
     * @returns int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @returns Models\UpdaterModel
     */
    public function setId(int $id = null)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @returns string
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @param string $file
     * @returns Models\UpdaterModel
     */
    public function setFile(string $file = null)
    {
        $this->file = $file;
        return $this;
    }

    /**
     * @returns string
     */
    public function getDateApplied()
    {
        return $this->dateApplied;
    }

    /**
     * @param string $dateApplied
     * @returns Models\UpdaterModel
     */
    public function setDateApplied(string $dateApplied = null)
    {
        $this->dateApplied = $dateApplied;
        return $this;
    }


    /*****************************************************
     * "Referenced To" Remote Constraint Object Fetchers *
     *****************************************************/


    /**
     * @returns Models\UpdaterModel
     */
    public function save()
    {
        /** @var $tableGateway TableGateways\UpdaterTableGateway */
        $tableGateway = App::Container()->get(TableGateways\UpdaterTableGateway::class);
        return $tableGateway->save($this);
    }

    /**
     * Destroy the current record.
     *
     * @return int Number of affected rows.
     */
    public function destroy()
    {
        /** @var $tableGateway TableGateways\UpdaterTableGateway */
        $tableGateway = App::Container()->get(TableGateways\UpdaterTableGateway::class);
        return $tableGateway->delete($this->getPrimaryKeys());
    }

    /**
     * Destroy the current record, and any dependencies upon it, recursively.
     *
     * @return int Number of affected models.
     */
    public function destroyThoroughly()
    {
        $countOfThingsDestroyed = 0;
        $thingsToDestroy        = [];
        if (count($thingsToDestroy) > 0) {
            foreach ($thingsToDestroy as $thingToDestroy) {
                /** @var $thingToDestroy ModelInterface */
                $countOfThingsDestroyed+= $thingToDestroy->destroyThoroughly();
            }
        }
        $this->destroy();
        $countOfThingsDestroyed++;
        return $countOfThingsDestroyed;
    }


    /**
     * Provides an array of all properties in this model.
     *
     * @returns array
     */
    public function getListOfProperties()
    {
        return [
            'id',
            'file',
            'dateApplied',
        ];
    }
}
