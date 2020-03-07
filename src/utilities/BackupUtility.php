<?php

namespace weareferal\backup\utilities;

use Craft;
use craft\base\Utility;

use weareferal\backup\assets\BackupUtilityAsset;
use weareferal\backup\Backup;

class BackupUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Backup');
    }

    public static function id(): string
    {
        return 'backup';
    }

    public static function iconPath()
    {
        return Backup::getInstance()->getBasePath() . DIRECTORY_SEPARATOR . 'utility-icon.svg';
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(BackupUtilityAsset::class);
        $forms = [
            ['create-database-backup', true],
            ['create-volumes-backup', true],
            ['push-database', false],
            ['push-volumes', false],
            ['pull-database', true],
            ['pull-volumes', true],
            ['restore-database-backup', false],
            ['restore-volumes-backup', false]
        ];
        foreach ($forms as $form) {
            $view->registerJs("new Craft.BackupUtility('" . $form[0] . "', " . $form[1] . ");");
        }

        $dbBackupOptions = Backup::getInstance()->backup->getDbBackupOptions();
        $volumeBackupOptions = Backup::getInstance()->backup->getVolumeBackupOptions();

        return $view->renderTemplate('backup/_components/utilities/sync', [
            "settingConfigured" => Backup::getInstance()->getSettings()->isConfigured(),
            "dbBackupOptions" => $dbBackupOptions,
            "volumes" => Craft::$app->getVolumes()->getAllVolumes(),
            "volumeBackupOptions" => $volumeBackupOptions
        ]);
    }
}
