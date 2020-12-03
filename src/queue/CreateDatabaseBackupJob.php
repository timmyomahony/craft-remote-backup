<?php

namespace weareferal\remotebackup\queue;

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

    protected function defaultDescription()
    {
        return 'Create a new remote database backup';
    }
    
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
