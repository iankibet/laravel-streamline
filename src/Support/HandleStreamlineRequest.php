<?php

namespace Iankibet\Streamline\Support;

use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HandleStreamlineRequest
{
    public function handleRequest(Request $request)
    {
        $this->validateRequest($request);

        $stream = $this->convertStreamToClass($request->input('stream'));
        $class = $this->getServiceClass($stream);

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

    protected function convertStreamToClass(string $stream): string
    {
        $streamCollection = collect(explode('/', $stream));

        return $streamCollection->map(function ($item) {
            return Str::studly(str_replace('-', ' ', $item));
        })->implode('\\');
    }

    protected function getServiceClass(string $stream): string
    {
        $classPostfix = config('streamline.class_postfix', '');
        if (str_ends_with($stream, $classPostfix)) {
            $classPostfix = '';
        }

        return config('streamline.class_namespace') . '\\' . $stream . $classPostfix;
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
        $reflectionClass = new \ReflectionClass($instance);
        $classAttributes = $reflectionClass->getAttributes(Permission::class);
        // check attributes for permission on the action
        $attributes = $reflection->getAttributes(Permission::class);
        $attributes = array_merge($attributes, $classAttributes);
        if (count($attributes) > 0) {
            foreach ($attributes as $attribute) {
                $permissionSlugs = $attribute->getArguments();
                foreach ($permissionSlugs as $permissionSlug) {
                    $user = \request()->user();
                    if (!$user->can($permissionSlug)) {
                        abort(403, 'Unauthorized: ' . $permissionSlug);
                    }
                }
            }
        }
        return $instance->$action(...array_values($params));
    }
}
