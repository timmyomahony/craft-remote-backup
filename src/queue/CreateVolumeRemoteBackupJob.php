<?php

namespace weareferal\remotebackup\queue;

use craft\queue\BaseJob;

use weareferal\remotebackup\RemoteBackup;

class CreateVolumeBackupJob extends BaseJob
{
    public function execute($queue)
    {
        RemoteBackup::getInstance()->remotebackup->createVolumeBackup();
    }

    protected function defaultDescription()
    {
        return 'Create a new remote volumes backup';
    }
}
