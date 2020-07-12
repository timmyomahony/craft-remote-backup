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
use craft\web\UrlManager;
use craft\services\Utilities;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\UserPermissions;

use yii\base\Event;

use weareferal\remotebackup\utilities\RemoteBackupUtility;
use weareferal\remotebackup\models\Settings;
use weareferal\remotebackup\services\PruneService;

use weareferal\remotecore\RemoteCoreHelper;
use weareferal\remotecore\assets\remotecoresettings\RemoteCoreSettingsAsset;


class RemoteBackup extends Plugin
{
    public $hasCpSettings = true;

    public static $plugin;

    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();
        self::$plugin = $this;

        RemoteCoreHelper::registerModule();

        $this->registerServices();
        $this->registerURLs();
        $this->registerConsoleControllers();
        $this->registerPermissions();
        $this->registerUtilties();
    }

    /**
     * Register Permissions
     * 
     */
    public function registerPermissions()
    {
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
    }

    /**
     * Register URLs
     * 
     */
    public function registerURLs()
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['remote-backup/google-drive/auth'] = 'remote-backup/google-drive/auth';
                $event->rules['remote-backup/google-drive/auth-redirect'] = 'remote-backup/google-drive/auth-redirect';
            }
        );
    }

    /**
     * Register Console Controllers
     * 
     */
    public function registerConsoleControllers()
    {
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'weareferal\remotebackup\console\controllers';
        }
    }

    /**
     * Register Services
     * 
     */
    public function registerServices()
    {
        Craft::debug('Registering services for: ' . $this->name, 'remote-backup');
        Craft::debug('Cloud provider setting: ' . $this->getSettings()->cloudProvider, 'remote-backup');
        $provider = Craft::$app->getModule('remote-core')->providerFactory->create($this);
        Craft::debug('Provider: ' . $provider->name, 'remote-backup');
        $this->setComponents([
            'provider' => $provider,
            'prune' => PruneService::class
        ]);
    }

    /**
     * Register Utilities
     * 
     */
    public function registerUtilties()
    {
        if ($this->getSettings()->enabled) {
            Event::on(
                Utilities::class,
                Utilities::EVENT_REGISTER_UTILITY_TYPES,
                function (RegisterComponentTypesEvent $event) {
                    $event->types[] = RemoteBackupUtility::class;
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
        $view->registerAssetBundle(RemoteCoreSettingsAsset::class);
        $view->registerJs("new Craft.RemoteCoreSettings('main-form');");

        $isAuthenticated = $this->provider->isAuthenticated();
        $isConfigured = $this->provider->isConfigured();

        return $view->renderTemplate(
            'remote-backup/settings',
            [
                'plugin' => $this,
                'settings' => $this->getSettings(),
                'isConfigured' => $isConfigured,
                'isAuthenticated' => $isAuthenticated
            ]
        );
    }
}
