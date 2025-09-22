<?php

namespace weareferal\remotebackup\services\providers;

use Craft;

use Kunnu\Dropbox\Dropbox;
use Kunnu\Dropbox\DropboxApp;
use Kunnu\Dropbox\DropboxFile;

use weareferal\remotebackup\services\ProviderService;
use weareferal\remotebackup\services\ProviderInterface;
use weareferal\remotebackup\helpers\RemoteFile;


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
class DropboxProvider extends ProviderService implements ProviderInterface
{
    public $name = "Dropbox";

    /**
     * Is Configured
     *
     * @return boolean whether this provider is properly configured
     * @since 1.3.0
     */
    public function isConfigured(): bool
    {
        return isset($this->plugin->settings->dropboxAppKey) &&
            isset($this->plugin->settings->dropboxSecretKey) &&
            isset($this->plugin->settings->dropboxAccessToken);
    }

    /**
     * Return Dropbox files
     *
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Working-with-files#list-folder-contents
     *
     * @param string $extension The file extension to filter the results by
     * @return array[string] An array of filenames returned from Dropbox
     * @since 1.3.0
     * @todo filter results via the API as opposed to our own custom filtering
     */
    public function list($filterExtension): array
    {
        $dropboxFolder = Craft::parseEnv($this->plugin->settings->dropboxFolder);

        $dstPath = "/";
        if ($dropboxFolder) {
            $dstPath = $dropboxFolder;
        }

        $dropbox = $this->getClient();
        $folder = $dropbox->listFolder($dstPath);
        $files = $folder->getItems();

        $remote_files = [];
        foreach ($files->all() as $file) {
            array_push($remote_files, new RemoteFile($file->getName(), $file->getSize()));
        }

        if ($filterExtension) {
            return $this->filterByExtension($remote_files, $filterExtension);
        }

        return $remote_files;
    }

    /**
     * Push a file to Dropbox folder
     *
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Upload-and-Download-Files#auto-file-upload
     *
     * @param string $path The full filesystem path to file
     * @since 1.3.0
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
     * Pull a file from Dropbox folder
     *
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Upload-and-Download-Files#download-file
     *
     * @since 1.3.0
     */
    public function pull($filename, $localPath)
    {
        $dstPath = $this->getDestinationPath($filename);
        $dropbox = $this->getClient();
        $file = $dropbox->download($dstPath);
        $contents = $file->getContents();
        file_put_contents($localPath, $contents);
    }

    /**
     * Delete a remote Dropbox file
     *
     * https://github.com/kunalvarma05/dropbox-php-sdk/wiki/Working-with-files#delete-filefolder
     *
     * @since 1.3.0
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
     * @since 1.3.0
     */
    private function getDestinationPath($filename): string
    {
        $dropboxFolder = Craft::parseEnv($this->plugin->settings->dropboxFolder);
        if ($dropboxFolder) {
            return $dropboxFolder . DIRECTORY_SEPARATOR . $filename;
        }
        return '/' . $filename;
    }

    /**
     * Return a useable Dropbox client object
     *
     * @return Dropbox The Dropbox service object
     * @since 1.3.0
     */
    private function getClient()
    {
        $dropboxAppKey = Craft::parseEnv($this->plugin->settings->dropboxAppKey);
        $dropboxSecretKey = Craft::parseEnv($this->plugin->settings->dropboxSecretKey);
        $dropboxAccessToken = Craft::parseEnv($this->plugin->settings->dropboxAccessToken);
        $app = new DropboxApp($dropboxAppKey, $dropboxSecretKey, $dropboxAccessToken);
        return new Dropbox($app);
    }
}
