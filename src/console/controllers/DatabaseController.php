<?php

namespace weareferal\remotebackup\console\controllers;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;
use yii\console\ExitCode;

use weareferal\remotebackup\RemoteBackup;

/**
 * Manage remote database backups
 */
class DatabaseController extends Controller
{
    public function requirePluginEnabled()
    {
        if (!RemoteBackup::getInstance()->getSettings()->enabled) {
            throw new \Exception('Remote Backup Plugin not enabled');
        }
    }

    public function requirePluginConfigured()
    {
        if (!RemoteBackup::getInstance()->provider->isConfigured()) {
            throw new \Exception('Remote Backup Plugin not correctly configured');
        }
    }

    /**
     * List remote database backups
     */
    public function actionList()
    {
        try {
            $this->requirePluginEnabled();
            $this->requirePluginConfigured();
            $startTime = microtime(true);
            $remoteFiles = RemoteBackup::getInstance()->provider->listDatabases();
            if (count($remoteFiles) <= 0) {
                $this->stdout("No remote database backups" . PHP_EOL, Console::FG_YELLOW);
            } else {
                $this->stdout("Remote database backups:" . PHP_EOL, Console::FG_GREEN);
                foreach ($remoteFiles as $result) {
                    $this->stdout("- " . $result->filename . PHP_EOL);
                }
            }
            $this->printTime($startTime);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Create a remote database backup
     */
    public function actionCreate()
    {
        try {
            $this->requirePluginEnabled();
            $this->requirePluginConfigured();
            $startTime = microtime(true);
            $filename = RemoteBackup::getInstance()->provider->pushDatabase();
            $this->stdout("Created remote database backup:" . PHP_EOL, Console::FG_GREEN);
            $this->stdout("- " . $filename . PHP_EOL);
            $this->printTime($startTime);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Delete old remote database backups
     */
    public function actionPrune()
    {
        try {
            $this->requirePluginEnabled();
            $this->requirePluginConfigured();
            if (!RemoteBackup::getInstance()->getSettings()->prune) {
                $this->stderr("Backup pruning disabled. Please enable via the Remote Backup control panel settings" . PHP_EOL, Console::FG_YELLOW);
                return ExitCode::CONFIG;
            } else {
                $startTime = microtime(true); 
                $filenames = RemoteBackup::getInstance()->prune->pruneDatabases();
                if (count($filenames) <= 0) {
                    $this->stdout("No databases backups deleted" . PHP_EOL, Console::FG_YELLOW);
                } else {
                    $this->stdout("Deleted database backups:" . PHP_EOL, Console::FG_GREEN);
                    foreach ($filenames as $filename) {
                        $this->stdout("- " . $filename . PHP_EOL);
                    }
                    $this->printTime($startTime);
                }
                return ExitCode::OK;
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    protected function printTime($startTime) {
        $this->stdout("Started at " . (string) date("Y-m-d H:i:s") .  ". Duration " . (string) number_format(microtime(true) - $startTime, 2)  . " seconds" . PHP_EOL);
    }
}
