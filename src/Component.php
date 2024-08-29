<?php

namespace Iankibet\Streamline;


use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract  class Component
{
    protected $isTesting = false;

    protected $authenticatedUser;
    protected $rules = [];
    protected $requestData = [];


    public function setRequestData(array $data)
    {
        $this->requestData = $data;
    }

    /**
     * @param mixed $authenticatedUser
     */
    public function setAuthenticatedUser($authenticatedUser): void
    {
        Auth::login($authenticatedUser);
    }

    public function asUser($user){
        Auth::login($user);
        return $this;
    }
    public function validate($rules = [])
    {
        if(empty($rules)){
            $rules = $this->rules;
        }
        $validator = validator($this->requestData, $rules);
        if ($validator->fails()) {
            $this->response($validator->errors(), 422);
        }
        return $validator->validated();
    }

    public function onMounted()
    {
        //return class instance
        return $this->toArray();
    }

    protected function response($data, $status = 200)
    {
        abort(response($data, $status));
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
