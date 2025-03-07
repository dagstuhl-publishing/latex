<?php

namespace Dagstuhl\Latex\Utilities;

abstract class Filesystem
{
    public static function get(string $path): ?string
    {
        try {
            $contents = class_exists('\Storage')
                ? \Storage::get($path)
                : @file_get_contents($path);

            // file_get_contents returns false on error
            if ($contents === false) {
                $contents = '';
            }
        }
        catch(\Exception $ex) {
            $contents = '';
        }

        return $contents;
    }

    public static function put(string $path, ?string $contents): void
    {
        try {
            if (class_exists('\Storage')) {
                \Storage::put($path, $contents ?? '');
            }
            else {
                file_put_contents($path, $contents);
            }
        } catch (\Exception $ex) { }
    }

    public static function delete(string $path): void
    {
        if (class_exists('\Storage')) {
            \Storage::delete($path);
        }
        else {
            try {
                @unlink($path);
            }
            catch(\Exception $ex) { }
        }
    }

    public static function copy(string $srcPath, string $targetPath): void
    {
        if (class_exists('\Storage')) {
            \Storage::copy($srcPath, $targetPath);
        }
        else {
            copy($srcPath, $targetPath);
        }
    }

    public static function move(string $oldPath, string $newPath): void
    {
        if (class_exists('\Storage')) {
            \Storage::move($oldPath, $newPath);
        }
        else {
            try {
                @rename($oldPath, $newPath);
            }
            catch(\Exception $ex) { }
        }
    }

    public static function files(string $path): array
    {
        try {
            if (class_exists('\Storage')) {
                return \Storage::files($path);
            }
            else {
                $filesOrFolders = scandir($path);

                $files = [];
                foreach($filesOrFolders as $file) {
                    if (!is_dir($path.'/'.$file)) {
                        $files[] = $file;
                    }
                }

                return $files;
            }
        }
        catch(\Exception $ex) {
            return [];
        }
    }

    public static function fileExists(string $path): bool
    {
        try {
            return class_exists('\Storage')
                ? \Storage::fileExists($path)
                : @file_exists($path);
        }
        catch(\Exception $ex) {
            return false;
        }
    }

    public static function storagePath(string $path): string
    {
        return function_exists('storage_path')
            ? storage_path('/app/'.$path)
            : $path;
    }

    public static function getResource(?string $path = ''): string
    {
        $contents = '';

        $path = preg_replace('/^\\\\/', '', $path);

        // allows to read resources from laravel storage
        if (
            function_exists('config')
            AND config('latex.paths.resources') !== NULL
            AND class_exists('\Storage')
        ) {
            $path = config('latex.paths.resources').'/'.$path;
            $path = str_replace('//', '/', $path);

            try {
                $contents = \Storage::get($path) ?? '';
            }
            catch(\Exception $ex) { }
        }
        // default: read from local resources folder
        else {
            $path = __DIR__.'/../../resources/'.$path;
            $path = str_replace('//', '/', $path);

            try {
                $contents = @file_get_contents($path);

                // file_get_contents returns false on error
                if ($contents === false) {
                    $contents = '';
                }
            }
            catch(\Exception $ex) { }
        }

        return $contents;
    }

    public static function deleteDirectory(string $path, bool $absolutePath = false): void
    {
        if (class_exists('\Storage') && !$absolutePath) {
            \Storage::deleteDirectory($path);
        }
        elseif (is_dir($path)) {
            $objects = scandir($path);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    if (is_dir($path. DIRECTORY_SEPARATOR .$object) && !is_link($path.'/'.$object)) {
                        self::deleteDirectory($path . DIRECTORY_SEPARATOR . $object);
                    }
                    else {
                        unlink($path . DIRECTORY_SEPARATOR . $object);
                    }
                }
            }
            rmdir($path);
        }
    }

    public static function makeDirectory(string $path, bool $absolutePath = false): void
    {
        if (class_exists('\Storage') AND !$absolutePath) {
            \Storage::makeDirectory($path);
        }
        else {
            try {
                @mkdir($path, 0777, true);
            }
            catch (\Exception $ex) { }
        }
    }
}