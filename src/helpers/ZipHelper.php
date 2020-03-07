<?php

namespace weareferal\backup\helpers;

use Craft;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

/**
 * Zip file
 * 
 * https://stackoverflow.com/a/1334949
 */
class ZipHelper
{
    public static function recursiveZip($srcPath, $dstPath)
    {
        $zip = new ZipArchive();
        $zip->open($dstPath, ZIPARCHIVE::CREATE);
        $srcPath = str_replace('\\', DIRECTORY_SEPARATOR, realpath($srcPath));

        if (is_dir($srcPath)) {
            $paths = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcPath), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($paths as $path) {
                $path = str_replace('\\', DIRECTORY_SEPARATOR, $path);
                // Ignore "." and ".." folders
                if (in_array(substr($path, strrpos($path, DIRECTORY_SEPARATOR) + 1), array('.', '..'))) {
                    continue;
                }
                $path = realpath($path);
                if (is_dir($path)) {
                    $relPath = str_replace($srcPath . DIRECTORY_SEPARATOR, '', $path . DIRECTORY_SEPARATOR);
                    $zip->addEmptyDir($relPath);
                } else if (is_file($path)) {
                    $relPath = str_replace($srcPath . DIRECTORY_SEPARATOR, '', $path);
                    $zip->addFile($path, $relPath);
                }
            }
        } else if (is_file($srcPath)) {
            $zip->addFromString(basename($srcPath), file_get_contents($srcPath));
        }

        return $zip->close();
    }

    public static function unzip($srcPath, $dstPath)
    {
        $zip = new ZipArchive();
        $zip->open($srcPath);
        $zip->extractTo($dstPath);
        return $zip->close();
    }
}
