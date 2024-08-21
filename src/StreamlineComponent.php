<?php

namespace Iankibet\Streamline;


abstract  class StreamlineComponent
{
    protected $rules = [];
    public function validate($rules = [])
    {
        if(empty($rules)){
            $rules = $this->rules;
        }
        return request()->validate($rules);
    }
}
