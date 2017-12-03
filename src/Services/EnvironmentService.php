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

        // Generate some convenience envvars that will help us.
        if (isset($this->environmentVariables['HTTP_HOST'])) {
            $this->environmentVariables['HTTP_FQDN'] =
                ($this->environmentVariables['SERVER_PORT'] == 443 ? 'https://' : 'http://') .
                $this->environmentVariables['HTTP_HOST'] .
                (!in_array($this->environmentVariables['SERVER_PORT'], [80, 443]) ? ':' . $this->environmentVariables['SERVER_PORT'] : '') .
                "/";
        }
        foreach (['argv', 'argc'] as $unsettable) {
            unset($this->environmentVariables[$unsettable]);
        }
    }

    public function clearCache()
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        return $this;
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
     * @param string|array $var
     * @param string|int|bool $default
     *
     * @return string|int|bool
     */
    public function get($var, $default = false)
    {
        if (is_string($var)) {
            return $this->isSet($var) ? $this->environmentVariables[$var] : $default;
        } elseif (is_array($var)) {
            foreach ($var as $option) {
                if ($this->isSet($option)) {
                    return $this->get($option);
                }
            }
        }
        return $default;
    }


    public function __toArray()
    {
        ksort($this->environmentVariables);
        return $this->environmentVariables;
    }
}
