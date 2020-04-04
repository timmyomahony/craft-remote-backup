<?php

namespace weareferal\remotebackup\controllers;

use Craft;
use craft\web\Controller;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\queue\CreateDatabaseBackupJob;
use weareferal\remotebackup\queue\CreateVolumeBackupJob;
use weareferal\remotebackup\queue\PruneDatabaseBackupsJob;
use weareferal\remotebackup\queue\PruneVolumeBackupsJob;


class RemoteBackupController extends Controller
{
    /**
     * List database backups
     * 
     */
    public function actionListDatabaseBackups()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');

        try {
            return $this->asJson([
                "backups" => RemoteBackup::getInstance()->remotebackup->getDatabaseBackups(),
                "success" => true
            ]);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error getting remote database backups'));
        }
    }

    public function actionCreateDatabaseBackup()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');

        try {
            // $prune = RemoteBackup::getInstance()->getSettings()->prune;
            $useQueue = RemoteBackup::getInstance()->getSettings()->useQueue;
            // if ($prune) {
            //     if ($useQueue) {
            //         Craft::$app->queue->push(new PruneDatabaseBackupsJob());
            //     } else {
            //         RemoteBackup::getInstance()->remotebackup->pruneDatabaseBackups();
            //     }
            // }
            if ($useQueue) {
                Craft::$app->queue->push(new CreateDatabaseBackupJob());
            } else {
                RemoteBackup::getInstance()->remotebackup->createDatabaseBackup();
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error creating database backup'));
        }

        return $this->asJson([
            "success" => true
        ]);
    }

    public function actionListVolumeBackups()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');

        try {
            return $this->asJson([
                "backups" => RemoteBackup::getInstance()->remotebackup->getVolumeBackups(),
                "success" => true
            ]);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error getting remote volume backups'));
        }
    }

    public function actionCreateVolumeBackup()
    {
        $this->requirePostRequest();
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');

        try {
            // $prune = RemoteBackup::getInstance()->getSettings()->prune;
            $useQueue = RemoteBackup::getInstance()->getSettings()->useQueue;
            // if ($prune) {
            //     if ($useQueue) {
            //         Craft::$app->queue->push(new PruneVolumeBackupsJob());
            //     } else {
            //         RemoteBackup::getInstance()->remotebackup->pruneVolumeBackups();
            //     }
            // }
            if ($useQueue) {
                Craft::$app->queue->push(new CreateVolumeBackupJob());
            } else {
                RemoteBackup::getInstance()->remotebackup->createVolumeBackup();
            }
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error creating volume backup'));
        }

        return $this->asJson([
            "success" => true
        ]);
    }
}
