<?php

/**
 * Craft Remote Backup plugin for Craft CMS 3.x
 *
 * @link      https://weareferal.com
 * @copyright Copyright (c) 2020 Timmy O'Mahony
 */

namespace weareferal\remotebackup;

use Craft;
use craft\base\Plugin;
use craft\services\Utilities;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;

use yii\base\Event;

use weareferal\remotebackup\utilities\RemoteBackupUtility;
use weareferal\remotebackup\models\Settings;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\assets\remotebackupsettings\RemoteBackupSettingAsset;


class RemoteBackup extends Plugin
{
    public $hasCpSettings = true;

    public static $plugin;

    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'remotebackup' => RemoteBackupService::create($this->getSettings()->cloudProvider)
        ]);

        // Register console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'weareferal\remotebackup\console\controllers';
        }

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions['RemoteBackup'] = [
                    'remotebackup' => [
                        'label' => 'Backup database and assets',
                    ],
                ];
            }
        );

        // Register with Utilities service
        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITY_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = RemoteBackupUtility::class;
            }
        );
    }

    protected function createSettingsModel(): Settings
    {
        return new Settings();
    }

    protected function settingsHtml(): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(RemoteBackupSettingAsset::class);
        $view->registerJs("new Craft.RemoteBackupSettings('main-form');");
        return $view->renderTemplate(
            'remote-backup/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
