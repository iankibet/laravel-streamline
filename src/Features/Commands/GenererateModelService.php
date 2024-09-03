<?php

namespace Iankibet\Streamline\Features\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

class GenererateModelService extends Command
{
    protected $real_model;
    protected $model_name;
    protected $fields;
    protected $plain_fields;
    protected $model_fields;
    protected $factory_amount;
    protected $permission;
    protected $factory_field = [];
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'streamline:model-service {model}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a model , migration and service';
    /**
     * Execute the console command.
     */


    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $model_name = $this->argument('model');
        $this->model_name = $model_name;
        $model_namespace = $this->ask("What is the model namespace?", "Core");

        if (!$model_namespace) {
            $model_namespace = "Core";
        }

        $real_model = $model_namespace . "/" . $model_name;
        $this->real_model = $real_model;
        $plain_fields = [];
        $fields = [];

        $field_input = $this->ask("Enter migration fields (e.g., name,string;email,string;password,string):");
        $this->factory_amount = $this->ask("Enter number of records to generate (or 'n' to skip):", 0);
        $this->permission = $this->ask("Do you want to create permission ? Yes or No");
        $field_entries = explode(';', $field_input);

        foreach ($field_entries as $entry) {
            $field_parts = explode(',', $entry);
            $field_name = $field_parts[0];
            $field_type = isset($field_parts[1]) ? $field_parts[1] : 'string';

            if (!$field_type) {
                $field_type = $this->getFieldType($field_name);
            }
            $field_type = str_replace('datetime', 'dateTime', $field_type);
            $field_type = str_replace('longtext', 'longText', $field_type);
            $fields[] = [
                'name' => $field_name,
                'type' => $field_type
            ];
            $plain_fields[] = $field_name;
        }

        $this->fields = $fields;
        $this->plain_fields = $plain_fields;

        $this->createModel();
        if (intval($this->factory_amount) > 0) {
            $this->createFactory();
            $this->createSeeder();
        }
        $this->updateMigrationFields();
        $this->createService();
        $this->createPermission();
        $this->info("Done!");
    }

    public function createModel()
    {
        Artisan::call("make:model", [
            'name' => $this->real_model,
            '-m' => true
        ]);
        $model_path = app_path("Models/" . $this->real_model . '.php');
        $model_content = file_get_contents($model_path);
        $model_array = explode('use HasFactory;', $model_content);
        $pre_model_content = $model_array[0];
        $post_model_content = $model_array[1];
        $this->model_fields = '"' . implode('","', $this->plain_fields) . '"';
        $current_model_content = "\n\tuse HasFactory;\n\t" . 'protected $fillable = [' . $this->model_fields . '];' . "\n";
        $new_model_contents = $pre_model_content . $current_model_content . $post_model_content;
        file_put_contents($model_path, $new_model_contents);
    }

    protected function updateMigrationFields()
    {
        $migration_dir = base_path('database/migrations');
        $migrations = scandir($migration_dir);
        $migration = $migration_dir . '/' . $migrations[count($migrations) - 1];
        $migration_contents = file_get_contents($migration);
        $migration_arr = explode('$table->id();' . "\n", $migration_contents);
        $pre_migration_content = $migration_arr[0];
        $after_migration_content = $migration_arr[1];
        $current_migration_content = '$table->id();' . "\n";
        $fields = $this->fields;
        $nullables = ['description', 'bio'];
        $defaults = [
            'status' => 0,
            "state" => "'inactive'"
        ];
        $enum = [
            'state' => [
                "inactive",
                "active",
                "disabled"
            ]
        ];

        foreach ($fields as $field) {
            $current_migration_content .= $field['type'] == 'enum' ? "\t\t\t" . '$table->' . $field['type'] . '(\'' . $field['name'] . '\'' . "," . '[\'' . implode("','", $enum['state']) . '\']' . ')' : "\t\t\t" . '$table->' . $field['type'] . '(\'' . $field['name'] . '\')';
            if (in_array($field['name'], $nullables))
                $current_migration_content .= '->nullable()';
            if (isset($defaults[$field['name']]))
                $current_migration_content .= '->default(' . $defaults[$field['name']] . ')';

            $current_migration_content .= ';' . "\n";
        }

        $new_migration_content = $pre_migration_content . $current_migration_content . $after_migration_content;
        file_put_contents($migration, $new_migration_content);
    }

    public function createService()
    {
        $template_path = base_path('stubs/service_template.txt');
        if (!file_exists($template_path)) {
            $this->error('Service template not found at: ' . $template_path);
            return;
        }
        $service_namespace_path = str_replace('Core/', '', $this->real_model);
        $service_name = $this->model_name . 'sService';
        $service_namespace = str_replace('/', '\\', 'App\Services\\' . $service_namespace_path);
        $service_directory = app_path('Services/' . str_replace('/', '/', $service_namespace_path));
        $modelNamespace = 'App\Models\\' . $this->real_model;
        $modelNamespace = str_replace('/', '\\', $modelNamespace);
        if (!file_exists($service_directory)) {
            mkdir($service_directory, 0755, true);
        }

        $service_path = $service_directory . '/' . $service_name . '.php';
        $template_content = file_get_contents($template_path);
        $umodel = Str::camel($this->model_name);
        $Umodel = Str::studly($this->model_name);
        $service_content = str_replace(
            ['{serviceNamespace}','{modelNamespace}', '{umodel}', '{Umodel}'],
            [$service_namespace, $modelNamespace,$umodel, $Umodel],
            $template_content
        );
        file_put_contents($service_path, $service_content);
        $this->info('Service created ');
    }

    public function createPermission() {
        if (strtolower($this->permission) == 'no' || strtolower($this->permission) == 'n') {
            return;
        }
        $permission_name = Str::camel($this->model_name);
        $permission_path = base_path('stubs/permission_template.txt');
        if (!file_exists($permission_path)) {
            $this->error('Permission template not found at: ' . $permission_path);
            return;
        }
        $permission_directory =  storage_path('app/permissions/modules/' . $permission_name.'s.json');
        $template_content = file_get_contents($permission_path);
        $permission_content = str_replace(
            ['{permissionName}'],
            [$permission_name],
            $template_content
        );
        file_put_contents($permission_directory, $permission_content);
        $this->info('Permission created ');
    }
    public function getFieldType($field_name){
        $textareas = ['description','answer','more_information','reason','email_message','sms_message','html',
            'comment',"testimonial",'about','address','postal_address','message','invoice_footer',
            'security_credential','reason_rejected','note','instructions'];
        $enum = ['state'];
        if (in_array($field_name,$enum))
            return 'enum';
        if(in_array($field_name,$textareas))
            return 'longText';
        $arr = explode('_',$field_name);
        if($arr[count($arr)-1] == 'id')
            return 'integer';
        if($arr[count($arr)-1] == 'at')
            return 'dateTime';
        if($arr[0] == 'date')
            return 'dateTime';
        $doubles = ['age','year','height','width','amount','price','discount','deposit','rate','percentage','year_of_birth','id_number'];
        if(in_array($field_name,$doubles))
            return 'double';
        return 'string';
    }
    public function createFactory()
    {
        $factory_name = str_replace('Core/', '', $this->real_model);
        $path = "factories/$factory_name" . "Factory.php";
        Artisan::call("make:factory", [
            'name' => $factory_name
        ]);
        $factory_path = database_path($path);
        $factory_content = file_get_contents($factory_path);
        if (strpos($factory_content, 'return [') !== false) {
            $factory_array = explode('return [', $factory_content);
            $pre_factory_content = $factory_array[0];
            if (isset($factory_array[1])) {
                $post_factory_content = $factory_array[1];
            } else {
                $post_factory_content = '';
            }
        } else {
            $this->error("Invalid factory content format. Could not find 'return [' in the factory file.");
            return;
        }
        $this->factoryContent();
        $current_factory_content = 'return [ ' . implode(',', $this->factory_field) . "\n\t\t";
        $new_factory_contents = $pre_factory_content . $current_factory_content . $post_factory_content;
        file_put_contents($factory_path, $new_factory_contents);
        $this->info("Factory created: ");
    }
    protected function factoryContent () {
        $fields = $this->fields;
        foreach($fields as $field){
            $custom_fields = ['name','email','password','remember_token','email_verified_at','phone','state'];
            $field_name= $field['name'];
            $type = null;
            $content = in_array($field_name,$custom_fields) ?  $type = $field_name :$type = $field['type'];
            $content = $this->getFactoryType($type);
            $name ="\n\t\t\t'".$field['name'].'\'';
            $current_factory_content = $name.'=>'.$content;
            array_push($this->factory_field,$current_factory_content);
            $current_factory_content = null;
        }
    }
    protected function getFactoryType($type){
        $matched =  match($type) {
            'longText' => '$this->faker->text(300)',
            'phone' => '$this->faker->phoneNumber',
            'dateTime' => '$this->faker->dateTime()',
            'integer','double' => '$this->faker->randomDigit()',
            'string' =>  '$this->faker->text(20)',
            'name' =>  '$this->faker->name()',
            'email' => '$this->faker->unique()->safeEmail()',
            'password' =>  "\Illuminate\Support\Facades\Hash::make('password')",
            'remember_token' =>  '\Illuminate\Support\StrStr::random(10)',
            'email_verified_at' =>  'now()',
            'state' => '$this->faker->randomElement(["inactive" ,"active", "disabled"])',
        };
        return $matched;
    }
    protected function createSeeder()
    {
        $model_namespace = explode('/', $this->real_model);
        $seeder_name = last($model_namespace) . 'Seeder';
        $path = "seeders/$seeder_name" . ".php";
        $amount = $this->factory_amount;
        Artisan::call("make:seeder", [
            'name' => $seeder_name
        ]);
        $seed_path = database_path($path);
        $seed_content = file_get_contents($seed_path);
        $seed_array = explode('//', $seed_content);
        if (count($seed_array) < 2) {
            $this->error("Unexpected seeder content format. The '//' separator was not found.");
            return;
        }
        $pre_seed_content = $seed_array[0];
        $post_seed_content = $seed_array[1];
        $namespace = implode("\\", $model_namespace);
        $seed_namespace = "\App\Models\\" . $namespace . "::factory($amount)->create();";
        $new_seed_content = $pre_seed_content . $seed_namespace . $post_seed_content;
        file_put_contents($seed_path, $new_seed_content);
        $this->info("Seeder created: " );
    }
}
