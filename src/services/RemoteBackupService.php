<?php

namespace weareferal\remotebackup\services;

use yii\base\Component;
use Craft;
use Craft\helpers\FileHelper;
use Craft\helpers\StringHelper;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\providers\S3Service;
use weareferal\remotebackup\helpers\ZipHelper;


interface Provider
{
    public function list($filterExtensions): array;
    public function push($path);
    public function pull($key, $path);
    public function delete($key);
}

class RemoteBackupInstance
{
    public $filename;
    public $datetime;
    public $label;
    public $env;

    // Regex to capture/match:
    // - Site name
    // - Environment (optional and captured)
    // - Date (required and captured)
    // - Random string
    // - Version
    // - Extension
    private static $regex = '/^(?:[a-zA-Z0-9\-]+)\_(?:([a-zA-Z0-9\-]+)\_)?(\d{6}\_\d{6})\_(?:[a-zA-Z0-9]+)\_(?:[v0-9\.]+)\.(?:\w{2,10})$/';

    public function __construct($_filename)
    {
        // Extract values from filename
        preg_match(RemoteBackupInstance::$regex, $_filename, $matches);
        $env = $matches[1];
        $date = $matches[2];
        $datetime = date_create_from_format('ymd_Gis', $date);
        $label = $datetime->format('Y-m-d H:i:s');
        if ($env) {
            $label = $label  . ' (' . $env . ')';
        }
        $this->filename = $_filename;
        $this->datetime = $datetime;
        $this->label = $label;
        $this->env = $env;
    }
}

class RemoteBackupService extends Component
{
    /**
     * Return the remote database backup filenames
     * 
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listDatabases(): array
    {
        $filenames = $this->list(".sql");
        $backups = $this->parseFilenames($filenames);
        $options = [];
        foreach ($backups as $i => $backup) {
            $options[$i] = [
                "label" => $backup->label,
                "value" => $backup->filename
            ];
        }
        return $options;
    }

    /**
     * Return the remote bolume backup filenames
     * 
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listVolumes(): array
    {
        $filenames = $this->list('.zip');
        $backups = $this->parseFilenames($filenames);
        $options = [];
        foreach ($backups as $i => $backup) {
            $options[$i] = [
                "label" => $backup->label,
                "value" => $backup->filename
            ];
        }
        return $options;
    }

    /**
     * Create a new Remote Sync of the database
     * 
     * @return string The filename of the newly created Remote Sync
     * @since 1.0.0
     */
    public function pushDatabase()
    {
        $dir = $this->getLocalDir();
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = $this->getFilename();
        $path = $dir . DIRECTORY_SEPARATOR . $filename . '.sql';
        Craft::$app->getDb()->backupTo($path);
        $this->push($path);
        if (!RemoteBackup::getInstance()->getSettings()->keepLocal) {
            unlink($path);
        }
        return $filename;
    }

    /**
     * Push all volumes
     * 
     * @return string The filename of the newly created Remote Sync
     * @return null If no volumes exist
     * @since 1.0.0
     */
    public function pushVolumes(): string
    {
        $dir = $this->getLocalDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $filename = $this->getFilename();
        $path = $dir . DIRECTORY_SEPARATOR . $filename . '.zip';
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        if (count($volumes) <= 0) {
            return null;
        }

        foreach ($volumes as $volume) {
            $tmpPath = $tmpDirName . DIRECTORY_SEPARATOR . $volume->handle;
            FileHelper::copyDirectory($volume->rootPath, $tmpPath);
        }

        ZipHelper::recursiveZip($tmpDirName, $path);
        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());

        $this->push($path);

        if (!RemoteBackup::getInstance()->getSettings()->keepLocal) {
            unlink($path);
        }

        return $filename;
    }

    /**
     * Return a unique filename for a backup file
     * 
     * Based on getBackupFilePath():
     * 
     * https://github.com/craftcms/cms/tree/master/src/db/Connection.php
     * 
     * @return string The unique backup filename
     */
    private function getFilename(): string
    {
        $currentVersion = 'v' . Craft::$app->getVersion();
        $systemName = FileHelper::sanitizeFilename(Craft::$app->getInfo()->name, ['asciiOnly' => true]);
        $systemEnv = Craft::$app->env;
        $filename = ($systemName ? $systemName . '_' : '') . ($systemEnv ? $systemEnv . '_' : '') . gmdate('ymd_His') . '_' . strtolower(StringHelper::randomString(10)) . '_' . $currentVersion;
        return mb_strtolower($filename);
    }

    /**
     * Return a chronologically sorted array of Backup objects
     * 
     * @param string[] Array of filenames
     * @return array[] Array of Backup objects
     */
    private function parseFilenames($filenames): array
    {
        $backups = [];

        foreach ($filenames as $filename) {
            array_push($backups, new RemoteBackupInstance($filename));
        }

        uasort($backups, function ($b1, $b2) {
            return $b1->datetime <=> $b2->datetime;
        });

        return array_reverse($backups);
    }

    /**
     * Prune old database backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array of filenames that were deleted
     */
    public function pruneDatabases($dryRun = false): array
    {
        $filenames = $this->list(".sql");
        return $this->prune($filenames, $dryRun);
    }

    /**
     * Prune old volume backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array containing the deleted local and remote path 
     */
    public function pruneVolumes($dryRun = false)
    {
        $filenames = $this->list(".zip");
        return $this->prune($filenames, $dryRun);
    }

    private function prune($filenames, $dryRun = false)
    {
        $backups = $this->parseFilenames($filenames);
        $backups = $this->getOldBackups($backups);
        $results = [];
        foreach ($backups as $backup) {
            $filename = $backup->filename;
            if (!$dryRun) {
                $this->delete($backup->filename);
                array_push($results, $filename);
            }
        }
        return $results;
    }

    /**
     * Find the backups that should be deleted
     * 
     * @param array $backups Array of Backup objects
     * @param bool $report Print information to the console
     * @return array An array of Backup objects for deletion
     */
    private function getOldBackups($backups, $report = true)
    {
        foreach ($backups as $backup) {
            $backup->delete = True;
        }

        $config = [
            "Hourly" => [
                "format" => 'Y-m-d H',
                "limit" => RemoteBackup::getInstance()->getSettings()->pruneHourlyCount
            ],
            "Daily" => [
                "format" => 'Y-m-d',
                "limit" => RemoteBackup::getInstance()->getSettings()->pruneDailyCount
            ],
            "Weekly" => [
                "format" => 'Y-W',
                "limit" => RemoteBackup::getInstance()->getSettings()->pruneWeeklyCount
            ],
            "Monthly" => [
                "format" => 'Y-m',
                "limit" => RemoteBackup::getInstance()->getSettings()->pruneMonthlyCount
            ],
            "Yearly" => [
                "format" => 'Y',
                "limit" => RemoteBackup::getInstance()->getSettings()->pruneYearlyCount
            ]
        ];

        $data = [
            "Hourly" => [],
            "Daily" => [],
            "Weekly" => [],
            "Monthly" => [],
            "Yearly" => [],
        ];

        $groups = [
            "Hourly" => [],
            "Daily" => [],
            "Weekly" => [],
            "Monthly" => [],
            "Yearly" => [],
        ];

        // Group all dates by their periods
        foreach ($backups as $backup) {
            foreach ($config as $period => $settings) {
                $key = $backup->datetime->format($settings["format"]);
                if (!array_key_exists($key, $data[$period])) {
                    $data[$period][$key] = [];
                }
                array_push($data[$period][$key], $backup);
            }
        }
        // Save relevant backups from each period
        foreach ($config as $period => $settings) {
            foreach ($data[$period] as $key => $_backups) {
                if (count($groups[$period]) >= $settings["limit"]) {
                    break;
                }
                $backup = $_backups[0];
                $backup->delete = False;
                array_push($groups[$period], $backup);
            }
        }

        if ($report) {
            Craft::debug('All Backups:' . PHP_EOL, 'remote-backup');
            foreach ($backups as $backup) {
                Craft::debug($backup->datetime->format('Y-m-d H:i:s'), 'remote-backup');
            }
            Craft::debug(PHP_EOL, 'remote-backup');

            Craft::debug('Saving:' . PHP_EOL, 'remote-backup');
            foreach ($groups as $period => $_backups) {
                Craft::debug(" " . $period . " (Most recent " . $config[$period]['limit'] . ')' . PHP_EOL, 'remote-backup');
                foreach ($_backups as $backup) {
                    Craft::debug(" + " . $backup->datetime->format('Y-m-d H:i:s') . PHP_EOL, 'remote-backup');
                }
            }

            Craft::debug('For Deletion:' . PHP_EOL, 'remote-backup');
            foreach ($backups as $backup) {
                if ($backup->delete) {
                    Craft::debug(" - " . $backup->datetime->format('Y-m-d H:i:s') . PHP_EOL, 'remote-backup');
                }
            }
        }

        $oldBackups = [];
        foreach ($backups as $backup) {
            if ($backup->delete) {
                array_push($oldBackups, $backup);
            }
        }

        return $oldBackups;
    }

    protected function getLocalDir()
    {
        return Craft::$app->getPath()->getDbBackupPath();
    }

    /**
     * Factory method to return appropriate class depending on provider
     * setting
     * 
     * @return class The provider
     */
    public static function create($provider)
    {
        switch ($provider) {
            case "s3":
                return S3Service::class;
                break;
        }
    }
}
