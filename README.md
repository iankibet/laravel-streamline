# Laravel Streamline

[![Latest Version on Packagist](https://img.shields.io/packagist/v/iankibet/laravel-streamline.svg?style=flat-square)](https://packagist.org/packages/iankibet/laravel-streamline)
[![Total Downloads](https://img.shields.io/packagist/dt/iankibet/laravel-streamline.svg?style=flat-square)](https://packagist.org/packages/iankibet/laravel-streamline)

`laravel-streamline` is a powerful backend package that allows your frontend (Vue/React) to interact directly with "Stream" classes in Laravel without the need for manual routes, controllers, or boilerplate API endpoints.

## 🚀 Key Features

- **Zero Routes**: No need to define routes or controllers for every action.
- **Stateful Streams**: Initial arguments are persisted across subsequent action calls, allowing for stateful backend logic.
- **Dependency Injection**: Full support for Laravel's service container in constructors and methods.
- **Method Object Mapping**: Pass objects from the frontend that automatically populate `request()` data and map to positional parameters.
- **Security First**: Fine-grained permissions and validation via PHP attributes.
- **Privacy**: Automatically filters out internal methods and properties from the frontend.

## 📦 Installation

```bash
composer require iankibet/laravel-streamline
```

Publish the config file:

```bash
php artisan vendor:publish --tag=laravel-streamline
```

## ⚙️ Configuration

The default configuration looks like this:

```php
return [
    'class_namespace' => 'App\\Streams',
    'class_postfix' => 'Stream',
    'route' => 'api/streamline',
    'middleware' => ['auth:sanctum'],
    'guest_streams' => [
        'auth/auth'
    ],
    // Hides these properties from the frontend
    'exclude_properties' => [
        'rules', 'isTesting', 'authenticatedUser', 'requestData', 'action'
    ]
];
```

## 🛠 Basic Usage

### 1. Create a Stream class

```bash
php artisan make:stream TaskStream
```

### 2. Implement your logic

```php
namespace App\Streams;

use Iankibet\Streamline\Stream;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Attributes\Permission;

class TaskStream extends Stream
{
    public $taskId;
    public $task;

    /**
     * Arguments passed during initialization in Vue (useStreamline)
     * are automatically injected here and persisted for all future actions.
     */
    public function __construct($taskId = null)
    {
        $this->taskId = $taskId;
        if ($taskId) {
            $this->task = Task::find($taskId);
        }
    }

    #[Permission('view-tasks')]
    public function onMounted()
    {
        // This is called when the stream is initialized on the frontend
        return $this->toArray();
    }

    #[Validate(['status' => 'required|string'])]
    public function updateStatus($status)
    {
        // Positional parameters can also be passed from objects!
        // service.updateStatus({status: 'completed'}) works too.

        $this->task->update(['status' => $status]);
        return ['success' => true];
    }
}
```

## 🧙 Advanced Features

### Persistent State

When you initialize a stream on the frontend:
`const { service } = useStreamline('task', 42);`

The argument `42` is sent to the backend. For every subsequent call to `service.someAction()`, the backend automatically re-instantiates `TaskStream(42)` before executing `someAction()`.

### Method Object Mapping

You can pass objects to methods from the frontend:
`service.updateUser(123, { role: 'admin', note: 'Promoted' })`

On the backend:

```php
public function updateUser($userId, $role) {
    // $userId = 123
    // $role = 'admin' (automatically unwrapped from the object)
    // request('note') = 'Promoted'
}
```

### Security & Privacy

- **Filtered Output**: Only **public** properties are returned to the frontend.
- **Internal Protection**: Standard internal properties and all method names are automatically hidden from the frontend response payload to prevent leaking logic.
- **Attributes**: Use `#[Permission('...')]` and `#[Validate([...])]` for robust security.

## 🧪 Testing

Test your streams directly from the CLI:

```bash
php artisan streamline:test-stream TaskStream
```

## 📄 License

MIT
