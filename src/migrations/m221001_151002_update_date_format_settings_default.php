<?php

namespace weareferal\remotebackup\migrations;

use Craft;
use craft\db\Migration;

/**
 * m221001_151002_update_date_format_settings_default migration.
 */
class m221001_151002_update_date_format_settings_default extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // If the existing remote backup date format setting remains the default
        // setting from previous versions, then update it.
        $remoteBackup = Craft::$app->plugins->getPlugin("remote-backup");
        $settings = $remoteBackup->getSettings();
        if ($settings->displayDateFormat == "Y-m-d H:i:s") {
            Craft::$app->getPlugins()->savePluginSettings($remoteBackup, [
                "displayDateFormat" => "Y-m-d"
            ]);
        }
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        return true;
    }
}
