<?php
namespace Segura\AppCore\Commands;

use Segura\AppCore\App;
use Segura\AppCore\Services\AutoImporterService;
use Segura\AppCore\ZenderatorConfig;
use Zenderator\Automize;

class WaitForDatabaseCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {

        echo "Database Up\n\n";
        return true;
    }
}
