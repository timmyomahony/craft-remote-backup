<?php

namespace weareferal\remotebackup\queue;

use craft\queue\BaseJob;

use weareferal\remotebackup\RemoteBackup;

class CreateDatabaseBackupJob extends BaseJob
{
    public function execute($queue)
    {
        RemoteBackup::getInstance()->provider->pushDatabase();
    }

    protected function defaultDescription()
    {
        return 'Create a new remote database backup';
    }
}
