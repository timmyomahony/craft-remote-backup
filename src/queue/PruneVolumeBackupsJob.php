<?php

namespace weareferal\remotebackup\queue;

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

    protected function defaultDescription()
    {
        return 'Prune remote volume backups';
    }
    
    public function canRetry($attempt, $error)
    {
        return true;
    }
}
