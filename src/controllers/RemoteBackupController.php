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
                "backups" => RemoteBackup::getInstance()->remotebackup->listDatabaseBackups(),
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

        $settings = RemoteBackup::getInstance()->getSettings();
        $service = RemoteBackup::getInstance()->remotebackup;
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateDatabaseBackupJob());
            } else {
                $service->createDatabaseBackup();
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneDatabaseBackupsJob());
                } else {
                    $service->pruneDatabaseBackups();
                }
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
                "backups" => RemoteBackup::getInstance()->remotebackup->listVolumeBackups(),
                "success" => true
            ]);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error getting remote volume backups'));
        }
    }

    public function actionCreateVolumeBackup()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');

        $settings = RemoteBackup::getInstance()->getSettings();
        $service = RemoteBackup::getInstance()->remotebackup;
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateVolumeBackupJob());
            } else {
                $service->createVolumeBackup();
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneVolumeBackupsJob());
                } else {
                    $service->pruneVolumeBackups();
                }
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
