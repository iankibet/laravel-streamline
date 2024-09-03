<?php

namespace Iankibet\Streamline\Features\Commands;

use App\Models\User;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Component;
use Iankibet\Streamline\Features\Support\StreamlineSupport;
use Illuminate\Console\Command;

class TestComponent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streamline:test-component {component?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test a streamline component';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        //
        $component = $this->argument('component');
        if(!$component){
            $classes = StreamlineSupport::getStreamlineClasses();
            $component = $this->anticipate('Enter Component', $classes);
        }
        $class = StreamlineSupport::convertStreamToClass($component);

        $instance = app($class);
        if(!$instance instanceof  Component){
            $this->error('Service class must implement streamline Component');
            exit;
        }
        $classReflection = new \ReflectionClass($class);
        //check if class extends Component
        $methods = $classReflection->getMethods();
        $methodsArr = collect($methods)->map(function ($method){
            return $method->name;
        })->toArray();
        $action = $this->anticipate('Enter action', $methodsArr);
        $instance->setAction($action);
        $reflection =  new \ReflectionMethod($class, $action);
        $params = [];
        foreach ($reflection->getParameters() as $parameter) {
            $params[] = $this->ask($parameter->getName());
        }
        // check if we have Validate attribute
        $attributes = $reflection->getAttributes(Validate::class);
        if (!empty($attributes)) {
            $validationClass = $attributes[0]->newInstance();
            $rules = $validationClass->getRules();
            $data = $this->askRequestData(array_keys($rules));
            $instance->setRequestData($data);
        }
        if($userId = $this->ask('Which user do you want to test as, Enter user id')){
            $user = User::find($userId);
            if(!$user){
                $this->error('User not found');
                return;
            }
            $this->info('Testing as user: '.$user->name);
            $instance->setAuthenticatedUser($user);
            auth()->login($user);
        }
        $response = $instance->{$action}(...$params);
        dd($response);
    }

    protected function askRequestData($rules){
        $data = [];
        $this->info("Enter data for the following fields, press enter to skip");
        foreach ($rules as $rule){
            $value = $this->ask($rule);
            $data[$rule] = $value;
        }
        return $data;
    }
}
