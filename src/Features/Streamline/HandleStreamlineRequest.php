<?php

namespace Iankibet\Streamline\Features\Streamline;

use App\Http\Controllers\Controller;
use Iankibet\Streamline\Attributes\Permission;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Component;
use Iankibet\Streamline\Features\Support\StreamlineSupport;
use Iankibet\Streamline\Stream;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Support\Str;

class HandleStreamlineRequest extends Controller implements HasMiddleware
{

    protected $classReflection;

    public function handleFlatRequest(){
        $args = func_get_args();
        // e.g users/user/1 results in users stream, user function
        // users/list-active results in users stream listActive
        // users/list/active - can be users stream, list function, active is param
        $streamArr = [];
        $streamStr = '';
        foreach ($args as $arg) {
            $streamArr[] = $arg;
            $streamStr = implode('/', $streamArr);
            $class = StreamlineSupport::convertStreamToClass($streamStr);
            if(class_exists($class)){
                break;
            }
        }
        $remainingArgs = array_diff($args, $streamArr);
        if(!$remainingArgs){
            $action = 'onMounted';
        } else {
            $action = array_values($remainingArgs)[0];
            $remainingArgs = array_values($remainingArgs);
            unset($remainingArgs[0]);
        }
        $action = Str::studly($action);
        // lowercase first letter
        $action = lcfirst($action);
        \request()->merge([
            'stream'=>$streamStr,
            'action'=>$action,
            'params' => $remainingArgs
        ]);
        return $this->handleRequest(request());
    }

    public function handleRequest(Request $request)
    {
        $middleware = config('streamline.middleware', []);
        $this->middleware($middleware);
        $this->validateRequest($request);

        $class = StreamlineSupport::convertStreamToClass($request->input('stream'));

        $guestClasses = config('streamline.guest_classes', []);
        if (in_array($class, $guestClasses)) {
            $this->middleware('guest');
        }

        if (!class_exists($class)) {
            $error = 'Stream class not found';
            if (app()->environment('local')) {
                $error .= ' - ' . $class;
            }
            abort(404, $error);
        }

        $action = $request->input('action','onMounted');
        $params = $request->input('params', []);
        $constructorParams = [];
        if(!$action || $action == 'onMounted'){
            $constructorParams = $params;
        }
//        $instance = new $class(...$constructorParams);
        $instance = $this->resolveInstance($class, $constructorParams);
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

    protected function resolveInstance(string $class, array $constructorParams): object
    {
        $reflection = new \ReflectionClass($class);
        $this->classReflection = $reflection;

        $constructor = $reflection->getConstructor();

        if ($constructor) {
            // Get all parameters of the constructor
            $parameters = $constructor->getParameters();

            // Resolve each parameter (either from the container or provided manually)
            $resolvedParams = [];
            foreach ($parameters as $index => $parameter) {
                if (isset($constructorParams[$index])) {
                    // Use the provided indexed parameter
                    $resolvedParams[] = $constructorParams[$index];
                } elseif ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
                    // Resolve class dependencies via the container
                    $resolvedParams[] = app($parameter->getType()->getName());
                } elseif ($parameter->isDefaultValueAvailable()) {
                    // Use the default value if available
                    $resolvedParams[] = $parameter->getDefaultValue();
                } else {
                    // Throw an exception if a required parameter is missing
                    throw new \InvalidArgumentException("Missing required parameter [{$parameter->getName()}] for [{$class}].");
                }
            }

            // Return an instance of the class with resolved parameters
            return $reflection->newInstanceArgs($resolvedParams);
        }

        // If no constructor, simply resolve the class
        return new $class;
    }

    protected function validateRequest(Request $request)
    {
        $request->validate([
            'stream' => 'required|string',
            'action' => 'nullable|string',
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
        // check if instance implements StreamlineComponent
        if (!$instance instanceof Component && !$instance instanceof Stream) {
            abort(404, 'Streamline class must extend Iankibet\Streamline\Stream');
        }
        // check if action has Validate attribute

        $reflectionClass = $this->classReflection;
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
                    if (!$user || !$user->can($permissionSlug)) {
                        abort(403, 'Unauthorized: ' . $permissionSlug);
                    }
                }
            }
        }
        $validateAttributes = $reflection->getAttributes(Validate::class);
        if (count($validateAttributes) > 0) {
            $instance->validate();
        }
        $params = array_filter(array_values($params));
        $parameters = $reflection->getParameters();

        // Resolve each parameter (either from the container or provided manually)
        $resolvedParams = [];
        foreach ($parameters as $index => $parameter) {
            if (isset($params[$index])) {
                // Use the provided indexed parameter
                $resolvedParams[] = $params[$index];
            } elseif ($parameter->getType() && !$parameter->getType()->isBuiltin()) {
                // Resolve class dependencies via the container
                $resolvedParams[] = app($parameter->getType()->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                // Use the default value if available
                $resolvedParams[] = $parameter->getDefaultValue();

//            } else {
                // Throw an exception if a required parameter is missing
//                throw new \InvalidArgumentException("Missing required parameter [{$parameter->getName()}] for [{$class}].");
            }
        }
        return $instance->$action(...$resolvedParams);
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        $stream = \request('stream');
        $guestStream = config('streamline.guest_streams', []);
        if (in_array($stream, $guestStream)) {
            return ['guest'];
        }
        $middleware = config('streamline.middleware', []);
        return $middleware;
    }
}
