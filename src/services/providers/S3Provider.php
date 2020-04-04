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
    public function listDatabaseBackups(): array
    {
        try {
            return $this->list("sql");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    public function listVolumeBackups(): array
    {
        try {
            return $this->list("zip");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    private function list($extension): array
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
     * Push local volume backups from backup folder to S3
     * 
     * @return array An array of paths that were pushed
     */
    public function pushVolumeBackups(): array
    {
        try {
            return $this->push("zip");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    /**
     * Push local database backups from backup folder to S3
     * 
     * @return array An array of paths that were pushed
     */
    public function pushDatabaseBackups(): array
    {
        try {
            return $this->push("sql");
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    /**
     * Push all local backups of a particular extension to S3
     * 
     * @param string $extension The extension to target (sql or zip)
     * @return array An array of paths that were pushed successfully
     */
    private function push($extension): array
    {
        $settings = RemoteBackup::getInstance()->settings;
        $s3BucketName = Craft::parseEnv($settings->s3BucketName);
        $client = $this->getS3Client();

        $filenames = $this->getBackupFilenames($extension);
        $backups = $this->parseBackupFilenames($filenames);
        $paths = [];
        foreach ($backups as $backup) {
            $path = $backup->path();
            $key = $this->getAWSKey($backup->filename);
            $exists = $client->doesObjectExist($s3BucketName, $key);
            if (!$exists) {
                $client->putObject([
                    'Bucket' => $s3BucketName,
                    'Key' => $key,
                    'SourceFile' => $path
                ]);
                array_push($paths, $path);
            } else {
                Craft::warning("Skipping push of '" . $key . "' as file already exists on S3", "craft-sync");
            }
        }
        return $paths;
    }

    /**
     * Delete remote backups
     * 
     * @param array $backups An array of backups to delete
     * @return array An array of paths that were deleted
     */
    public function deleteRemoteBackups($backups): array
    {
        try {
            $settings = RemoteBackup::getInstance()->settings;
            $s3BucketName = Craft::parseEnv($settings->s3BucketName);
            $client = $this->getS3Client();
            $paths = [];
            foreach ($backups as $backup) {
                $key = $this->getAWSKey($backup->filename);
                $exists = $client->doesObjectExist($s3BucketName, $key);
                if ($exists) {
                    $client->deleteObject([
                        'Bucket' => $s3BucketName,
                        'Key'    => $key
                    ]);
                    array_push($paths, $backup->path());
                } else {
                    Craft::warning("Could\'nt delete '" . $key . "' as it doesn't exist on S3", "craft-sync");
                }
            }
            return $paths;
        } catch (AwsException $exception) {
            throw new ProviderException($this->createErrorMessage($exception));
        }
    }

    /**
     * Return the AWS key, including any prefix folders
     * 
     * @param string $filename The filename for the key
     * @return string The prefixed key
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
