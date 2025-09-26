<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Aws\S3\MultipartUploader;

use weareferal\remotebackup\services\ProviderService;
use weareferal\remotebackup\exceptions\ProviderException;
use weareferal\remotebackup\helpers\RemoteFile;


/**
 * AWS Provider
 *
 * A provider for use with Amazon AWS S3. This class can also be used to
 * implement other providers that use the S3 API footproint, like Digital
 * Ocean.
 *
 * @since 1.0.0
 */
class AWSProvider extends ProviderService
{
    public $name = "AWS";

    /**
     * Provider is configured
     *
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
     */
    public function isConfigured(): bool
    {
        $bucketName = $this->getBucketName();
        $regionName = $this->getRegionName();
        return isset($bucketName) &&
            isset($regionName);
    }

    /**
     * Return S3 keys
     *
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of keys returned from S3
     * @since 1.0.0
     */
    public function list($filterExtension): array
    {
        $client = $this->getClient();
        $kwargs = [
            'Bucket' => $this->getBucketName(),
        ];
        if ($this->getBucketPath() !== null) {
            $kwargs['Prefix'] = $this->getBucketPath();
        }
        Craft::info("Listing AWS objects");
        Craft::info($kwargs, "remote-backup");
        $response = $client->listObjects($kwargs);


        $files = $response['Contents'];
        if (!$files) {
            Craft::info("AWS: No files exist", "remote-backup");
            return [];
        }

        $remote_files = [];
        foreach ($files as $file) {
            if ($file['StorageClass'] == "STANDARD" && $file['Size'] > 0) {
                Craft::info("File:".$file['Key'] ,"remote-backup");
                array_push($remote_files, new RemoteFile(basename($file['Key']), $file['Size']));
            }
        }

        if ($filterExtension) {
            return $this->filterByExtension($remote_files, $filterExtension);
        }

        return $remote_files;
    }

    /**
     * Push a file path to S3
     *
     * @param string $path The full filesystem path to file
     * @since 1.0.0
     */
    public function push($path)
    {
        $client = $this->getClient();
        $pathInfo = pathinfo($path);
        $key = $this->getPrefixedKey($pathInfo['basename']);

        Craft::info("AWS: Local path: " . $path, "remote-backup");
        Craft::info("AWS: Remote path: " . $key, "remote-backup");

        try {
            $uploader = new MultipartUploader($client, $path, [
                'bucket' => $this->getBucketName(),
                'key' => $key,
            ]);
            $uploader->upload();
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    /**
     * Pull a remote S3 file
     *
     * @since 1.0.0
     */
    public function pull($key, $localPath)
    {
        $client = $this->getClient();
        $key = $this->getPrefixedKey($key);

        Craft::info("AWS: Remote path: " . $key, "remote-backup");
        Craft::info("AWS: Local path: " . $localPath, "remote-backup");

        try {
            $client->getObject([
                'Bucket' => $this->getBucketName(),
                'SaveAs' => $localPath,
                'Key' => $key,
            ]);
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }

        return true;
    }

    /**
     * Delete a remote S3 key
     *
     * @since 1.0.0
     */
    public function delete($key)
    {
        $client = $this->getClient();
        $key = $this->getPrefixedKey($key);
        $exists = $client->doesObjectExist($this->getBucketName(), $key);
        if (!$exists) {
            throw new ProviderException("File does not exist on AWS");
        }
        try {
            $client->deleteObject([
                'Bucket' => $this->getBucketName(),
                'Key'    => $key
            ]);
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    /**
     * Return the AWS key, including any prefix folders
     *
     * @param string $key The key for the key
     * @return string The prefixed key
     * @since 1.0.0
     */
    private function getPrefixedKey($key): string
    {
        if ($this->getBucketPath()) {
            return $this->getBucketPath() . DIRECTORY_SEPARATOR . $key;
        }
        return $key;
    }

    /**
     * Return a useable S3 client object
     *
     * @return S3Client The S3 client object
     * @since 1.0.0
     */
    private function getClient(): S3Client
    {
        $options = [
            'version' => 'latest',
            'region'  => $this->getRegionName()
        ];
        $endpoint = $this->getEndpoint();
        if ($endpoint) {
            $options['endpoint'] = $endpoint;
        }
        if ($this->getAccessKey() && $this->getSecretKey()) {
            $options['credentials'] = [
                'key'    => $this->getAccessKey(),
                'secret' => $this->getSecretKey(),
            ];
        }
        return S3Client::factory($options);
    }

    /**
     * Get endpoint
     *
     * If using a non-AWS endpoint (like Digital Ocean) we specify the
     * endpoint used in the client here
     */
    protected function getEndpoint(): ?string
    {
        return null;
    }

    protected function getAccessKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->s3AccessKey);
    }

    protected function getSecretKey(): ?string {
        return Craft::parseEnv($this->plugin->settings->s3SecretKey);
    }

    protected function getRegionName(): ?string {
        return Craft::parseEnv($this->plugin->settings->s3RegionName);
    }

    protected function getBucketName(): ?string {
        return Craft::parseEnv($this->plugin->settings->s3BucketName);
    }

    protected function getBucketPath(): ?string {
        return Craft::parseEnv($this->plugin->settings->s3BucketPath);
    }

    /**
     * Create a more user-friendly error message from AWS
     *
     * @param AwsException $exception The exception
     * @return string An client-friendly string
     * @since 1.0.0
     */
    private function createErrorMessage($exception)
    {
        Craft::$app->getErrorHandler()->logException($exception);
        $awsMessage = $exception->getAwsErrorMessage();
        $message = "{$this->name} Error";
        if ($awsMessage) {
            if (strpos($awsMessage, "The request signature we calculated does not match the signature you provided") !== false) {
                $message = $message . ' (Check secret key)';
            } else {
                $message = $message . ' ("' . $awsMessage . '")';
            }
        } else {
            $awsMessage = $exception->getMessage();
            if (strpos($awsMessage, 'Are you sure you are using the correct region for this bucket') !== false) {
                $message = $message . " (Check region credentials)";
            } else {
                $message = $message . " (Check credentials)";
            }
        }
        return $message;
    }
}
