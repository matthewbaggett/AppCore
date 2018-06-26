<?php
namespace Segura\AppCore;

class ZenderatorConfig
{
    public static function getConfig($rootOfApp = APP_ROOT)
    {
        if (file_exists($rootOfApp . "/zenderator.yml")) {
            $zenderatorConfigPath = $rootOfApp . "/zenderator.yml";
        } elseif (file_exists($rootOfApp . "/zenderator.yml.dist")) {
            $zenderatorConfigPath = $rootOfApp . "/zenderator.yml.dist";
        } else {
            die("Missing Zenderator config /zenderator.yml or /zenderator.yml.dist\nThere is an example in /vendor/bin/segura/zenderator/zenderator.example.yml\n\n");
        }

        $config = file_get_contents($zenderatorConfigPath);
        $config = \Symfony\Component\Yaml\Yaml::parse($config);
        return $config;
    }
}
