<?php
namespace Segura\AppCore\Commands;

use Segura\AppCore\App;
use Segura\AppCore\Services\AutoImporterService;
use Zenderator\Automize;

class DataMigrateCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {
        /** @var AutoImporterService $autoImporter */
        $autoImporter = App::Container()->get(AutoImporterService::class);
        $autoImporter->run();
        echo "\n\n";
        $this->getZenderator()->waitForKeypress();
        return true;
    }
}
