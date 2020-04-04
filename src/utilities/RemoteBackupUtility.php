<?php

namespace weareferal\remotebackup\utilities;

use Craft;
use craft\base\Utility;

use weareferal\remotebackup\assets\remotebackuputility\RemoteBackupUtilityAsset;
use weareferal\remotebackup\RemoteBackup;

class RemoteBackupUtility extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('app', 'Remote Backup');
    }

    public static function id(): string
    {
        return 'remote-backup';
    }

    public static function iconPath()
    {
        return RemoteBackup::getInstance()->getBasePath() . DIRECTORY_SEPARATOR . 'utility-icon.svg';
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(RemoteBackupUtilityAsset::class);
        $view->registerJs("new Craft.RemoteBackupUtility('rb-utilities-database')");
        $view->registerJs("new Craft.RemoteBackupUtility('rb-utilities-volumes')");

        return $view->renderTemplate('remote-backup/utilities/remote-backup', [
            "settingConfigured" => RemoteBackup::getInstance()->getSettings()->isConfigured(),
            "volumes" => Craft::$app->getVolumes()->getAllVolumes(),
        ]);
    }
}
