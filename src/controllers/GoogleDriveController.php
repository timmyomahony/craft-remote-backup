<?php

namespace weareferal\remotebackup\controllers;

use weareferal\remotecore\controllers\BaseGoogleDriveController;
use weareferal\remotebackup\RemoteBackup;


/**
 * Google Drive controller
 * 
 */
class GoogleDriveController extends BaseGoogleDriveController
{
    protected function pluginInstance() {
        return RemoteBackup::getInstance();
    }
}
