<?php

namespace Iankibet\Streamline;


use Iankibet\Streamline\Attributes\Validate;
use Illuminate\Support\Facades\Auth;

abstract  class Stream
{
    protected $isTesting = false;

    protected $authenticatedUser;
    protected $rules = [];
    protected $requestData = [];

    protected $action;


    public function setRequestData(array $data)
    {
        $this->requestData = $data;
    }

    public function setAction($action)
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
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
    public function validate($rules = null)
    {
        if(!$rules){
            // get the method name
            // get rules from Validate attribute
            $reflection = new \ReflectionMethod($this, $this->getAction());
            $attributes = $reflection->getAttributes(Validate::class);
            if (!empty($attributes)) {
                $validationClass = $attributes[0]->newInstance();
                $rules = $validationClass->getRules();
            }
        }
        $validator = validator($this->requestData, $rules);
        if ($validator->fails()) {
            $this->response(['errors'=>$validator->errors()], 422);
        }
        return $validator->validated();
    }

    public function only()
    {
        $data = $this->validate();
        $arguments = func_get_args();
        if(count($arguments) == 1 && !is_array($arguments[0])) {
            return $data[$arguments[0]] ?? null;
        }
        if(count($arguments) == 1 && is_array($arguments[0])) {
            $keys = $arguments[0];
        } else {
            $keys = $arguments;
        }
        return collect($data)->only($keys)->toArray();
    }

    protected function response($data, $status = 200)
    {
        if(app()->runningInConsole()){
            dd($data);
        }
        return abort(response($data, $status));
    }

    public function onMounted()
    {
        //return class instance
        return $this->toArray();
    }
    protected function toArray()
    {
        // get public properties of the class instance then return them as an array
        $classVars = call_user_func('get_object_vars', $this);
        // remove the rules property
        $exclude = ['rules', 'isTesting', 'authenticatedUser', 'requestData', 'action'];
        foreach ($exclude as $key) {
            unset($classVars[$key]);
        }
        $data = [];
        $data['properties'] = $classVars;
        return $data;
    }
}
