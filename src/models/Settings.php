<?php

namespace weareferal\backup\models;

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

    public $pruneDailyCount = 14;
    public $pruneWeeklyCount = 4;
    public $pruneMonthlyCount = 6;
    public $pruneYearlyCount = 3;

    public function rules(): array
    {
        return [
            [
                ['cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName'],
                'required'
            ],
            [
                ['cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 's3BucketPrefix'],
                'string'
            ],
            [
                ['useQueue'],
                'boolean'
            ],
            [
                ['pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount', 'pruneYearlyCount'],
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
