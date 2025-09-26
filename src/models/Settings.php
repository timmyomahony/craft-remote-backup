<?php

namespace weareferal\remotebackup\models;

use craft\base\Model;


/**
 * Common plugin settings
 *
 */
class Settings extends Model
{
    // Seemd redundant but allows the plugins to be enabled/disabled on
    // different environments via configuration settings, which is useful.
    public $enabled = true;

    public $keepLocal = false;
    public $prune = true;
    public $pruneHourlyCount = 6;
    public $pruneDailyCount = 14;
    public $pruneWeeklyCount = 4;
    public $pruneMonthlyCount = 6;
    public $pruneYearlyCount = 3;

    public $cloudProvider = 's3';


    // AWS configuration settings
    public $s3AccessKey;
    public $s3SecretKey;
    public $s3RegionName;
    public $s3BucketName;
    public $s3BucketPath;

    // Backblaze configuration settings
    public $b2MasterKeyID;
    public $b2MasterAppKey;
    public $b2RegionName;
    public $b2BucketName;
    public $b2BucketPath;

    // Google configuration settings
    public $googleProjectName;
    public $googleClientId;
    public $googleClientSecret;
    public $googleAuthRedirect;
    public $googleDriveFolderId;

    // Dropbox configuration settings
    public $dropboxAppKey;
    public $dropboxSecretKey;
    public $dropboxAccessToken;
    public $dropboxFolder;

    // Digital Ocean configuration settings
    public $doAccessKey;
    public $doSecretKey;
    public $doRegionName;
    public $doSpacesName;
    public $doSpacesPath;

    // Other S3 compliant configuration settings
    public $otherS3AccessKey;
    public $otherS3SecretKey;
    public $otherS3Endpoint;
    public $otherS3RegionName;
    public $otherS3BucketName;
    public $otherS3BucketPath;

    // Use the build-in Craft queue to handle all operations. This is useful
    // when you have large database of volume backups that need to be run where
    // a regular non-async request might timeout
    public $useQueue = false;
    // https://github.com/yiisoft/yii2-queue/blob/master/docs/guide/retryable.md#retryablejobinterface
    public $queueTtr = "300";

    // Show/hide either databases or volume backups in the utilities panel.
    // This is useful for cleaning up the interface
    public $hideDatabases = false;
    public $hideVolumes = false;

    public $displayDateFormat = "Y-m-d";

    public function rules(): array
    {
        return [
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
                    'keepLocal', 'prune'
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
            // Provider details should only run when that provider is selected
            [
                ['s3BucketName', 's3RegionName'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 's3' & $model->enabled == 1;
                }
            ],
            [
                ['b2MasterKeyID', 'b2MasterAppKey', 'b2RegionName', 'b2BucketName'],
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
                    'dropboxAppKey', 'dropboxSecretKey', 'dropboxAccessToken',
                ],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 'dropbox' & $model->enabled == 1;
                }
            ],
            [
                ['doAccessKey', 'doSecretKey', 'doSpacesName', 'doRegionName'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 'do' & $model->enabled == 1;
                }
            ],
            [
                ['otherS3BucketName', 'otherS3RegionName', 'otherS3Endpoint'],
                'required',
                'when' => function ($model) {
                    return $model->cloudProvider == 'other-s3' & $model->enabled == 1;
                }
            ],

            [
                [
                    'cloudProvider',
                    's3AccessKey', 's3SecretKey', 's3BucketName', 's3RegionName', 's3BucketPath',
                    'b2MasterKeyID', 'b2MasterAppKey', 'b2RegionName', 'b2BucketName', 'b2BucketPath',
                    'googleClientId', 'googleClientSecret', 'googleProjectName', 'googleAuthRedirect', 'googleDriveFolderId',
                    'dropboxAppKey', 'dropboxSecretKey', 'dropboxAccessToken', 'dropboxFolder',
                    'doAccessKey', 'doSecretKey', 'doSpacesName', 'doRegionName', 'doSpacesPath',
                    'displayDateFormat', 'queueTtr'
                ],
                'string'
            ],
            [
                ['useQueue', 'hideDatabases', 'hideVolumes'],
                'boolean'
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
