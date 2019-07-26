<?php
namespace Gone\AppCore\Services;

use Symfony\Component\Yaml\Yaml;

class EnvironmentService
{

    /** @var array */
    protected $environmentVariables;

    protected $cacheFile = "/tmp/.configcache.yml";

    public function __construct()
    {
        $this->rebuildEnvironmentVariables();
    }


    public function __toArray()
    {
        ksort($this->environmentVariables);
        return $this->environmentVariables;
    }

    public function rebuildEnvironmentVariables()
    {
        if (file_exists($this->cacheFile) && php_sapi_name() != 'cli') {
            $this->environmentVariables = Yaml::parse(file_get_contents($this->cacheFile));
        } else {
            foreach (array_merge($_SERVER, $_ENV) as $key => $value) {
                $this->environmentVariables[$key] = $value;
            }

            ksort($this->environmentVariables);

            if (php_sapi_name() != 'cli') {
                file_put_contents($this->cacheFile, Yaml::dump($this->environmentVariables));
                chmod($this->cacheFile, 0777);
            }
        }

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

        ksort($this->environmentVariables);

        return $this->environmentVariables;
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
     * @param string|array    $key
     * @param string|int|bool $default
     *
     * @return string|int|bool
     */
    public function get($key, $default = false)
    {
        if (is_string($key)) {
            return $this->isSet($key) ? $this->environmentVariables[$key] : $default;
        } elseif (is_array($key)) {
            foreach ($key as $option) {
                if ($this->isSet($option)) {
                    return $this->get($option);
                }
            }
        }
        return $default;
    }
    
    public function set($key, $value) : EnvironmentService
    {
        $this->environmentVariables[$key] = $value;
        return $this;
    }
}
