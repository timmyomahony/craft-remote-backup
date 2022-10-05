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

use weareferal\remotecore\helpers\RemoteFile;


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
        if (!RemoteBackup::getInstance()->provider->isConfigured()) {
            throw new BadRequestHttpException('Plugin is not correctly configured');
        }
    }

    /**
     * Test Provider
     * 
     * 
     * @since 4.1.0
     */
    public function actionTestProvider()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        
        $plugin = RemoteBackup::getInstance();

        try {
            // Simply attempt to list all files
            $plugin->provider->list('.sql');
            return $this->asJson([
                "success" => true
            ]);
        } catch (\Exception $e) {
            return $this->asFailure(
                Craft::t('remote-backup', 'Test failed'),
                [
                    "message" => $e->getMessage(),
                    "trace" => $e->getTraceAsString()
                ]);
        }
    }
    public function actionListDatabases()
    {
        $this->requireCpRequest();
        $this->requirePermission('remotebackup');
        $this->requirePluginEnabled();
        $this->requirePluginConfigured();

        $plugin = RemoteBackup::getInstance();
        $settings = $plugin->getSettings();

        try {
            $remoteFiles = RemoteBackup::getInstance()->provider->listDatabases();
            $files = RemoteFile::serialize($remoteFiles, $settings->displayDateFormat);
            return $this->asJson([
                "files" => $files,
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
        
        $plugin = RemoteBackup::getInstance();
        $settings = $plugin->getSettings();

        try {
            $remoteFiles = RemoteBackup::getInstance()->provider->listVolumes();
            $files = RemoteFile::serialize($remoteFiles, $settings->displayDateFormat);
            return $this->asJson([
                "files" => $files,
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

        $plugin = RemoteBackup::getInstance();
        $settings = $plugin->getSettings();
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateDatabaseBackupJob());
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Job added to queue'));
            } else {
                $plugin->provider->pushDatabase();
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Database backup created'));
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneDatabaseBackupsJob());
                } else {
                    $plugin->prune->pruneDatabases();
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

        $plugin = RemoteBackup::getInstance();
        $settings = $plugin->getSettings();
        $queue = Craft::$app->queue;

        try {
            if ($settings->useQueue) {
                $queue->push(new CreateVolumeBackupJob());
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Job added to queue'));
            } else {
                $plugin->provider->pushVolumes();
                Craft::$app->getSession()->setNotice(Craft::t('remote-backup', 'Volume backup created'));
            }

            if ($settings->prune) {
                if ($settings->useQueue) {
                    $queue->push(new PruneVolumeBackupsJob());
                } else {
                    $plugin->prune->pruneVolumes();
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
