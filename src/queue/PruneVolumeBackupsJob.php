<?php

namespace weareferal\remotebackup\queue;

use craft\queue\BaseJob;

use weareferal\remotebackup\RemoteBackup;

class PruneVolumeBackupsJob extends BaseJob
{
    public function execute($queue)
    {
        RemoteBackup::getInstance()->pruneservice->pruneVolumes();
    }

    protected function defaultDescription()
    {
        return 'Prune remote volume backups';
    }
}
