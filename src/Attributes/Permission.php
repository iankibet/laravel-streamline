<?php

namespace Iankibet\Streamline\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_ALL)]
class Permission
{
    protected $permissions = [];
    public function __construct(...$args)
    {
        $this->permissions = $args;
    }

    public function getPermissions(){
        return $this->permissions;
    }

}
