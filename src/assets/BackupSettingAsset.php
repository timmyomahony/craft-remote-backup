<?php

namespace weareferal\backup\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;


class BackupSettingAsset extends AssetBundle
{
    public function init()
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [
            CpAsset::class,
        ];
        $this->js = [
            'BackupSetting.js'
        ];
        parent::init();
    }
}
