<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\Provider;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\exceptions\ProviderException;


/**
 * 
 * Bear in mind that the version of this PHP Client library (v2) is different
 * to the actual Google Drive API (which is v3). In other words, we're using
 * v2 of this client library to access v3 of the Google Drive API. Confusing.
 * 
 * Furthermore, the Google Drive docs are terrible, so here are some of the
 * slightly more relevent links:
 * 
 * https://developers.google.com/drive/api/v3/quickstart/php
 * https://developers.google.com/resources/api-libraries/documentation/drive/v3/php/latest
 * https://github.com/googleapis/google-api-php-client/tree/master/src/Google/Service
 * https://github.com/googleapis/google-api-php-client-services/blob/master/src/Google/Service/Drive.php
 */
class GoogleDriveProvider extends RemoteBackupService implements Provider
{
    private $tokenFileName = "google-drive-remote-backup-token";

    /**
     * Is Configured
     * 
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
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
     * @since 1.1.0
     */
    public function isAuthenticated(): bool
    {
        $client = $this->getClient();
        $isExpired = $client->isAccessTokenExpired();
        if ($isExpired) {
            // Try refresh
            $isExpired = $client->getRefreshToken() == null;
        }
        return !$isExpired;
    }

    /**
     * Return Google Drive files
     * 
     * https://github.com/googleapis/google-api-php-client-services/blob/82f6213007f4d2acccdafd1372fd88447f728008/src/Google/Service/Drive/Resource/Files.php#L230
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of files from Google Drive
     * @since 1.1.0
     */
    public function list($filterExtension = null): array
    {
        $settings = RemoteBackup::getInstance()->settings;
        $googleDriveFolderId = Craft::parseEnv($settings->googleDriveFolderId);
        $service = new Google_Service_Drive($this->getClient());

        $q = "name contains '${filterExtension}'";
        if ($googleDriveFolderId) {
            $q = "'${googleDriveFolderId}' in parents and " . $q;
        }

        $params = array(
            'spaces' => 'drive',
            'q' => $q
        );

        $results = $service->files->listFiles($params);

        $filenames = [];
        foreach ($results as $result) {
            array_push($filenames, $result->getName());
        }

        return $filenames;
    }

    /**
     * Push a file to Google Drive
     *  
     * @param string $path The full filesystem path to file
     * @since 1.1.0
     */
    public function push($path)
    {
        $mimeType = mime_content_type($path);
        $settings = RemoteBackup::getInstance()->settings;
        $googleDriveFolderId = Craft::parseEnv($settings->googleDriveFolderId);

        $service = new Google_Service_Drive($this->getClient());
        $gFile = new Google_Service_Drive_DriveFile();
        $gFile->setName(basename($path));

        # Upload to specified folder
        if ($googleDriveFolderId) {
            $gFile->setParents([$googleDriveFolderId]);
        }

        $service->files->create(
            $gFile,
            array(
                'data' => file_get_contents($path),
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'supportsAllDrives' => true
            )
        );
    }

    /**
     * Delete a remote Google Drive file
     * 
     * @since 1.1.0
     */
    public function delete($key)
    {
    }

    public function getTokenPath()
    {
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
    function getClient(): Google_Client
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
