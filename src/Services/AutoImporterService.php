<?php

namespace Segura\AppCore\Services;

use Segura\AppCore\App;
use Segura\AppCore\Exceptions\AutoImporterException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Adapter\Driver\Pdo\Result;

class AutoImporterService
{

    /** @var UpdaterService */
    private $updaterService;

    private $sqlPaths = [];

    public function __construct(UpdaterService $updaterService)
    {
        $this->updaterService = $updaterService;
    }

    public function addSqlPath($sqlPath)
    {
        if (file_exists($sqlPath)) {
            $this->sqlPaths[] = $sqlPath;
            return $this;
        } else {
            throw new AutoImporterException("Cannot find path {$sqlPath}");
        }
    }

    public function scanForSql($path)
    {
        $results = [];
        foreach (new \DirectoryIterator($path) as $file) {
            if (!$file->isDot()) {
                if ($file->isDir()) {
                    $results = array_merge($results, $this->scanForSql($file->getRealPath()));
                } elseif ($file->getExtension() == 'sql') {
                    $results[$file->getRealPath()] = $file->getRealPath();
                }
            }
        }
        ksort($results);
        return $results;
    }

    public function applyScripts($sqlFiles)
    {
        if (is_array($sqlFiles)) {
            foreach ($sqlFiles as $sqlFile) {
                $this->applyScript($sqlFile);
            }
        } else {
            $this->applyScript($sqlFiles);
        }
    }
    public function applyScript($sqlFile)
    {
        echo " > Running {$sqlFile}...";
        try {
            $alreadyApplied = $this->updaterService->updateAlreadyApplied($sqlFile);
        } catch (\Exception $exception) {
            $alreadyApplied = false;
            if (stripos($exception->getMessage(), "42S02") !== false) {
                $this->runFile(APPCORE_ROOT . "/src/SQL/create_updates_table.sql");
            }
        }

        if (!$alreadyApplied) {
            $this->runFile($sqlFile);
            $update = $this->updaterService->getNewModelInstance();
            $update
                ->setFile($sqlFile)
                ->setDateApplied(date("Y-m-d H:i:s"))
                ->save();
            echo " [DONE]\n";
        } else {
            echo " [SKIPPED]\n";
        }
    }

    private function runFile($sqlFile)
    {
        $configs = App::Instance(false)->getContainer()->get("DatabaseConfig");
        if(isset($configs['Default'])) {
            $connection = $configs['Default'];
        }else{
            $connection = reset($configs);
        }

        $importCommand = "mysql -u {$connection['username']} -h {$connection['hostname']} -p{$connection['password']} {$connection['database']} < {$sqlFile}  2>&1 | grep -v \"Warning: Using a password\"";
        ob_start();
        exec($importCommand);
        ob_end_clean();
    }

    public function run()
    {
        App::waitForMySQLToBeReady();
        echo "Checking for SQL to run:\n";
        foreach ($this->sqlPaths as $sqlPath) {
            echo " > Looking in {$sqlPath}\n";
        }
        echo "Running found scripts:\n";
        foreach ($this->sqlPaths as $sqlPath) {
            foreach ($this->scanForSql($sqlPath) as $file) {
                $this->applyScripts($file);
            }
        }
        echo "Complete.\n\n";
    }

    public function purge()
    {
        $db = App::Instance(false)->getContainer()->get('DatabaseInstance');
        /** @var Adapter $database */
        $databases = $db->getDatabases();
        $database  = reset($databases);
        $sqlDoer   = $database->driver->getConnection();

        /** @var Result $tables */
        $tablesResult = $sqlDoer->execute("SHOW TABLES");
        $tables       = [];
        while ($row = $tablesResult->next()) {
            $tables[] = reset($row);
        }
        foreach ($tables as $table) {
            $sql   = [];
            $sql[] = 'SET FOREIGN_KEY_CHECKS = 0';
            $sql[] = "DROP TABLES `{$table}`";
            $sql[] = 'SET FOREIGN_KEY_CHECKS = 1';
            $sql   = implode(";\n", $sql);
            echo " > Dropping {$table}\n";
            $sqlDoer->execute($sql);
        }
    }
}
