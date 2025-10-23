<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Google_Client;
use Google_Exception;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;

use weareferal\remotebackup\services\ProviderService;
use weareferal\remotebackup\exceptions\ProviderException;
use weareferal\remotebackup\helpers\RemoteFile;


/**
 * Google Drive Provider
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
class GoogleDriveProvider extends ProviderService
{
    public $name = "Google Drive";

    /**
     * Is Configured
     *
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
     */
    public function isConfigured(): bool
    {
        return isset($this->plugin->settings->googleClientId) &&
            isset($this->plugin->settings->googleClientSecret) &&
            isset($this->plugin->settings->googleProjectName) &&
            isset($this->plugin->settings->googleAuthRedirect);
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
     * https://github.com/googleapis/google-api-php-client/blob/main/examples/large-file-upload.php
     *
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of files from Google Drive
     * @since 1.1.0
     */
    public function list($filterExtension): array
    {
        $googleDriveFolderId = Craft::parseEnv($this->plugin->settings->googleDriveFolderId);
        $service = new Google_Service_Drive($this->getClient());

        $q = "name contains '${filterExtension}'";
        if ($googleDriveFolderId) {
            $q = "'${googleDriveFolderId}' in parents and " . $q;
        }


        $params = array(
            'corpora' => 'allDrives',
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => 'drive',
            'q' => $q,
            'fields' => 'files(name, size)'
        );

        Craft::info("Listing files on Google Drive");
        Craft::info("Google Drive folder ID: [".$googleDriveFolderId."]", "remote-backup");
        Craft::info("Google Drive query: [".$q."]", "remote-backup");
        Craft::info("Google Drive files:'".$q."'", "remote-backup");

        try {
            $files = $service->files->listFiles($params);
        } catch (Google_Exception $exception) {
            throw new ProviderException($exception->getMessage());
        }

        $remote_files = [];
        foreach ($files as $file) {
            Craft::info(" - ".$file->getName(), "remote-backup");
            array_push($remote_files, new RemoteFile($file->getName(), $file->getSize()));
        }

        return $remote_files;
    }

    /**
     * Push a file to Google Drive
     *
     * @param string $path The full filesystem path to file
     * @since 1.0.0
     */
    public function push($localPath)
    {
        $mimeType = mime_content_type($localPath);
        $googleDriveFolderId = Craft::parseEnv($this->plugin->settings->googleDriveFolderId);
        $driveFile = new Google_Service_Drive_DriveFile();
        $driveFile->setName(basename($localPath));
        # Upload to specified folder
        $googleDriveFolderId = Craft::parseEnv($this->plugin->settings->googleDriveFolderId);
        if ($googleDriveFolderId) {
            $driveFile->setParents([$googleDriveFolderId]);
        }

        // Set chunk size
        $chunkSizeBytes = 1 * 1024 * 1024;

        Craft::info("Pushing file to Google Drive");
        Craft::info("Google Drive remote file path: [".$googleDriveFolderId."/".$driveFile->getName()."]", "remote-backup");

        // Call the API with the media upload, defer so it doesn't immediately return.
        $client = $this->getClient();
        $client->setDefer(true);
        $client->setUseBatch(true);
        $service = new Google_Service_Drive($client);
        $request = $service->files->create($driveFile, [
            'supportsAllDrives' => true
        ]);

        // Create a media file upload to represent our upload process.
        $media = new \Google\Http\MediaFileUpload(
            $client,
            $request,
            $mimeType,
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($localPath));

        // Upload the various chunks. $status will be false until the process is
        // complete.
        $status = false;
        $handle = fopen($localPath, "rb");
        while (!$status && !feof($handle)) {
            // read until you get $chunkSizeBytes from TESTFILE
            // fread will never return more than 8192 bytes if the stream is read buffered and it does not represent a plain file
            // An example of a read buffered file is when reading from a URL
            $chunk = $this->readVideoChunk($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }
        // The final value of $status will be the data from the API for the object
        // that has been uploaded.
        $result = false;
        if ($status != false) {
            $result = $status;
        }
        fclose($handle);
    }

    private function readVideoChunk($handle, $chunkSize)
    {
        $byteCount = 0;
        $giantChunk = "";
        while (!feof($handle)) {
            // fread will never return more than 8192 bytes if the stream is read
            // buffered and it does not represent a plain file
            $chunk = fread($handle, 8192);
            $byteCount += strlen($chunk);
            $giantChunk .= $chunk;
            if ($byteCount >= $chunkSize) {
                return $giantChunk;
            }
        }
        return $giantChunk;
    }

    /**
     * Delete a remote Google Drive file
     *
     * https://developers.google.com/drive/api/v3/reference/files/delete
     *
     * @param $filename string the filename to delete
     * @return boolean if successful
     * @throws ProviderException if there was an API error deleting the file
     * @since 1.0.0
     */
    public function delete($filename)
    {
        $fileId = $this->getFileID($filename);
        $service = new Google_Service_Drive($this->getClient());
        $service->files->delete($fileId, [
            'supportsAllDrives' => true
        ]);
        return true;
    }

    /**
     * Get file ID
     *
     * Search for the file by filename across all drives in Google Drive
     *
     * https://developers.google.com/drive/api/v3/reference/files/list
     *
     * @param $filename string the filename we are searching for
     * @return string the Google Drive fileId of the found file
     * @throws ProviderException if there was an API error finding the file
     * @since 1.0.0
     */
    private function getFileID($filename)
    {
        $googleDriveFolderId = Craft::parseEnv($this->plugin->settings->googleDriveFolderId);
        $service = new Google_Service_Drive($this->getClient());
        $q = "name = '${filename}'";
        if ($googleDriveFolderId) {
            $q = "'${googleDriveFolderId}' in parents and " . $q;
        }
        return $service->files->listFiles([
            'corpora' => 'allDrives',
            'includeItemsFromAllDrives' => true,
            'supportsAllDrives' => true,
            'spaces' => 'drive',
            'q' => $q
        ])[0]->id;
    }

    /**
     * Get token path
     *
     * Return the absolute path to the saved Google API credential json file
     *
     * @return string absolute path
     * @since 1.0.0
     */
    public function getTokenPath(): string
    {
        return Craft::$app->path->getStoragePath()
            . DIRECTORY_SEPARATOR
            . $this->plugin->getHandle()
            . DIRECTORY_SEPARATOR
            . "google-drive-{$this->plugin->getHandle()}-token"
            . ".json";
    }

    /**
     * Get client
     *
     * Return a Google Drive client
     *
     * @return Client The Google SDK client object
     * @since 1.0.0
     */
    function getClient(): Google_Client
    {
        $client = new Google_Client();
        $client->setScopes(Google_Service_Drive::DRIVE);
        $config = [
            'client_id' => Craft::parseEnv($this->plugin->settings->googleClientId),
            "project_id" => Craft::parseEnv($this->plugin->settings->googleProjectName),
            "auth_uri" => "https://accounts.google.com/o/oauth2/auth",
            "token_uri" => "https://oauth2.googleapis.com/token",
            "auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
            "client_secret" => Craft::parseEnv($this->plugin->settings->googleClientSecret),
            "redirect_uris" => [
                Craft::parseEnv($this->plugin->settings->googleAuthRedirect)
            ]
        ];
        $client->setAuthConfig($config);
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        $tokenPath = $this->getTokenPath();
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        return $client;
    }
}
