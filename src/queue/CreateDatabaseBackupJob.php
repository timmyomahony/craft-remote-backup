<?php

namespace weareferal\remotebackup\queue;

use Craft;
use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;

use weareferal\remotebackup\RemoteBackup;

class CreateDatabaseBackupJob extends BaseJob implements RetryableJobInterface
{
    public function getTtr()
    {
        return RemoteBackup::getInstance()->getSettings()->queueTtr;
    }

    public function execute($queue)
    {
        RemoteBackup::getInstance()->provider->pushDatabase();
    }

    protected function defaultDescription(): string
    {
        return Craft::t('remote-backup', 'Pushing .sql database backup to remote destination');
    }
    
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
