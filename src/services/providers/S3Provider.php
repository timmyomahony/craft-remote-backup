<?php

namespace weareferal\remotebackup\services\providers;

use Craft;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\Provider;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\exceptions\ProviderException;



class S3Service extends RemoteBackupService implements Provider
{
    // Public methods

    public function listRemoteDatabaseBackups(): array
    {
        try {
            return $this->get("sql");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    public function listRemoteVolumeBackups(): array
    {
        try {
            return $this->get("zip");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    public function pushRemoteBackup($path): bool
    {
        try {
            return $this->push($path);
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    public function deleteRemoteBackup($filename): bool
    {
        try {
            return $this->delete($filename);
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    // Private methods

    /**
     * Delete a remote S3 key
     * 
     * @return bool Remote key was successfully deleted
     * @since 1.0.0
     */
    private function delete($filename): bool
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3BucketName = Craft::parseEnv($settings->s3BucketName);
        $client = $this->getS3Client();
        $key = $this->getAWSKey($filename);
        $exists = $client->doesObjectExist($s3BucketName, $key);
        if ($exists) {
            $client->deleteObject([
                'Bucket' => $s3BucketName,
                'Key'    => $key
            ]);
            return true;
        } else {
            Craft::warning("Could\'nt delete '" . $key . "' as it doesn't exist on S3", "remote-backup");
        }
        return false;
    }

    /**
     * Return S3 keys
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of filenames returned from S3
     * @since 1.0.0
     */
    private function get($extension): array
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3BucketName = Craft::parseEnv($settings->s3BucketName);
        $s3BucketPrefix = Craft::parseEnv($settings->s3BucketPrefix);
        $client = $this->getS3Client();
        $kwargs = [
            'Bucket' => $s3BucketName,
        ];
        if ($s3BucketPrefix) {
            $kwargs['Prefix'] = $s3BucketPrefix;
        }
        $response = $client->listObjects($kwargs);

        $objects = $response['Contents'];
        if (!$objects) {
            return [];
        }

        $keys = [];
        foreach ($objects as $object) {
            if (substr($object['Key'], -strlen($extension)) === $extension) {
                array_push($keys, basename($object['Key']));
            }
        }
        return $keys;
    }

    /**
     * Push a file path to S3
     *  
     * @param string $path The full filesystem path to file
     * @return bool If the operation was successful
     * @since 1.0.0
     */
    private function push($path): bool
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3BucketName = Craft::parseEnv($settings->s3BucketName);
        $client = $this->getS3Client();
        $pathInfo = pathinfo($path);

        $key = $this->getAWSKey($pathInfo['basename']);
        $exists = $client->doesObjectExist($s3BucketName, $key);
        if (!$exists) {
            $client->putObject([
                'Bucket' => $s3BucketName,
                'Key' => $key,
                'SourceFile' => $path
            ]);
        } else {
            Craft::warning("Skipping push of '" . $key . "' as file already exists on S3", "remote-backup");
        }
        return true;
    }

    /**
     * Return the AWS key, including any prefix folders
     * 
     * @param string $filename The filename for the key
     * @return string The prefixed key
     * @since 1.0.0
     */
    private function getAWSKey($filename): string
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3BucketPrefix = Craft::parseEnv($settings->s3BucketPrefix);
        if ($s3BucketPrefix) {
            return $s3BucketPrefix . DIRECTORY_SEPARATOR . $filename;
        }
        return $filename;
    }

    /**
     * Return a useable S3 client object
     * 
     * @return S3Client The S3 client object
     * @since 1.0.0
     */
    private function getS3Client(): S3Client
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3AccessKey = Craft::parseEnv($settings->s3AccessKey);
        $s3SecretKey = Craft::parseEnv($settings->s3SecretKey);
        $s3RegionName = Craft::parseEnv($settings->s3RegionName);
        return S3Client::factory([
            'credentials' => array(
                'key'    => $s3AccessKey,
                'secret' => $s3SecretKey
            ),
            'version' => 'latest',
            'region'  => $s3RegionName
        ]);
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
        $message = "AWS Error";
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
