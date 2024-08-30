<?php

namespace Iankibet\Streamline\Features\Commands;

use App\Models\User;
use Iankibet\Streamline\Attributes\Validate;
use Iankibet\Streamline\Features\Support\StreamlineSupport;
use Illuminate\Console\Command;

class TestComponent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streamline:test-component {component}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */

    public function handle()
    {
        //
        $component = $this->argument('component');
        $class = StreamlineSupport::convertStreamToClass($component);
        $instance = app($class);

        $action = $this->ask('Enter action');

        // using reflection class, we check if the action exists in the class and the arguments it requires
        $reflection = new \ReflectionMethod($class, $action);
        $instance->setAction($action);
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
        if($this->confirm('Do you want to test as authenticated user?')){
            $user = $this->ask('Enter user id');
            $user = User::find($user);
            if(!$user){
                $this->error('User not found');
                return;
            }
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
