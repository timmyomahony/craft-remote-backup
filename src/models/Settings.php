<?php

namespace weareferal\remotebackup\models;

use craft\base\Model;

class Settings extends Model
{
    public $cloudProvider = 's3';
    public $s3AccessKey;
    public $s3SecretKey;
    public $s3RegionName;
    public $s3BucketName;
    public $s3BucketPrefix;

    public $useQueue = false;
    public $keepLocal = false;

    /**
     * Pruning involves keeping a minimum number of the most recent backups
     * for daily, weekly, monthly and yearly backup periods. The number of 
     * retained backups is decided by the plugin settings but is usually:
     * 
     * 14 of the most recent daily backups
     * 4 of the most recent weekly backups
     * 6 of the most recent monthly backups
     * 3 of the most recent yearly backups
     */
    public $prune = true;
    public $pruneHourlyCount = 6;
    public $pruneDailyCount = 14;
    public $pruneWeeklyCount = 4;
    public $pruneMonthlyCount = 6;
    public $pruneYearlyCount = 3;

    public function rules(): array
    {
        return [
            [
                ['cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 'pruneHourlyCount', 'pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount', 'pruneYearlyCount'],
                'required'
            ],
            [
                ['cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 's3BucketPrefix'],
                'string'
            ],
            [
                ['useQueue', 'keepLocal', 'prune'],
                'boolean'
            ],
            [
                ['pruneHourlyCount', 'pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount', 'pruneYearlyCount'],
                'number'
            ]
        ];
    }

    public function isConfigured(): bool
    {
        $vars = [
            $this->s3AccessKey,
            $this->s3SecretKey,
            $this->s3RegionName,
            $this->s3BucketName
        ];
        $configured = true;
        foreach ($vars as $var) {
            if (!$var || $var == '') {
                return false;
            }
        }
        return true;
    }
}
