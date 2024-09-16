<?php

namespace Iankibet\Streamline\Features\Streamline;

use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Component;
use Iankibet\Streamline\Features\Support\StreamlineSupport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HandleStreamlineRequest
{
    public function handleRequest(Request $request)
    {
        $this->validateRequest($request);

        $class = StreamlineSupport::convertStreamToClass($request->input('stream'));

        if (!class_exists($class)) {
            $error = 'Service class not found';
            if (app()->environment('local')) {
                $error .= ' - ' . $class;
            }
            abort(404, $error);
        }

        $action = $request->input('action');
        $params = $request->input('params', []);

        $instance = app($class);
        $instance->setAction($action);
        $requestData = $request->all();
        // remove action and params from request data
        unset($requestData['action']);
        unset($requestData['params']);
        $instance->setRequestData($requestData);
        if (!method_exists($instance, $action)) {
            abort(404, 'Action not found');
        }

        return $this->invokeAction($instance, $action, $params);
    }

    protected function validateRequest(Request $request)
    {
        $request->validate([
            'stream' => 'required|string',
            'action' => 'required|string',
            'params' => ''// this is optional,
        ]);
        if ($request->has('params')) {
            $params = $request->input('params');
            if (!is_array($params)) {
                $params = explode(',', $params);
                $request->merge(['params' => $params]);
            }
        }
    }

    protected function invokeAction($instance, string $action, array $params)
    {
        // Check if the required parameters are provided, Reflection is slow so only do this in local environment for debugging
        $reflection = new \ReflectionMethod($instance, $action);
        $requiredParams = $reflection->getNumberOfRequiredParameters();
        if (count($params) < $requiredParams) {
            $missingParams = array_diff(
                array_map(fn($param) => $param->getName(), $reflection->getParameters()),
                array_keys($params)
            );

            abort(400, 'Missing required parameters: ' . implode(', ', $missingParams));
        }
        // check if instance implements StreamlineComponent
        if (!$instance instanceof Component) {
            abort(404, 'Service class must implement streamline Component');
        }
        // check if action has Validate attribute

        $reflectionClass = new \ReflectionClass($instance);
        $classAttributes = $reflectionClass->getAttributes(Permission::class);
        // check attributes for permission on the action
        $attributes = $reflection->getAttributes(Permission::class);
        $attributes = array_merge($attributes, $classAttributes);
        if (count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                $permissionInstance = $attribute->newInstance();
                $permissionSlugs = $permissionInstance->getPermissions();
                foreach ($permissionSlugs as $permissionSlug) {
                    $user = \request()->user();
                    if (!$user->can($permissionSlug)) {
                        $unauthorizedMessage = 'Unauthorized to perform this action';
                        if(app()->environment('local')){
                            $unauthorizedMessage .= ' - ' . $permissionSlug;
                        }
                        abort(403, $unauthorizedMessage);
                    }
                }
            }
        }
        $validateAttributes = $reflection->getAttributes(Validate::class);
        if (count($validateAttributes) > 0) {
            $instance->validate();
        }
        return $instance->$action(...array_values($params));
    }
}
