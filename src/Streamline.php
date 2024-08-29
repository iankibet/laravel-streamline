<?php

namespace Iankibet\Streamline;

use Illuminate\Support\Facades\Facade;

//void static function test()
class Streamline extends Facade
{

    protected static function getFacadeAccessor(): string
    {
        return 'streamline';
    }
}
