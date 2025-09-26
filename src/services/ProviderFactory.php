<?php
namespace weareferal\remotebackup\services;

use Craft;
use craft\base\Component;
use weareferal\remotebackup\exceptions\ProviderException;


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
        try {
            return new $ProviderClass($plugin);
        } catch (\Error $e) {
            // Check if it's a class not found error related to AWS SDK
            if (strpos($e->getMessage(), 'Aws\\') !== false ||
                strpos($e->getMessage(), 'Class \'Aws') !== false) {
                throw new ProviderException("AWS SDK is required for Backblaze B2, Digital Ocean, and other S3-compatible providers. Please install it with: composer require aws/aws-sdk-php");
            }
            throw $e;
        } catch (\Exception $e) {
            throw new ProviderException("Failed to create provider: " . $e->getMessage());
        }
    }
}
