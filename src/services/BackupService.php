<?php

namespace weareferal\backup\services;

use yii\base\Component;
use Craft;
use Craft\helpers\FileHelper;
use Craft\helpers\StringHelper;

use weareferal\backup\Backup;
use weareferal\backup\services\providers\S3Service;
use weareferal\backup\helpers\ZipHelper;

/**
 * Backupable interface for all providers
 */
interface Backupable
{
    public function pullDatabaseBackups(): array;
    public function pushDatabaseBackups(): array;
    public function pushVolumeBackups(): array;
    public function pullVolumeBackups(): array;
    public function deleteRemoteBackups($backups): array;
}

/**
 *
 */
class Backup
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
        preg_match(Backup::$regex, $_filename, $matches);
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

    public function path()
    {
        $path = Craft::$app->getPath()->getDbBackupPath();
        return $path . DIRECTORY_SEPARATOR . $this->filename;
    }
}

class BackupService extends Component
{
    /**
     * Create a SQL database dump to our backup folder
     * 
     * NOTE: Craft already has a native function for this operation, but 
     * we want to provide a little bit more control over the filename so we
     * piggy-back on the existing backup methods from 
     * 
     * https://github.com/craftcms/cms/blob/master/src/db/Connection.php
     * 
     * @return string The path to the newly created backup
     */
    public function createDatabaseBackup()
    {
        $backupPath = $this->createBackupPath('sql');
        Craft::$app->getDb()->backupTo($backupPath);
        return $backupPath;
    }

    /**
     * Create a zipped archive of all volumes to our backup folder
     * 
     * @return string The path to the newly created backup
     */
    public function createVolumeBackup(): string
    {
        $backupPath = $this->createBackupPath('zip');
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        foreach ($volumes as $i => $volume) {
            $tmpPath = $tmpDirName . DIRECTORY_SEPARATOR . $volume->handle;
            FileHelper::copyDirectory($volume->rootPath, $tmpPath);
        }

        ZipHelper::recursiveZip($tmpDirName, $backupPath);
        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());

        return $backupPath;
    }

    /**
     * Restore a particular volume backup
     * 
     * @param string $filename: The filename (not absolute path) of the 
     * zipped volumes archive to restore
     * @return string The path for the restored backup file
     */
    public function restoreVolumesBackup($filename)
    {
        $backupPath = Craft::$app->getPath()->getDbBackupPath() . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '.zip';
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDirName = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));

        ZipHelper::unzip($backupPath, $tmpDirName);

        $folders = array_diff(scandir($tmpDirName), array('.', '..'));
        foreach ($folders as $folder) {
            foreach ($volumes as $volume) {
                if ($folder == $volume->handle) {
                    $dest = $tmpDirName . DIRECTORY_SEPARATOR . $folder;
                    if (!file_exists($volume->rootPath)) {
                        FileHelper::createDirectory($volume->rootPath);
                    } else {
                        FileHelper::clearDirectory($volume->rootPath);
                    }
                    FileHelper::copyDirectory($dest, $volume->rootPath);
                }
            }
        }

        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());

        return $backupPath;
    }

    /**
     * Restore a particular database backup
     * 
     * @param string $filename The filename (not absolute path) of the 
     * zipped volumes archive to restore
     * @return string The path for the restored backup file
     */
    public function restoreDatabaseBackup($filename): string
    {
        $path = Craft::$app->getPath()->getDbBackupPath() . DIRECTORY_SEPARATOR . pathinfo($filename, PATHINFO_FILENAME) . '.sql';
        Craft::$app->getDb()->restore($path);
        return $path;
    }

    /**
     * Prune old database backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array containing the deleted local and remote path 
     */
    public function pruneDatabaseBackups($dryRun = false)
    {
        return $this->prune("sql", $dryRun);
    }

    /**
     * Prune old volume backups
     * 
     * @param bool $dryRun Skip the actual deletion of files
     * @return array An array containing the deleted local and remote path 
     */
    public function pruneVolumeBackups($dryRun = false)
    {
        return $this->prune("zip", $dryRun);
    }

    /**
     * Return available database backups
     * 
     * @return array A list of filename ready for an HTML select
     */
    public function getDbBackupOptions(): array
    {
        $filenames = $this->getBackupFilenames("sql");
        return $this->getHTMLSelectOptions($filenames);
    }

    /**
     * Return available volume backups
     * 
     * @return string[] A list of filename ready for an HTML select
     */
    public function getVolumeBackupOptions(): array
    {
        $filenames = $this->getBackupFilenames("zip");
        return $this->getHTMLSelectOptions($filenames);
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
    protected function createBackupFileName(): string
    {
        $currentVersion = 'v' . Craft::$app->getVersion();
        $systemName = FileHelper::sanitizeFilename(Craft::$app->getInfo()->name, ['asciiOnly' => true]);
        $systemEnv = Craft::$app->env;
        $filename = ($systemName ? $systemName . '_' : '') . ($systemEnv ? $systemEnv . '_' : '') . gmdate('ymd_His') . '_' . strtolower(StringHelper::randomString(10)) . '_' . $currentVersion;
        return mb_strtolower($filename);
    }

    /**
     * Return the absolute path to a new backup file
     * 
     * @param string $extension: The extension to add to the new file
     * @return string The absolute path to a new backup
     */
    protected function createBackupPath($extension): string
    {
        $dir = Craft::$app->getPath()->getDbBackupPath();
        $filename = $this->createBackupFileName();
        $path = $dir . DIRECTORY_SEPARATOR . $filename . '.' . $extension;
        return mb_strtolower($path);
    }

    /**
     * Return all backup filenames of a particular extension
     * 
     * @param string $extension: The extension to target
     * @return array An array of filenames
     */
    protected function getBackupFilenames($extension): array

    {
        $dir = Craft::$app->getPath()->getDbBackupPath();
        return preg_grep('~\.' . $extension . '$~', scandir($dir));
    }

    /**
     * Return a chronologically sorted array of Backup objects
     * 
     * @param string[] Array of filenames
     * @return array[] Array of Backup objects
     */
    protected function parseBackupFilenames($filenames): array
    {
        $backups = [];

        foreach ($filenames as $filename) {
            array_push($backups, new Backup($filename));
        }

        uasort($backups, function ($b1, $b2) {
            return $b1->datetime <=> $b2->datetime;
        });

        return array_reverse($backups);
    }

    /**
     * Return an array of human-readable select options
     * 
     * @param array $filenames: Array of filenames
     * @return array Array of labels mapped to values
     */
    protected function getHTMLSelectOptions($filenames): array
    {
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
     * Pruning involves keeping a minimum number of the most recent backups
     * for daily, weekly, monthly and yearly backup periods. The number of 
     * retained backups is decided by the plugin settings but is usually:
     * 
     * 14 of the most recent daily backups
     * 4 of the most recent weekly backups
     * 6 of the most recent monthly backups
     * 3 of the most recent yearly backups
     * 
     * @param string $extension: The type of backups we are targeting for
     * deletion
     */
    protected function prune($extension, $dryRun = false): array
    {
        $filenames = $this->getBackupFilenames($extension);
        $backups = $this->parseBackupFilenames($filenames);
        $oldBackups = $this->getOldBackups($backups);

        $result = [
            "local" => [],
            "remote" => []
        ];

        if (!$dryRun) {
            $result["local"] = $this->deleteLocalBackups($oldBackups);
            $result["remote"] = $this->deleteRemoteBackups($oldBackups);
        }

        return $result;
    }

    /**
     * Delete local backups paths
     * 
     * @param array $backups An array of Backup objects
     * @return array An array of paths that were deleted
     */
    protected function deleteLocalBackups($backups): array
    {
        $paths = [];
        foreach ($backups as $backup) {
            $path = $backup->path();
            array_push($paths, $path);
            unlink($path);
        }
        return $paths;
    }

    /**
     * Find the backups that should be deleted
     * 
     * @param array $backups Array of Backup objects
     * @param bool $report Print information to the console
     * @return array An array of Backup objects for deletion
     */
    protected function getOldBackups($backups, $report = true)
    {
        foreach ($backups as $backup) {
            $backup->delete = True;
        }

        $config = [
            "Daily" => [
                "format" => 'Y-m-d',
                "limit" => Backup::getInstance()->getSettings()->pruneDailyCount
            ],
            "Weekly" => [
                "format" => 'Y-W',
                "limit" => Backup::getInstance()->getSettings()->pruneWeeklyCount
            ],
            "Monthly" => [
                "format" => 'Y-m',
                "limit" => Backup::getInstance()->getSettings()->pruneMonthlyCount
            ],
            "Yearly" => [
                "format" => 'Y',
                "limit" => Backup::getInstance()->getSettings()->pruneYearlyCount
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
                if (!array_key_exists($key, $groups[$period])) {
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
            Craft::debug('Saving:' . PHP_EOL, 'backup');
            foreach ($groups as $period => $_backups) {
                Craft::debug(" " . $period . " (Most recent " . $config[$period]['limit'] . ')' . PHP_EOL, 'backup');
                foreach ($_backups as $backup) {
                    Craft::debug(" + " . $backup->datetime->format('Y-m-d') . PHP_EOL, 'backup');
                }
            }

            Craft::debug('For Deletion:' . PHP_EOL, 'backup');
            foreach ($backups as $backup) {
                if ($backup->delete) {
                    Craft::debug(" - " . $backup->datetime->format('Y-m-d') . PHP_EOL, 'backup');
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
