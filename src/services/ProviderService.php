<?php

namespace weareferal\remotebackup\services;

use Throwable;

use weareferal\remotebackup\helpers\ZipHelper;
use weareferal\remotebackup\helpers\RemoteFile;


use Craft;
use craft\helpers\App;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;


/**
 * Provider interface
 *
 * Methods that all new providers must implement
 *
 * @since 1.0.0
 */
interface ProviderInterface
{
    public function isConfigured(): bool;
    public function isAuthenticated(): bool;
    public function list($filterExtensions): array;
    public function push($path);
    public function delete($key);
}


/**
 * Base Prodiver
 *
 * A remote cloud backend provider for sending and receiving files to and from
 */
abstract class ProviderService extends Component implements ProviderInterface
{

    protected $plugin;
    public $name;

    function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Provider is configured
     *
     * @return boolean whether this provider is properly configured
     * @since 1.1.0
     */
    public function isConfigured(): bool
    {
        return false;
    }

    /**
     * User is authenticated with the provider
     *
     * @return boolean
     * @since 1.1.0
     */
    public function isAuthenticated(): bool
    {
        // TODO: we should perform an actual authentication test
        return true;
    }

    /**
     * Return the remote database filenames
     *
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listDatabases(): array
    {
        Craft::info("Listing databases", "remote-backup");
        $sqlZipFiles = $this->list(".sql.zip");
        $sqlFiles = $this->list(".sql");
        
        // Merge both file types
        $remote_files = array_merge($sqlZipFiles, $sqlFiles);
        return RemoteFile::sort($remote_files);
    }

    /**
     * Return the remote volume filenames
     *
     * @return array An array of label/filename objects
     * @since 1.0.0
     */
    public function listVolumes(): array
    {
        Craft::info("Listing volumes", "remote-backup");
        $remote_files = $this->list(".zip");
        
        // Filter out .sql.zip files
        $remote_files = array_filter($remote_files, function($file) {
            return !str_ends_with($file->filename, ".sql.zip");
        });
        
        return RemoteFile::sort($remote_files);
    }

    /**
     * Push database to remote provider
     *
     * @return string The filename of the newly created Remote Sync
     * @since 1.0.0
     */
    public function pushDatabase()
    {
        $settings = $this->getSettings();
        $filename = $this->createFilename();
        $path = $this->createDatabaseDump($filename);
        Craft::info('Pushing new database:' . $path, 'remote-backup');

        try {
            $this->push($path);
        } catch (Throwable $e) {
            Craft::error("Database push failed, cleaning up local zip file:" . $path, "remote-backup");
            $this->rmPath($path);
            throw $e;
        }

        if (!property_exists($settings, 'keepLocal') || !$settings->keepLocal) {
            Craft::info('Deleting local database zip file:' . $path, 'remote-backup');
            $this->rmPath($path);
        }

        return $filename;
    }

    /**
     * Push all volumes to remote provider
     *
     * @return string The filename of the newly created synced file
     * @return null If no volumes exist
     * @since 1.0.0
     */
    public function pushVolumes(): string
    {
        $filename = $this->createFilename();
        $tmpDirName = $this->createTmpDirName();
        $tmpZipPath = $this->createTmpZipPath($filename);
        $time = microtime(true);
        $settings = $this->getSettings();
        Craft::info('Pushing new volumes:' . $filename, 'remote-backup');

        // Copy volume files to tmp folder
        try {
            $this->copyVolumeFilesToTmp($tmpDirName);
        } catch (Throwable $e) {
            Craft::error("Copying volume files locally failed, cleaning up tmp directory:" . $tmpDirName, "remote-backup");
            $this->rmDir($tmpDirName);
            throw $e;
        }

        // Zip up the locally copied volume files
        try {
            $this->createVolumesZip($tmpDirName, $tmpZipPath);
        } catch (Throwable $e) {
            Craft::error("Zipping local volume files failed, cleaning up tmp directory and zip file.", "remote-backup");
            Craft::error("- " . $tmpDirName, "remote-backup");
            Craft::error("- " . $tmpZipPath, "remote-backup");
            $this->rmDir($tmpDirName);
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        $this->rmDir($tmpDirName);

        // Push zip to remote destination
        try {
            $this->push($tmpZipPath);
        } catch (Throwable $e) {
            Craft::error("Volume push failed, cleaning up local volume zip file:"  . $tmpZipPath, "remote-backup");
            $this->rmPath($tmpZipPath);
            throw $e;
        }

        // Keep or delete the local zip file
        if (!property_exists($settings, 'keepLocal') || !$settings->keepLocal) {
            Craft::error('Deleting tmp local volume zip file:' . $tmpZipPath, 'remote-backup');
            $this->rmPath($tmpZipPath);
        }

        Craft::info("Volumes successfully pushed in : " . (string) (microtime(true) - $time)  . " seconds", "remote-backup");

        return $filename;
    }

    /**
     * Delete Database
     *
     * Delete a remote database .sql file
     *
     * @param string The filename to delete
     * @since 1.0.0
     */
    public function deleteDatabase($filename)
    {
        Craft::info("Deleting database: " . $filename, "remote-backup");
        $this->delete($filename);
    }

    /**
     * Delete Volume
     *
     * Delete a remote volume .zip file
     *
     * @param string The filename to delete
     * @since 1.0.0
     */
    public function deleteVolume($filename)
    {
        Craft::info("Deleting volume: " . $filename, "remote-backup");
        $this->delete($filename);
    }

    /**
     * Copy Volume Files To Tmp
     *
     * Copy all files across all volumes to a local temporary directory, ready
     * to be zipped.
     *
     * @return bool if the copy was successful
     */
    private function copyVolumeFilesToTmp($tmpDir): bool
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $time = microtime(true);

        if (count($volumes) <= 0) {
            Craft::debug("No volumes configured, skipping copy", "remote-backup");
            return false;
        }

        foreach ($volumes as $volume) {
            // Get all files in the volume.
            $fileSystem = $volume->getFs();
            $fsListings = $fileSystem->getFileList('/', true);

            // Create tmp location
            $tmpPath = $tmpDir . DIRECTORY_SEPARATOR  . $volume->handle;
            if (!file_exists($tmpPath)) {
                mkdir($tmpPath, 0777, true);
            }

            foreach ($fsListings as $fsListing) {
                $localDirname = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getDirname();
                $localPath = $tmpPath . DIRECTORY_SEPARATOR . $fsListing->getUri();

                if ($fsListing->getIsDir()) {
                    mkdir($localPath, 0777, $recursive = true);
                } else {
                    if ($localDirname && !file_exists($localDirname)) {
                        mkdir($localDirname, 0777, true);
                    }
                    $src = $fileSystem->getFileStream($fsListing->getUri());
                    $dst = fopen($localPath, 'w');
                    stream_copy_to_stream($src, $dst);
                    fclose($src);
                    fclose($dst);
                }
            }
        }

        Craft::debug("Volume successfully files copied to local tmp folder in " . (string) (microtime(true) - $time)  . " seconds", "remote-backup");

        return true;
    }

    /**
     * Create volumes zip
     *
     * Generates a temporary zip file of all volumes
     *
     * @param string $filename the filename to give the new zip
     * @return string $path the temporary path to the new zip file
     * @since 1.0.0
     */
    private function createVolumesZip($srcDir, $dstPath): string
    {
        if (file_exists($dstPath)) {
            $this->rmPath($dstPath);
        }

        ZipHelper::recursiveZip($srcDir, $dstPath);

        return $dstPath;
    }

    /**
     * Restore Volumes Zip
     *
     * Unzips volumes to a temporary path and then moves them to the "web"
     * folder.
     *
     * @param string $path the path to the zip file to restore
     * @since 1.0.0
     */
    private function restoreVolumesZip($zipPath)
    {
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $tmpDir = $this->createTmpDirName();

        // Unzip files to temp folder
        ZipHelper::unzip($zipPath, $tmpDir);

        // Copy all files to the volume
        $dirs = array_diff(scandir($tmpDir), array('.', '..'));
        foreach ($dirs as $dir) {
            Craft::debug("-- unzipped folder: " . $dir, "remote-backup");
            foreach ($volumes as $volume) {
                if ($dir == $volume->handle) {
                    // Send to volume backend
                    $absDir = $tmpDir . DIRECTORY_SEPARATOR . $dir;
                    $files = FileHelper::findFiles($absDir);
                    foreach ($files as $file) {
                        Craft::debug("-- " . $file, "remote-backup");
                        $fs = $volume->getFs();
                        if (is_file($file)) {
                            $relPath = str_replace($tmpDir . DIRECTORY_SEPARATOR . $volume->handle, '', $file);
                            $stream = fopen($file, 'r');
                            $fs->writeFileFromStream($relPath, $stream);
                            fclose($stream);
                        }
                    }
                }
            }
        }

        FileHelper::clearDirectory(Craft::$app->getPath()->getTempPath());
    }

    /**
     * Create Database Dump
     *
     * Uses the underlying Craft 3 "backup/db" function to create a new database
     * backup in local folder.
     *
     * @param string The file name to give the new backup
     * @return string The file path to the new database dump
     * @since 1.0.0
     */
    private function createDatabaseDump($filename): string
    {
        $path = $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.sql';
        Craft::$app->getDb()->backupTo($path);

        // Zip it up and delete the SQL file
        $zipPath = FileHelper::zip($path);
        unlink($path);

        return $zipPath;
    }

    /**
     * Create Filename
     *
     * Create a unique filename for a backup file. Based on getBackupFilePath():
     *
     * https://github.com/craftcms/cms/tree/master/src/db/Connection.php
     *
     * @return string The unique filename
     * @since 1.0.0
     */
    private function createFilename(): string
    {
        $currentVersion = 'v' . Craft::$app->getVersion();
        $systemName = FileHelper::sanitizeFilename(Craft::$app->getSystemName(), ['asciiOnly' => true]);

        // We use the environment variable directly instead of app->env. This
        // is because app->env gets set to the domain name when the env var
        // is missing, which breaks our formatting for the filename
        $systemEnv = App::env('CRAFT_ENVIRONMENT') ?: false;
        if ($systemEnv == false) {
            $systemEnv = "unknown";
        }

        $filename = ($systemName ? $systemName . '__' : '') . ($systemEnv ? $systemEnv . '__' : '') . gmdate('ymd_His') . '__' . strtolower(StringHelper::randomString(10)) . '__' . $currentVersion;
        $filename = mb_strtolower($filename);
        Craft::info("Creating filename: ".$filename, "remote-backup");

        return $filename;
    }

    /**
     * Create Tmp Dir Name
     *
     * Create a random temporary directory path
     *
     * NOTE: This doesn't actually create the directory
     *
     * @since 1.1.0
     * @return string a path to a random directory
     */
    private function createTmpDirName(): string
    {
        return Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . strtolower(StringHelper::randomString(10));
    }

    /**
     * Generate Temporary Zip Path
     *
     * Note: This doesn't actually create the zip file, just the path.
     */
    private function createTmpZipPath($filename): string
    {
        return $this->getLocalDir() . DIRECTORY_SEPARATOR . $filename . '.zip';
    }

    /**
     * Get Local Directory
     *
     * Return (or creates) the local directory we use to store temporary files.
     * This is a separate folder to the default Craft backup folder.
     *
     * @return string The path to the local directory
     * @since 1.0.0
     */
    protected function getLocalDir()
    {
        $dir = Craft::$app->path->getStoragePath() . "/" . $this->plugin->getHandle();
        if (!file_exists($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Filter By Extension
     *
     * Filter an array of filenames by their extension (.sql or .zip)
     *
     * @param string The file extension to filter by
     * @return array The filtered filenames
     */
    protected function filterByExtension($remote_files, $extension)
    {
        Craft::info("Filtering files by extension: " . $extension, "remote-backup");
        $filtered_remote_files = [];
        foreach ($remote_files as $remote_file) {
            if (substr($remote_file->filename, -strlen($extension)) === $extension) {
                Craft::info($remote_file->filename . " (filtered)", "remote-backup");
                array_push($filtered_remote_files, $remote_file);
            }
        }
        return $filtered_remote_files;
    }

    /**
     * Get Settings
     *
     * This gives any implementing classes the ability to adjust settings
     *
     * @since 1.0.0
     * @return object settings
     */
    protected function getSettings()
    {
        return $this->plugin->getSettings();
    }

    /**
     * Remove Directory
     *
     */
    private function rmDir($dir)
    {
        FileHelper::clearDirectory($dir);
        if (file_exists($dir)) {
            rmdir($dir);
        }
    }

    /**
     * Remote Path
     *
     */
    private function rmPath($path)
    {
        if (file_exists($path)) {
            unlink($path);
        }
    }
}
