<?php

namespace weareferal\remotebackup\models;

use craft\base\Model;

class Settings extends Model
{
    public $enabled = true;
    public $cloudProvider = 's3';
    public $s3AccessKey;
    public $s3SecretKey;
    public $s3RegionName;
    public $s3BucketName;
    public $s3BucketPrefix;

    public $useQueue = false;
    public $keepLocal = false;

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
                ['s3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 'pruneHourlyCount'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 's3' & $model->enabled == 1;
                }
            ],
            [
                ['pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount', 'pruneYearlyCount'],
                'required',
                'when' => function ($model) {
                    return $model->prune == 1 & $model->enabled == 1;
                }
            ],
            [
                ['cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 's3BucketPrefix'],
                'string'
            ],
            [
                ['enabled', 'useQueue', 'keepLocal', 'prune'],
                'boolean'
            ],
            [
                ['pruneHourlyCount', 'pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount', 'pruneYearlyCount'],
                'number'
            ]
        ];
    }

    public function configured(): bool
    {
        $vars = [
            $this->s3AccessKey,
            $this->s3SecretKey,
            $this->s3RegionName,
            $this->s3BucketName
        ];
        foreach ($vars as $var) {
            if (!$var || $var == '') {
                return false;
            }
        }
        return true;
    }
}
