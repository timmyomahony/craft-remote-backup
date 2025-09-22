<?php

namespace weareferal\remotebackup\helpers;

use Craft;
use DateTime;
use DateTimezone;
use weareferal\remotebackup\helpers\TimeHelper;

/**
 * Remote file
 *
 * An internal class data-type that helps us extract the relevent remote
 * file information from the file name.
 */
class RemoteFile
{
    public $filename;
    public $datetime;
    public $env;
    public $version;
    public $size;

    // Regex to capture/match:
    // - Site name
    // - Environment (optional and captured)
    // - Date (required and captured)
    // - Random string
    // - Version (captured)
    // - Extension
    private static $legacyRegex = '/^(?:[a-zA-Z0-9\-]+)\_(?:([a-zA-Z0-9\-]+)\_)?(\d{6}\_\d{6})\_(?:[a-zA-Z0-9]+)\_(?:([va-zA-Z0-9\.\-]+))\.(?:\w{2,10})$/';
    private static $regex = '/^(?:.+)\_\_(.+)\_\_(\d{6}\_\d{6})\_\_(?:.+)\_\_([va-zA-Z0-9\.\-]+)\.(?:\w{2,10})/';

    public function __construct($filename, $size)
    {
        Craft::info("Creating remote file object from path: ".$filename, "remote-core");

        // Extract values from filename
        preg_match(RemoteFile::$regex, $filename, $matches);
        if (count($matches) <= 0) {
            preg_match(RemoteFile::$legacyRegex, $filename, $matches);
        }

        // UTC to timezone
        $datetime = DateTime::createFromFormat(
            'ymd_Gis',
            $matches[2],
            new DateTimeZone("UTC"));
        $datetime->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $this->filename = $filename;
        $this->size = $size;
        $this->datetime = $datetime;
        $this->env = $matches[1];
        $this->version = $matches[3];
    }

    /**
     * Friendly Size
     *
     * A human-readable size of this file
     *
     * https://stackoverflow.com/a/2510459/396300
     */
    protected function friendlySize($precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($this->size, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Sort
     *
     * Sort an array of RemoteFiles
     */
    public static function sort($remote_files) {
        uasort($remote_files, function ($b1, $b2) {
            return $b1->datetime <=> $b2->datetime;
        });
        return array_reverse($remote_files);
    }

    /**
     * Serialize
     *
     * Serialize an array of RemoteFiles so that they can be converted to JSON
     */
    public static function serialize($remoteFiles, $dateFormat="Y-m-d") {
        $objects = [];
        foreach ($remoteFiles as $i => $file) {
            $timesince = TimeHelper::time_since($file->datetime->getTimestamp());
            $objects[$i] = [
                "filename" => $file->filename,
                "date" => $file->datetime->format($dateFormat),
                "time" => $file->datetime->format("H:i:s"),
                "timesince" => $timesince,
                "env" => $file->env,
                "version" => $file->version,
                "size" => $file->friendlySize()
            ];
        }
        return $objects;
    }
}
