<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use weareferal\remotebackup\services\providers\AWSProvider;


class OtherS3Provider extends AWSProvider
{
    public $name = "Other S3-Compliant Provider";

    protected function getEndpoint(): ?string
    {
        return Craft::parseEnv($this->plugin->settings->otherS3Endpoint);
    }

    protected function getAccessKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->otherS3AccessKey);
    }

    protected function getSecretKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->otherS3SecretKey);
    }

    protected function getRegionName(): ?string {
        return Craft::parseEnv($this->plugin->settings->otherS3RegionName);
    }

    protected function getBucketName(): ?string {
        return Craft::parseEnv($this->plugin->settings->otherS3BucketName);
    }

    protected function getBucketPath(): ?string {
        return Craft::parseEnv($this->plugin->settings->otherS3BucketPath);
    }
}
