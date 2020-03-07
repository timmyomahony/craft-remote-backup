<?php

namespace weareferal\backup\queue;

use craft\queue\BaseJob;

use weareferal\backup\Backup;

class CreateDatabaseBackupJob extends BaseJob
{
    public function execute($queue)
    {
        Backup::getInstance()->backup->createDatabaseBackup();
    }

    protected function defaultDescription()
    {
        return 'Create a new remote database backup';
    }
}
