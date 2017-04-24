<?php
namespace Segura\AppCore\Services;

use Symfony\Component\Yaml\Yaml;

class EnvironmentService
{

    /** @var array */
    protected $environmentVariables;

    /** @var AutoConfigurationService */
    protected $autoConfigurationService;

    protected $cacheFile = "/tmp/.configcache.yml";

    public function __construct(
        AutoConfigurationService $autoConfigurationService
    ) {
        if (file_exists($this->cacheFile)) {
            $this->environmentVariables = Yaml::parse(file_get_contents($this->cacheFile));
        } else {
            $this->autoConfigurationService = $autoConfigurationService;
            $this->autoConfigurationService->setEnvironmentService($this);

            foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
                $this->environmentVariables[$key] = $value;
            }
            $autoConfiguration                              = $this->autoConfigurationService->isGondalezConfigurationPresent() ? $this->autoConfigurationService->getConfiguration() : [];
            $this->environmentVariables                     = array_merge($this->environmentVariables, $autoConfiguration);
            $this->environmentVariables['GONDALEZ_ENABLED'] = $this->autoConfigurationService->isGondalezConfigurationPresent() ? 'Yes' : 'No';
            file_put_contents($this->cacheFile, Yaml::dump($this->environmentVariables));
        }
        ksort($this->environmentVariables);
    }

    /**
     * @param string $var
     *
     * @return bool
     */
    public function isSet(string $var)
    {
        return isset($this->environmentVariables[$var]);
    }

    /**
     * @param string $var
     *
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
