<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use weareferal\remotebackup\services\providers\AWSProvider;


/**
 * Digital Ocean Spaces provider
 *
 * Because the Spaces API is based on AWS S3, we can use the same PHP SDK
 * library and simply point to a different endpoint:
 *
 * https://www.digitalocean.com/docs/spaces/resources/s3-sdk-examples/
 */
class DigitalOceanProvider extends AWSProvider
{
    public $name = "Digital Ocean Spaces";

    protected function getEndpoint(): ?string
    {
        $regionName = Craft::parseEnv($this->plugin->settings->doRegionName);
        return "https://{$regionName}.digitaloceanspaces.com";
    }

    protected function getAccessKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->doAccessKey);
    }

    protected function getSecretKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->doSecretKey);
    }

    protected function getRegionName(): ?string {
        // For whatever reason, to use the AWS SDK with Digital Ocean
        // you set the region name in the API options to us-east-1 while
        // adding the actual Digital Ocean region to the endpoint URL
        //
        // See for more:
        // https://www.digitalocean.com/docs/spaces/resources/s3-sdk-examples/
        return 'us-east-1';
    }

    protected function getBucketName(): ?string {
        return Craft::parseEnv($this->plugin->settings->doSpacesName);
    }

    protected function getBucketPath(): ?string {
        return Craft::parseEnv($this->plugin->settings->doSpacesPath);
    }
}
