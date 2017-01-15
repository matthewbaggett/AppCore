<?php
namespace Segura\AppCore\Services;

use Monolog\Logger;
use Predis\Client;
use SebastianBergmann\Diff\Differ;
use Segura\Session\Session;

class EnvironmentService{

    /** @var array */
    protected $environmentVariables;

    /** @var AutoConfigurationService */
    protected $autoConfigurationService;

    public function __construct(AutoConfigurationService $autoConfigurationService)
    {

        $this->autoConfigurationService = $autoConfigurationService;
        $this->autoConfigurationService->setEnvironmentService($this);

        foreach(array_merge($_SERVER, $_ENV) as $key => $value){
            $this->environmentVariables[$key] = $value;
        }
        ksort($this->environmentVariables);

        $autoConfiguration = $this->autoConfigurationService->isGondalezConfigurationPresent() ? $this->autoConfigurationService->getConfiguration() : [];

        $this->environmentVariables = array_merge($this->environmentVariables, $autoConfiguration);

        ksort($this->environmentVariables);

        die("arse");
    }

    /**
     * @param string $var
     * @return bool
     */
    public function isSet(string $var)
    {
        return isset($this->environmentVariables[$var]);
    }

    /**
     * @param string $var
     * @return bool
     */
    public function get(string $var)
    {
        return $this->isSet($var) ? $this->environmentVariables[$var] : false;
    }

    public function __toArray()
    {
        return $this->environmentVariables;
    }

}