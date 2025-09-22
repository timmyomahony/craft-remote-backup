<?php
namespace weareferal\remotebackup\services;

use Craft;
use craft\base\Component;


/**
 * Provider Factory
 *
 * A factory service that returns a provider intance based on the calling
 * plugins "cloudProvider" setting.
 */
class ProviderFactory extends Component {
    public function create($plugin) {
        $ProviderClass = null;
        switch ($plugin->getSettings()->cloudProvider) {
            case "s3":
                $ProviderClass = \weareferal\remotebackup\services\providers\AWSProvider::class;
                break;
            case "b2":
                $ProviderClass = \weareferal\remotebackup\services\providers\BackblazeProvider::class;
                break;
            case "google":
                $ProviderClass = \weareferal\remotebackup\services\providers\GoogleDriveProvider::class;
                break;
            case "dropbox":
                $ProviderClass = \weareferal\remotebackup\services\providers\DropboxProvider::class;
                break;
            case "do":
                $ProviderClass = \weareferal\remotebackup\services\providers\DigitalOceanProvider::class;
                break;
            case "other-s3":
                $ProviderClass = \weareferal\remotebackup\services\providers\OtherS3Provider::class;
                break;
        }
        return new $ProviderClass($plugin);
    }
}
