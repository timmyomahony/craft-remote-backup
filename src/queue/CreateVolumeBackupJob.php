<?php

namespace weareferal\backup\queue;

use craft\queue\BaseJob;

use weareferal\backup\Backup;

class CreateVolumeBackupJob extends BaseJob
{
    public function execute($queue)
    {
        Backup::getInstance()->backup->createVolumeBackup();
    }

    protected function defaultDescription()
    {
        return 'Create a new remote volume backup';
    }
}
