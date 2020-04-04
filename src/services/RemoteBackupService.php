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
    public function listRemoteDatabaseBackups(): array;
    public function listRemoteVolumeBackups(): array;
    public function pushRemoteBackup($path): bool;
    public function deleteRemoteBackup($filename): bool;
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
    public function listDatabaseBackups(): array
    {
        $filenames = $this->listRemoteDatabaseBackups();
        $backups = $this->parseBackupFilenames($filenames);
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
    public function listVolumeBackups(): array
    {
        $filenames = $this->listRemoteVolumeBackups();
        $backups = $this->parseBackupFilenames($filenames);
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
     * Create a new remote backup of the database
     * 
     * @return string The filename of the newly created remote backup
     * @since 1.0.0
     */
    public function createDatabaseBackup()
    {
        $dir = Craft::$app->getPath()->getDbBackupPath();
        $filename = $this->getBackupFileName();
        $path = $dir . DIRECTORY_SEPARATOR . $filename . '.sql';

        Craft::$app->getDb()->backupTo($path);
        $this->pushRemoteBackup($path);

        if (!RemoteBackup::getInstance()->getSettings()->keepLocal) {
            unlink($path);
        }

        return $filename;
    }

    /**
     * Create a new remote backup of all asset volumes
     * 
     * @return string The filename of the newly created remote backup
     * @return null If no volumes exist
     * @since 1.0.0
     */
    public function createVolumeBackup()
    {
        $dir = Craft::$app->getPath()->getDbBackupPath();
        $filename = $this->getBackupFileName();
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

        $this->pushRemoteBackup($path);

        if (!RemoteBackup::getInstance()->getSettings()->keepLocal) {
            unlink($path);
        }

        return $filename;
    }

    /**
     * Prune old database backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array of filenames that were deleted
     */
    public function pruneDatabaseBackups($dryRun = false): array
    {
        $filenames = $this->listRemoteDatabaseBackups();
        return $this->prune($filenames, $dryRun);
    }

    /**
     * Prune old volume backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array containing the deleted local and remote path 
     */
    public function pruneVolumeBackups($dryRun = false)
    {
        $filenames = $this->listRemoteVolumeBackups();
        return $this->prune($filenames, $dryRun);
    }

    private function prune($filenames, $dryRun = false)
    {
        $backups = $this->parseBackupFilenames($filenames);
        $backups = $this->getOldBackups($backups);
        $results = [
            'deleted' => [],
            'failed' => []
        ];
        foreach ($backups as $backup) {
            $filename = $backup->filename;
            if (!$dryRun) {
                $deleted = $this->deleteRemoteBackup($backup->filename);
                if ($deleted) {
                    array_push($results['deleted'], $filename);
                } else {
                    array_push($results['failed'], $filename);
                }
            }
        }
        return $results;
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
    private function getBackupFileName(): string
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
    private function parseBackupFilenames($filenames): array
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
            "Daily" => [],
            "Weekly" => [],
            "Monthly" => [],
            "Yearly" => [],
        ];

        $groups = [
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
