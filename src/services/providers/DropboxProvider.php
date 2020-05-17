<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;

use weareferal\remotebackup\RemoteBackup;
use weareferal\remotebackup\services\Provider;
use weareferal\remotebackup\services\RemoteBackupService;
use weareferal\remotebackup\exceptions\ProviderException;


/**
 * Dropbox Provider
 * 
 * This uses a community/unofficial SDK but it has great documentation
 * 
 * https://github.com/kunalvarma05/dropbox-php-sdk
 * 
 * @todo add regex to the folder path setting so users can't enter incorrect
 * values
 */
class DropboxProvider extends RemoteBackupService implements Provider
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
        return isset($settings->dropboxAppKey) &&
            isset($settings->dropboxSecretKey) &&
            isset($settings->dropboxAccessToken);
    }

    /**
     * Is Authenticated
     * 
     * @return boolean whether this provider is properly authenticated
     * @todo currently we assume that if you have the keys you are 
     * authenitcated. We should do a check here
     * @since 1.1.0
     */
    public function isAuthenticated(): bool
    {
        return true;
    }

    /**
     * Return Dropbox files
     * 
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of filenames returned from Dropbox
     * @since 1.1.0
     * @todo filter results via the API as opposed to our own custom filtering
     */
    public function list($filterExtension = null): array
    {
        $settings = RemoteBackup::getInstance()->settings;
        $dropboxFolder = Craft::parseEnv($settings->dropboxFolder);

        $dstPath = "/";
        if ($dropboxFolder) {
            $dstPath = $dropboxFolder;
        }

        $dropbox = $this->getClient();
        $folder = $dropbox->listFolder($dstPath);
        $items = $folder->getItems();

        $filenames = [];
        foreach ($items->all() as $item) {
            array_push($filenames, $item->getName());
        }

        if ($filterExtension) {
            return $this->filterByExtension($filenames, $filterExtension);
        }

        return $filenames;
    }

    /**
     * Push a file to Dropbox folder
     *  
     * @param string $path The full filesystem path to file
     * @since 1.1.0
     */
    public function push($srcPath)
    {
        $srcPathInfo = pathinfo($srcPath);
        $dstPath = $this->getDestinationPath($srcPathInfo['basename']);
        $dropbox = $this->getClient();
        $dropboxFile = new DropboxFile($srcPath);
        $dropbox->upload($dropboxFile, $dstPath, []);
    }

    /**
     * Delete a remote Dropbox file
     * 
     * @since 1.1.0
     */
    public function delete($filename)
    {
        $dstPath = $this->getDestinationPath($filename);
        $dropbox = $this->getClient();
        $dropbox->delete($dstPath);
    }

    /**
     * Return the destination file path, including any prefix folder. The
     * path must be of the format "/file.txt" or /folder/file.txt" (with a 
     * opening slash)
     * 
     * @param string $filename The filename for the filename
     * @return string The prefixed filename
     * @since 1.1.0
     */
    private function getDestinationPath($filename): string
    {
        $settings = RemoteBackup::getInstance()->settings;
        $dropboxFolder = Craft::parseEnv($settings->dropboxFolder);
        if ($dropboxFolder) {
            return $dropboxFolder . DIRECTORY_SEPARATOR . $filename;
        }
        return '/' . $filename;
    }

    /**
     * Return a useable Dropbox client object
     * 
     * @return Dropbox The Dropbox service object
     * @since 1.1.0
     */
    private function getClient()
    {
        $settings = RemoteBackup::getInstance()->settings;
        $dropboxAppKey = Craft::parseEnv($settings->dropboxAppKey);
        $dropboxSecretKey = Craft::parseEnv($settings->dropboxSecretKey);
        $dropboxAccessToken = Craft::parseEnv($settings->dropboxAccessToken);
        $app = new DropboxApp($dropboxAppKey, $dropboxSecretKey, $dropboxAccessToken);
        return new Dropbox($app);
    }
}
