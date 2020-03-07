<?php

namespace weareferal\backup\controllers;

use Craft;
use craft\web\Controller;

use weareferal\backup\Backup;
use weareferal\backup\queue\CreateDatabaseBackupJob;
use weareferal\backup\queue\CreateVolumeBackupJob;
use weareferal\backup\queue\PruneDatabaseBackupsJob;
use weareferal\backup\queue\PruneVolumeBackupsJob;
use weareferal\backup\queue\PullDatabaseBackupsJob;
use weareferal\backup\queue\PullVolumeBackupsJob;
use weareferal\backup\queue\PushDatabaseBackupsJob;
use weareferal\backup\queue\PushVolumeBackupsJob;
use weareferal\backup\exceptions\ProviderException;


class BackupController extends Controller
{
    public function actionCreateDatabaseBackup()
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('sync');

        try {
            $prune = Backup::getInstance()->getSettings()->prune;
            $useQueue = Backup::getInstance()->getSettings()->useQueue;
            if ($prune) {
                if ($useQueue) {
                    Craft::$app->queue->push(new PruneDatabaseBackupsJob());
                } else {
                    Backup::getInstance()->backup->pruneDatabaseBackups();
                }
            }
            if ($useQueue) {
                Craft::$app->queue->push(new CreateDatabaseBackupJob());
            } else {
                Backup::getInstance()->backup->createDatabaseBackup();
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('backup', 'Error creating database backup'));
        }

        return $this->asJson([
            "success" => true
        ]);
    }

    public function actionCreateVolumesBackup()
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('sync');

        try {
            $prune = Backup::getInstance()->getSettings()->prune;
            $useQueue = Backup::getInstance()->getSettings()->useQueue;
            if ($prune) {
                if ($useQueue) {
                    Craft::$app->queue->push(new PruneVolumeBackupsJob());
                } else {
                    Backup::getInstance()->backup->pruneVolumeBackups();
                }
            }
            if ($useQueue) {
                Craft::$app->queue->push(new CreateVolumeBackupJob());
            } else {
                Backup::getInstance()->backup->createVolumeBackup();
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('backup', 'Error creating volume backup'));
        }

        return $this->asJson([
            "success" => true
        ]);
    }
}
