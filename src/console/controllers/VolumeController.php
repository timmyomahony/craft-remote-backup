<?php

namespace weareferal\remotebackup\console\controllers;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;
use yii\console\ExitCode;

use weareferal\remotebackup\RemoteBackup;

/**
 * Manage remote volume backups
 */
class VolumeController extends Controller
{
    public function requirePluginEnabled()
    {
        if (!RemoteBackup::getInstance()->getSettings()->enabled) {
            throw new \Exception('Remote Backup Plugin not enabled');
        }
    }

    public function requirePluginConfigured()
    {
        if (!RemoteBackup::getInstance()->remotebackup->isConfigured()) {
            throw new \Exception('Remote Backup Plugin not correctly configured');
        }
    }

    /**
     * List remote volumes backups
     */
    public function actionList()
    {
        try {
            $this->requirePluginEnabled();
            $this->requirePluginConfigured();

            $results = RemoteBackup::getInstance()->remotebackup->listVolumes();
            if (count($results) <= 0) {
                $this->stdout("No remote volume backups" . PHP_EOL, Console::FG_YELLOW);
            } else {
                $this->stdout("Remote volume backups:" . PHP_EOL, Console::FG_GREEN);
                foreach ($results as $result) {
                    $this->stdout(" " . $result['value'] . PHP_EOL);
                }
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Create a remote volumes backup
     */
    public function actionCreate()
    {
        try {
            $this->requirePluginEnabled();
            $this->requirePluginConfigured();

            $filename = RemoteBackup::getInstance()->remotebackup->pushVolumes();
            $this->stdout("Created remote volumes backup:" . PHP_EOL, Console::FG_GREEN);
            $this->stdout(" " . $filename . PHP_EOL);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
        return ExitCode::OK;
    }

    /**
     * Delete old remote volume backups
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
                $filenames = RemoteBackup::getInstance()->remotebackup->pruneVolumes();
                if (count($filenames) <= 0) {
                    $this->stdout("No volume backups deleted" . PHP_EOL, Console::FG_YELLOW);
                } else {
                    $this->stdout("Deleted volume backups:" . PHP_EOL, Console::FG_GREEN);
                    foreach ($filenames as $filename) {
                        $this->stdout(" " . $filename . PHP_EOL);
                    }
                }
                return ExitCode::OK;
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }
}
