<?php
namespace Gone\AppCore\Commands;

use Zenderator\Automize;

class WaitForDatabaseCommand extends Automize\AutomizeCommand implements Automize\AutomizeCommandInterface
{
    public function action() : bool
    {
        echo "Database Up\n\n";
        return true;
    }
}
