<?php

namespace weareferal\remotebackup\assets\RemoteBackupUtility;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class RemoteBackupUtilityAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = '@weareferal/remotebackup/assets/RemoteBackupUtility/dist';

        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'js/RemoteBackupUtility.js'
        ];

        parent::init();
    }
}
