<?php

namespace weareferal\remotebackup\assets\remotebackupsettings;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteBackupSettingAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/RemoteBackupSetting.js'
        ];

        parent::init();
    }
}
