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
}
