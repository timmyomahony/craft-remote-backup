<?php

namespace weareferal\remotebackup\queue;

use Craft;
use craft\queue\BaseJob;
use yii\queue\RetryableJobInterface;

use weareferal\remotebackup\RemoteBackup;

class PruneVolumeBackupsJob extends BaseJob implements RetryableJobInterface
{
    public function getTtr()
    {
        return RemoteBackup::getInstance()->getSettings()->queueTtr;
    }

    public function execute($queue)
    {
        RemoteBackup::getInstance()->prune->pruneVolumes();
    }

    protected function defaultDescription(): string
    {
        return Craft::t('remote-backup', 'Removing old zip backups from remote destination');
    }
    
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
