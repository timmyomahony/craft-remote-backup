<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use BackblazeB2\Client;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\Provider;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\exceptions\ProviderException;


/**
 * Backblaze provider
 * 
 * https://github.com/gliterd/backblaze-b2
 */
class BackblazeB2Provider extends RemoteBackupService implements Provider
{
    /**
     * Is Configured
     * 
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
     */
    public function isConfigured(): bool
    {
        $settings = RemoteBackup::getInstance()->settings;
        return isset($settings->b2MasterKeyID) &&
            isset($settings->b2MasterAppKey);
    }

    /**
     * Is Authenticated
     * 
     * @return boolean whether this provider is properly authenticated
     * @since 1.1.0
     * @todo currently we assume that if you have the keys you are 
     * authenitcated. We should do a check here
     */
    public function isAuthenticated(): bool
    {
        return true;
    }

    /**
     * Return B2 files
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of filenames returned from B2
     * @since 1.1.0
     */
    public function list($filterExtension): array
    {
        $settings = RemoteBackup::getInstance()->settings;
        $b2BucketName = Craft::parseEnv($settings->b2BucketName);
        $b2BucketPath = Craft::parseEnv($settings->b2BucketPath);
        $client = $this->getClient();

        $options = [
            'BucketName' => $b2BucketName
        ];
        if ($b2BucketPath) {
            $options['Prefix'] = $b2BucketPath;
        }

        $files = $client->listFiles($options);

        $filenames = [];
        foreach ($files as $file) {
            array_push($filenames, basename($file->getName()));
        }

        if ($filterExtension) {
            return $this->filterByExtension($filenames, $filterExtension);
        }

        return $filenames;
    }

    /**
     * Push a file path to B2
     *  
     * @param string $path The full filesystem path to file
     * @since 1.1.0
     */
    public function push($path)
    {
        $settings = RemoteBackup::getInstance()->settings;
        $b2BucketName = Craft::parseEnv($settings->b2BucketName);
        $client = $this->getClient();
        $pathInfo = pathinfo($path);
        $filename = $this->getPrefixedFilename($pathInfo['basename']);

        $client->upload([
            'BucketName' => $b2BucketName,
            'FileName' => $filename,
            'Body' => fopen($path, 'r')
        ]);
    }

    /**
     * Delete a remote B2 file
     * 
     * @since 1.1.0
     */
    public function delete($filename)
    {
        $settings = RemoteBackup::getInstance()->settings;
        $b2BucketName = Craft::parseEnv($settings->b2BucketName);
        $b2BucketPath = Craft::parseEnv($settings->b2BucketPath);
        $client = $this->getClient();
        $filename = $this->getPrefixedFilename($filename);

        $options = [
            'BucketName' => $b2BucketName,
            'FileName' => $filename
        ];
        if ($b2BucketPath) {
            $options['Prefix'] = $b2BucketPath;
        }

        $exists = $client->fileExists($options);
        if (!$exists) {
            throw new ProviderException("B2 file does not exist");
        }

        $client->deleteFile($options);
    }

    /**
     * Return the AWS key, including any prefix folders
     * 
     * @param string $key The key for the key
     * @return string The prefixed key
     * @since 1.1.0
     */
    private function getPrefixedFilename($key): string
    {
        $settings = RemoteBackup::getInstance()->settings;
        $b2BucketPath = Craft::parseEnv($settings->b2BucketPath);
        if ($b2BucketPath) {
            return $b2BucketPath . DIRECTORY_SEPARATOR . $key;
        }
        return $key;
    }

    /**
     * Return a useable B2 client object
     * 
     * @return Client The B2 client object
     * @since 1.1.0
     */
    private function getClient(): Client
    {
        $settings = RemoteBackup::getInstance()->settings;
        $b2MasterKeyID = Craft::parseEnv($settings->b2MasterKeyID);
        $b2MasterAppKey = Craft::parseEnv($settings->b2MasterAppKey);
        return new Client($b2MasterKeyID, $b2MasterAppKey, []);
    }
}
