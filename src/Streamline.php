<?php

namespace Iankibet\Streamline;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Iankibet\Streamline\StreamlineManager
 * @method static test(string $name)
 */
class Streamline extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'streamline';
    }
}
