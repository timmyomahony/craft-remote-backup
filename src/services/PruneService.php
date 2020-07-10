<?php

namespace weareferal\remotesync\services;

use Craft;
use craft\base\Component;

use weareferal\remotesync\RemoteSync;


/**
 * Prune service
 * 
 * @since 1.3.0
 */
class PruneService extends Component
{
    /**
     * Prune database files
     * 
     * Delete all "old" database files
     * 
     * @param boolean $dryRun if true do everything except actually deleting
     * @return array the deleted files
     * @since 1.3.0
     */
    public function pruneDatabases($dryRun = false)
    {
        $remoteFiles = RemoteSync::getInstance()->provider->listDatabases();
        return $this->prune($remoteFiles, $dryRun);
    }

    /**
     * Prune volume files
     * 
     * Delete all "old" database files
     * 
     * @param boolean $dryRun if true do everything except actually deleting
     * @return array the deleted files
     * @since 1.3.0
     */
    public function pruneVolumes($dryRun = false)
    {
        $remoteFiles = RemoteSync::getInstance()->provider->listVolumes();
        return $this->prune($remoteFiles, $dryRun);
    }

    /**
     * Prune files
     * 
     * Delete "old" remote files. This operation relies on "prune" from the
     * settings. The algorithm is simple, delete all files > than the sync
     * limit. In other words, if the sync limit is 5 and we have 9 backups,
     * delete the 6th-9th backups keeping the 5 most recent.
     * 
     * @param array $filenames an array of remote filenames
     * @param boolean $dryRun if true do everything except actually deleting
     * @return array the deleted files (or empty array)
     * @since 1.2.0
     */
    private function prune($remoteFiles, $dryRun = false)
    {
        $plugin = RemoteSync::getInstance();
        $settings = $plugin->getSettings();
        $deleted_filenames = [];

        if (!$settings->prune) {
            Craft::warning("Pruning disabled" . PHP_EOL, 'remote-sync');
            return $deleted_filenames;
        }

        $remoteFiles = $this->getOldBackups($remoteFiles);

        foreach ($remoteFiles as $remoteFile) {
            $filename = $remoteFile->filename;
            if (!$dryRun) {
                $plugin->provider->delete($remoteFile->filename);
                array_push($deleted_filenames, $filename);
            }
        }

        return $deleted_filenames;
    }

    /**
     * Find the backups that should be deleted
     * 
     * @param array $remoteFiles Array of Backup objects
     * @param bool $report Print information to the console
     * @return array An array of Backup objects for deletion
     */
    private function getOldBackups($remoteFiles, $report = true)
    {
        $settings = RemoteBackup::getInstance()->getSettings();

        foreach ($remoteFiles as $remoteFile) {
            $remoteFile->delete = True;
        }

        $config = [
            "Hourly" => [
                "format" => 'Y-m-d H',
                "limit" => $settings->pruneHourlyCount
            ],
            "Daily" => [
                "format" => 'Y-m-d',
                "limit" => $settings->pruneDailyCount
            ],
            "Weekly" => [
                "format" => 'Y-W',
                "limit" => $settings->pruneWeeklyCount
            ],
            "Monthly" => [
                "format" => 'Y-m',
                "limit" => $settings->pruneMonthlyCount
            ],
            "Yearly" => [
                "format" => 'Y',
                "limit" => $settings->pruneYearlyCount
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
        foreach ($remoteFiles as $remoteFile) {
            foreach ($config as $period => $settings) {
                $key = $remoteFile->datetime->format($settings["format"]);
                if (!array_key_exists($key, $data[$period])) {
                    $data[$period][$key] = [];
                }
                array_push($data[$period][$key], $remoteFile);
            }
        }
        // Save relevant backups from each period
        foreach ($config as $period => $settings) {
            foreach ($data[$period] as $key => $_remoteFile) {
                if (count($groups[$period]) >= $settings["limit"]) {
                    break;
                }
                $remoteFile = $_remoteFile[0];
                $remoteFile->delete = False;
                array_push($groups[$period], $remoteFile);
            }
        }

        if ($report) {
            Craft::debug('All Backups:' . PHP_EOL, 'remote-backup');
            foreach ($remoteFiles as $remoteFile) {
                Craft::debug($remoteFile->datetime->format('Y-m-d H:i:s'), 'remote-backup');
            }
            Craft::debug(PHP_EOL, 'remote-backup');

            Craft::debug('Saving:' . PHP_EOL, 'remote-backup');
            foreach ($groups as $period => $_remoteFile) {
                Craft::debug(" " . $period . " (Most recent " . $config[$period]['limit'] . ')' . PHP_EOL, 'remote-backup');
                foreach  $_remoteFile as $remoteFile) {
                    Craft::debug(" + " . $remoteFile->datetime->format('Y-m-d H:i:s') . PHP_EOL, 'remote-backup');
                }
            }

            Craft::debug('For Deletion:' . PHP_EOL, 'remote-backup');
            foreach ($remoteFiles as $remoteFile) {
                if ($remoteFile->delete) {
                    Craft::debug(" - " . $remoteFile->datetime->format('Y-m-d H:i:s') . PHP_EOL, 'remote-backup');
                }
            }
        }

        $oldBackups = [];
        foreach ($remoteFiles as $remoteFile) {
            if ($remoteFile->delete) {
                array_push($oldBackups, $remoteFile);
            }
        }

        return $oldBackups;
    }
}
