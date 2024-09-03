<?php

namespace Iankibet\Streamline\Features\Support;

use Illuminate\Support\Str;

class StreamlineSupport
{
    public static function convertStreamToClass(string $stream): string
    {
        $streamCollection = collect(explode('/', $stream));

        $stream = $streamCollection->map(function ($item) {
            return Str::studly(str_replace('-', ' ', $item));
        })->implode('\\');
        $classPostfix = config('streamline.class_postfix', '');
        if (str_ends_with($stream, $classPostfix)) {
            $classPostfix = '';
        }
        return config('streamline.class_namespace') . '\\' . $stream . $classPostfix;
    }

    public static function getStreamlineClasses(): array
    {
        $streamlineClasses = [];
        $streamlinePath = config('streamline.class_namespace');
        $streamlinePath = str_replace('\\', '/', $streamlinePath);
        $replacedNamespace = $streamlinePath;
        $streamlinePath = str_replace('App/', '', $streamlinePath);
        $streamlinePath = app_path($streamlinePath);
        $files = self::getDirFilesRecursive($streamlinePath);
        foreach ($files as $file) {
            if (str_ends_with($file, '.php')) {
                $file = str_replace(app_path(), 'App', $file);
                $namespace = config('streamline.class_namespace');
                $namespace = str_replace('\\', '/', $namespace);
                $file = str_replace($namespace.'/', '', $file);
                $streamlineClasses[] = str_replace('.php', '', $file);
            }
        }
        return $streamlineClasses;
    }

    protected static function getDirFiles($dir)
    {
        $files = scandir($dir);
        $files = array_diff($files, ['.', '..']);
        $files = array_map(function ($file) use ($dir) {
            return $dir . '/' . $file;
        }, $files);
        return $files;
    }

    protected static function getDirFilesRecursive($dir)
    {
        $files = self::getDirFiles($dir);
        foreach ($files as $file) {
            if (is_dir($file)) {
                $files = array_merge($files, self::getDirFilesRecursive($file));
            }
        }
        return $files;
    }
}
