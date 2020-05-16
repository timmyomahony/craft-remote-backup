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
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\UrlManager;
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
                $event->permissions['Remote Backup'] = [
                    'remotebackup' => [
                        'label' => 'Create remote backups of database and assets',
                    ],
                ];
            }
        );

        // Register with Utilities service
        if ($this->getSettings()->enabled) {
            Event::on(
                Utilities::class,
                Utilities::EVENT_REGISTER_UTILITY_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = RemoteBackupUtility::class;
                }
            );
        }

        // Extra urls
        if ($this->getSettings()->cloudProvider == "google") {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                function (RegisterUrlRulesEvent $event) {
                    $event->rules['remote-backup/google-drive/auth'] = 'remote-backup/google-drive/auth';
                    $event->rules['remote-backup/google-drive/auth-redirect'] = 'remote-backup/google-drive/auth-redirect';
                }
            );
        }
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

        $isAuthenticated = $this->remotebackup->isAuthenticated();
        $isConfigured = $this->remotebackup->isConfigured();
        return $view->renderTemplate(
            'remote-backup/settings',
            [
                'settings' => $this->getSettings(),
                'isConfigured' => $isConfigured,
                'isAuthenticated' => $isAuthenticated
            ]
        );
    }
}
