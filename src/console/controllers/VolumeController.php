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
    /**
     * Create a remote database backup
     */
    public function actionCreate()
    {
        try {
            $path = RemoteBackup::getInstance()->remotebackup->createVolumeBackup();
            if ($path) {
                $this->stdout("Created remote volume backup: " . $path . PHP_EOL, Console::FG_GREEN);
            } else {
                $this->stdout("No volumes to backup" . PHP_EOL, Console::FG_YELLOW);
            }
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
        if (!RemoteBackup::getInstance()->getSettings()->prune) {
            $this->stderr("Backup pruning disabled. Please enable via the Remote Backup control panel settings" . PHP_EOL, Console::FG_YELLOW);
            return ExitCode::CONFIG;
        } else {
            try {
                $results = RemoteBackup::getInstance()->remotebackup->pruneVolumeBackups();
                if (!$results['deleted'] || count($results['deleted']) <= 0) {
                    $this->stdout('No backups deleted' . PHP_EOL, Console::FG_YELLOW);
                }
                foreach ($results['deleted'] as $path) {
                    $this->stdout('Successfully deleted "' . $path . '"' . PHP_EOL, Console::FG_GREEN);
                }
                foreach ($results['failed'] as $path) {
                    $this->stdout('Couldn\'nt delete "' . $path . '"' . PHP_EOL, Console::FG_YELLOW);
                }
            } catch (\Exception $e) {
                Craft::$app->getErrorHandler()->logException($e);
                $this->stderr('Error: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                return ExitCode::UNSPECIFIED_ERROR;
            }
            return ExitCode::OK;
        }
    }
}
