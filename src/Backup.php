<?php

/**
 * Craft Backup plugin for Craft CMS 3.x
 *
 * Backup your assets and database offsite
 *
 * @link      https://weareferal.com
 * @copyright Copyright (c) 2020 Timmy O'Mahony
 */

namespace weareferal\backup;

use Craft;
use craft\base\Plugin;
use craft\services\Utilities;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;

use yii\base\Event;

use weareferal\backup\utilities\BackupUtility;
use weareferal\backup\models\Settings;
use weareferal\backup\services\BackupService;
use weareferal\backup\assets\BackupSettingAsset;


class Backup extends Plugin
{
    public $hasCpSettings = true;

    public static $plugin;

    public $schemaVersion = '1.0.0';

    public function init()
    {
        parent::init();

        self::$plugin = $this;

        $this->setComponents([
            'backup' => BackupService::create($this->getSettings()->cloudProvider)
        ]);

        // Register console commands
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'weareferal\backup\console\controllers';
        }

        // Register permissions
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions['Backup'] = [
                    'backup' => [
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
                $event->types[] = BackupUtility::class;
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
        $view->registerAssetBundle(BackupSettingAsset::class);
        $view->registerJs("new Craft.BackupSettings('main-form');");
        return $view->renderTemplate(
            'backup/settings',
            [
                'settings' => $this->getSettings()
            ]
        );
    }
}
