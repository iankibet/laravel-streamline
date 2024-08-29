<?php

namespace Iankibet\Streamline\Features\Testing;

class Testable
{
    public function __construct(protected $instance)
    {
    }

    public function actingAs($user)
    {
        $this->instance->setAuthenticatedUser($user);
        return $this;
    }

    public function setRequestData(array $data)
    {
        $this->instance->setRequestData($data);
        return $this;
    }

    public function call(string $method, array $params = [])
    {
        return $this->instance->{$method}(...$params);
    }
}
