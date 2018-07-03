<?php

namespace Segura\AppCore;

use Zend\Db\Adapter\Platform;
use Zend\Db\Adapter\Profiler;
use Zend\Db\ResultSet;

class Adapter extends \Zend\Db\Adapter\Adapter
{
    public function __construct($driver, Platform\PlatformInterface $platform = null, ResultSet\ResultSetInterface $queryResultPrototype = null, Profiler\ProfilerInterface $profiler = null)
    {
        parent::__construct($driver, $platform, $queryResultPrototype, $profiler);
        if (!defined('ZEND_PROFILER_DISABLE') || ZEND_PROFILER_DISABLE == false) {
            $this->setProfiler(App::Container()->get(\Segura\AppCore\Zend\Profiler::class));
        }
    }
}
