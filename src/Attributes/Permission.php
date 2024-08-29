<?php

namespace Iankibet\Streamline\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS, Attribute::TARGET_METHOD)]
class Permission
{
    public function __construct(protected $permission)
    {
    }

}
