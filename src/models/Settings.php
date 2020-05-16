<?php

namespace weareferal\remotebackup\models;

use craft\base\Model;

class Settings extends Model
{
    public $enabled = true;

    public $cloudProvider = 's3';

    // AWS
    public $s3AccessKey;
    public $s3SecretKey;
    public $s3RegionName;
    public $s3BucketName;
    public $s3BucketPrefix;

    // Backblaze
    public $b2MasterKeyID;
    public $b2MasterAppKey;
    public $b2BucketName;
    public $b2BucketPrefix;

    // Google
    public $googleProjectName;
    public $googleClientId;
    public $googleClientSecret;
    public $googleAuthRedirect;
    public $googleDriveFolderId;

    public $useQueue = false;
    public $keepLocal = false;

    public $prune = true;
    public $pruneHourlyCount = 6;
    public $pruneDailyCount = 14;
    public $pruneWeeklyCount = 4;
    public $pruneMonthlyCount = 6;
    public $pruneYearlyCount = 3;

    public $hideDatabases = false;
    public $hideVolumes = false;

    public function rules(): array
    {
        return [
            [
                ['s3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 's3' & $model->enabled == 1;
                }
            ],
            [
                ['b2MasterKeyID', 'b2MasterAppKey', 'b2BucketName'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 'b2' & $model->enabled == 1;
                }
            ],
            [
                [
                    'googleClientId', 'googleClientSecret', 'googleProjectName',
                    'googleAuthRedirect'
                ],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 'google' & $model->enabled == 1;
                }
            ],
            [
                [
                    'pruneDailyCount', 'pruneWeeklyCount', 'pruneMonthlyCount',
                    'pruneYearlyCount'
                ],
                'required',
                'when' => function ($model) {
                    return $model->prune == 1 & $model->enabled == 1;
                }
            ],
            [
                [
                    'cloudProvider', 's3AccessKey', 's3SecretKey', 's3BucketName',
                    's3RegionName', 's3BucketPrefix', 'b2MasterKeyID', 'b2MasterAppKey',
                    'b2BucketName', 'b2BucketPrefix', 'googleClientId',
                    'googleClientSecret', 'googleProjectName', 'googleAuthRedirect',
                    'googleDriveFolderId'
                ],
                'string'
            ],
            [
                [
                    'enabled', 'useQueue', 'keepLocal', 'prune', 'hideDatabases',
                    'hideVolumes'
                ],
                'boolean'
            ],
            [
                [
                    'pruneHourlyCount', 'pruneDailyCount', 'pruneWeeklyCount',
                    'pruneMonthlyCount', 'pruneYearlyCount'
                ],
                'number'
            ],
            // This seems like a poor API design in Yii 2. We want to show a 
            // validation when a user hides both the database and volumes. You
            //  can't create custom validators that run on two separate fields
            // (as it would run twice)
            //
            // https://www.yiiframework.com/doc/guide/2.0/en/input-validation#multiple-attributes-validation
            ['hideDatabases', 'validateHideRules'],
        ];
    }

    public function validateHideRules($attribute, $params)
    {
        if ($this->hideDatabases && $this->hideVolumes) {
            $this->addError('hideDatabases', 'You cannot hide both databases and volumes');
            $this->addError('hideVolumes', 'You cannot hide both databases and volumes');
        }
    }
}
