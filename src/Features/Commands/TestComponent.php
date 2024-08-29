<?php

namespace Iankibet\Streamline\Features\Commands;

use App\Models\User;
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
        $params = [];
        foreach ($reflection->getParameters() as $parameter) {
            $params[] = $this->ask($parameter->getName());
        }
        if($this->confirm('Do you want to add request data?')){
            $data = $this->askRequestData();
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

    protected function askRequestData(){
        $data = [];
        $asking = true;
        while ($asking){
            $key = $this->ask('Enter key');
            $value = $this->ask('Enter value');
            $data[$key] = $value;
            $asking = $this->confirm('Add more data?');
        }
        return $data;
    }
}
