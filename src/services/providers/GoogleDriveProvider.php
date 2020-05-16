<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Google_Client;
use Google_Service_Drive;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\Provider;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\exceptions\ProviderException;



class GoogleDriveProvider extends RemoteBackupService implements Provider
{
    private $tokenFileName = "google-drive-remote-backup-token";

    /**
     * Is Configured
     * 
     * @return boolean whether this provider is properly configured
     */
    public function isConfigured(): bool
    {
        $settings = RemoteBackup::getInstance()->settings;
        return isset($settings->googleClientId) &&
            isset($settings->googleClientSecret) &&
            isset($settings->googleProjectName) &&
            isset($settings->googleAuthRedirect);
    }

    /**
     * Is Authenticated
     * 
     * @return boolean whether this provider is properly authenticated
     */
    public function isAuthenticated(): bool
    {
        $client = $this->getClient();
        $isExpired = $client->isAccessTokenExpired();
        if ($isExpired) {
            // Try refresh
            $isExpired = $client->getRefreshToken() == null;
        }
        return ! $isExpired;
    }

    /**
     * Return Google Drive files
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of files from Google Drive
     * @since 1.0.0
     */
    public function list($filterExtension = null): array
    {
        return [];
    }

    /**
     * Push a file to Google Drive
     *  
     * @param string $path The full filesystem path to file
     * @since 1.0.0
     */
    public function push($path)
    {
    }

    /**
     * Delete a remote Google Drive file
     * 
     * @since 1.0.0
     */
    public function delete($key)
    {
    }

    public function getTokenPath() {
        return Craft::$app->path->getStoragePath()
        . DIRECTORY_SEPARATOR
        . "remote-backup"
        . DIRECTORY_SEPARATOR
        . $this->tokenFileName
        . ".json";
    }

    /**
     * Return a Google Drive client
     * 
     * @return Client The Google SDK client object
     * @since 1.1.0
     */
    function getClient()
    {
        $settings = RemoteBackup::getInstance()->settings;
        $client = new Google_Client();
        $client->setApplicationName('Craft Remote Backup');
        $client->setScopes(Google_Service_Drive::DRIVE_FILE);
        $config = [
            'client_id' => Craft::parseEnv($settings->googleClientId),
            "project_id" => Craft::parseEnv($settings->googleProjectName),
            "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
            "token_uri" => "https://oauth2.googleapis.com/token",
            "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
            "client_secret" => Craft::parseEnv($settings->googleClientSecret),
            "redirect_uris" => [
                Craft::parseEnv($settings->googleAuthRedirect)
            ]
        ];
        $client->setAuthConfig($config);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenPath = $this->getTokenPath();
        Craft::debug($tokenPath, 'remote-backup');
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        return $client;
    }
}
