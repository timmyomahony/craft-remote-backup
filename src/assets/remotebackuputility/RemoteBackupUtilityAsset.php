<?php

namespace weareferal\remotebackup\assets\remotebackuputility;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteBackupUtilityAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/RemoteBackupUtility.js'
        ];

        parent::init();
    }
}
