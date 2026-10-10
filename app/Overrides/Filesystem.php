<?php

namespace App\Overrides;

use Illuminate\Filesystem\Filesystem as BaseFilesystem;

/**
 * PHP 8.4 compatibility override: tempnam() ignores the directory argument
 * when sys_temp_dir is not configured, emitting E_NOTICE and creating the
 * file in /tmp instead. Laravel's Filesystem::replace() uses tempnam(), which
 * causes fatal ErrorException on every compiled view write.
 *
 * This override creates the temp file directly in the target directory,
 * bypassing tempnam() entirely.
 */
class Filesystem extends BaseFilesystem
{
    public function replace($path, $content, $mode = null)
    {
        clearstatcache(true, $path);

        $path = realpath($path) ?: $path;

        $dir = dirname($path);
        $attempts = 0;
        $tempPath = false;

        do {
            $tempPath = $dir . '/' . basename($path) . '.tmp' . mt_rand(100000, 999999);
            $fp = @fopen($tempPath, 'x');
        } while (! $fp && ++$attempts < 5);

        if (! $fp) {
            throw new \RuntimeException("Unable to create temp file for {$path}");
        }

        if (! is_null($mode)) {
            @chmod($tempPath, $mode);
        } else {
            @chmod($tempPath, 0777 - umask());
        }

        fwrite($fp, $content);
        fclose($fp);

        rename($tempPath, $path);
    }
}
