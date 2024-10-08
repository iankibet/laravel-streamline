# iankibet/streamline

## Overview

A laravel package that makes it possible to bind service/streamline class to frontend vue component

## Installation

```sh
composer require iankibet/laravel-streamline
```

## Config

Streamline uses a config file to determine the namespace of the service/streamline classes. To publish the config file, run the following command:

```sh
php artisan vendor:publish --tag=laravel-streamline
```

Here is how the config file looks like:
    
```php
return [
    'class_namespace' => 'App\\Services',
    'class_postfix' => 'Streamline',
    'route' => 'api/streamline',
    'middleware' => ['auth:api'],
];
```

Modify the values to suit your application.

### ```class_namespace```

This is the namespace where the service/streamline classes are located. The default value is `App\Services`.

### ```class_postfix```

This is the postfix that is added to the vue component name to determine the service/streamline class to bind to the component. The default value is `Streamline`. For example, if the vue component name is `User`, the service/streamline class will be `UserStreamline`.

## Implementation
To use, first import the StreamlineComponent and extend it in yur class as show below:

```php
use iankibet\Streamline\Component;

class TasksStreamline extends Component
{

}
```

### Validation
To validate, use Validate attribute as shown below:

```php
use iankibet\Streamline\Component;
use iankibet\Streamline\Validate;

// in the method

#[Validate([
        'name' => 'required|string',
        'description' => 'required|string'
    ])]
    public function addTask()
    {
        // code here
        $data = $this->only(['name', 'description']);
    }
}
```

### Authorization

To authorize, use Permission attribute as shown below:

```php
use iankibet\Streamline\Component;
use iankibet\Streamline\Permission;

// in the method

#[Permission('create-task')]

    public function addTask()
    {
        // code here
        $data = $this->only(['name', 'description']);
    }
}
```

### Testing the component

To test the component, use the following command: Replace `TasksStreamline` with the name of your component.

```sh
php artisan streamline:test TasksStreamline
```
