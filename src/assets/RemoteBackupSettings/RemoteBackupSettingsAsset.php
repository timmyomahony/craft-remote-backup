<?php

namespace weareferal\remotebackup\assets\RemoteBackupSettings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteBackupSettingsAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = "@weareferal/remotebackup/assets/RemoteBackupSettings/dist";

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/RemoteBackupSettings.js'
        ];

        $this->css = [
            'css/RemoteBackupSettings.css',
        ];

        parent::init();
    }
}
