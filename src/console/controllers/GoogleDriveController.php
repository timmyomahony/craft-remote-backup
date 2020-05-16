<?php

namespace weareferal\remotebackup\console\controllers;

use Craft;
use yii\console\Controller;
use yii\helpers\Console;
use yii\console\ExitCode;

use weareferal\remotebackup\RemoteBackup;

/**
 * Manage Google Drive SDK
 * 
 */
class GoogleDriveController extends Controller
{
    /**
     * 
     */
    public function actionAuth()
    {
        $service = RemoteBackup::getInstance()->remotebackup;
        $settings = RemoteBackup::getInstance()->settings;
        $client = $service->getClient();
    }
}
