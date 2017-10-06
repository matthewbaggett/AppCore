<?php
namespace Segura\AppCore\Commands;

use Segura\AppCore\App;
use Segura\AppCore\Services\AutoImporterService;
use Zenderator\Automize;
use Zenderator\Zenderator;

class DataRevertCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {
        $rootOfApp = '/app';
        /** @var AutoImporterService $autoImporter */
        $autoImporter = App::Container()->get(AutoImporterService::class);
        $config       = Zenderator::getConfig($rootOfApp);
        $autoImporter->addSqlPath($rootOfApp . "/vendor/segura/appcore/src/SQL");
        if (isset($config['sql'])) {
            foreach ($config['sql'] as $location) {
                $autoImporter->addSqlPath($rootOfApp . "/" . $location);
            }
        }
        $autoImporter->purge();
        echo "\n\n";
        $autoImporter->run();
        echo "\n\n";
        $this->getZenderator()->waitForKeypress();
        return true;
    }
}
