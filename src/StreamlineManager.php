<?php

namespace Iankibet\Streamline;

use Iankibet\Streamline\Features\SupportTesting\Testable;

class StreamlineManager
{
    public function test(string $name){
        $instance = app($name);
        return new Testable($instance);
    }

}
