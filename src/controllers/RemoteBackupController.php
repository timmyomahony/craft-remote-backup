<?php

namespace weareferal\remotebackup\controllers;

use yii\web\BadRequestHttpException;

use Craft;
use craft\web\Controller;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\queue\CreateDatabaseBackupJob;
use weareferal\remotebackup\queue\CreateVolumeBackupJob;
use weareferal\remotebackup\queue\PruneDatabaseBackupsJob;
use weareferal\remotebackup\queue\PruneVolumeBackupsJob;


class RemoteBackupController extends Controller
{
    public function requirePluginEnabled()
    {
        if (!RemoteBackup::getInstance()->getSettings()->enabled) {
            throw new BadRequestHttpException('Plugin is not enabled');
        }
    }

    public function requirePluginConfigured()
    {
        if (!RemoteBackup::getInstance()->getSettings()->configured()) {
            throw new BadRequestHttpException('Plugin is not correctly configured');
        }
    }

    /**
     * List database backups
     * 
     */
    public function actionListDatabases()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        $this->requirePluginConfigured();

        try {
            return $this->asJson([
                "backups" => RemoteBackup::getInstance()->remotebackup->listDatabases(),
                "success" => true
            ]);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error getting remote database backups'));
        }
    }

    public function actionListVolumes()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        $this->requirePluginConfigured();

        try {
            return $this->asJson([
                "backups" => RemoteBackup::getInstance()->remotebackup->listVolumes(),
                "success" => true
            ]);
        } catch (\Exception $e) {
            Craft::$app->getErrorHandler()->logException($e);
            return $this->asErrorJson(Craft::t('remote-backup', 'Error getting remote volume backups'));
        }
    }

    public function actionPushDatabase()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        $this->requirePluginConfigured();

        $settings = RemoteBackup::getInstance()->getSettings();
        $service = RemoteBackup::getInstance()->remotebackup;
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateDatabaseBackupJob());
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Job added to queue'));
            } else {
                $service->pushDatabase();
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Database backup created'));
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneDatabaseBackupsJob());
                } else {
                    $service->pruneDatabases();
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

    public function actionPushVolumes()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        $this->requirePluginConfigured();

        $settings = RemoteBackup::getInstance()->getSettings();
        $service = RemoteBackup::getInstance()->remotebackup;
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateVolumeBackupJob());
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Job added to queue'));
            } else {
                $service->pushVolumes();
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Volume backup created'));
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneVolumeBackupsJob());
                } else {
                    $service->pruneVolumes();
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
