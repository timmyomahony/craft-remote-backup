<?php

namespace weareferal\remotebackup\queue;

use Craft;
use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;

use weareferal\remotebackup\RemoteBackup;

class CreateVolumeBackupJob extends BaseJob implements RetryableJobInterface
{
    public function getTtr()
    {
        return RemoteBackup::getInstance()->getSettings()->queueTtr;
    }

    public function execute($queue)
    {
        RemoteBackup::getInstance()->provider->pushVolumes();
    }

    protected function defaultDescription(): string
    {
        return Craft::t('remote-backup', 'Zipping volumes and pushing to remote destination');
    }
    
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
