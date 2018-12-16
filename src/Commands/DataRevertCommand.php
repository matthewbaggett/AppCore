<?php
namespace Gone\AppCore\Commands;

use Gone\AppCore\App;
use Gone\AppCore\Services\AutoImporterService;
use Gone\AppCore\ZenderatorConfig;
use Zenderator\Automize;

class DataRevertCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {
        $rootOfApp = '/app';
        /** @var AutoImporterService $autoImporter */
        $autoImporter = App::Container()->get(AutoImporterService::class);
        $config       = ZenderatorConfig::getConfig($rootOfApp);
        $autoImporter->addSqlPath($rootOfApp . "/vendor/gone.io/appcore/src/SQL");
        if (isset($config['sql'])) {
            foreach ($config['sql'] as $location) {
                $autoImporter->addSqlPath($rootOfApp . "/" . $location);
            }
        }
        $autoImporter->purge();
        echo "\n\n";
        $autoImporter->run();
        echo "\n\n";
        return true;
    }
}
