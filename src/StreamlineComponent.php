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

    public function onMounted()
    {
        //return class instance
        return $this->toArray();
    }

    protected function toArray()
    {
        // get public properties of the class instance then return them as an array
        $data = [];
        $classVars = call_user_func('get_object_vars', $this);
        // remove the rules property
        unset($classVars['rules']);
        // get all the public functions of the class instance
        $methods = get_class_methods($this);
        $data['methods'] = [];
        $data['properties'] = $classVars;
        foreach ($methods as $method) {
            if ($method !== 'toArray' && $method !== 'onMounted') {
                $data['methods'][] = $method;
            }
        }
        return $data;
    }
}
