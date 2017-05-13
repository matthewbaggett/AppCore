<?php
namespace Segura\AppCore\Commands;

use Segura\AppCore\App;
use Segura\AppCore\Services\AutoImporterService;
use Zenderator\Automize;

class DataRevertCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {
        $autoImporter = App::Container()->get(AutoImporterService::class);
        $autoImporter->purge();
        echo "\n\n";
        $autoImporter->run();
        echo "\n\n";
        $this->getZenderator()->waitForKeypress();
        return true;
    }
}
