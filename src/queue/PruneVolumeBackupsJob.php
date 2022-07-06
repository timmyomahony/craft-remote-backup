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

    public function execute($queue): void
    {
        RemoteBackup::getInstance()->prune->pruneVolumes();
    }

    protected function defaultDescription(): string|null
    {
        return Craft::t('remote-backup', 'Prune remote volume backups');
    }
    
    public function canRetry($attempt, $error)
    {
        // If true, errors aren't reported in the Craft Utilities queue manager
        return false;
    }
}
