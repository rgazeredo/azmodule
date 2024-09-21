<?php

namespace AZCore\AZModule\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Yaml\Yaml;

class AZModuleCommand extends Command
{
    protected $signature = 'az:module {filename} {--delete}';
    protected $description = 'AzModule manager command';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $file = Yaml::parseFile(base_path() . "/modules/{$this->argument('filename')}.yml");

        $name = Str::studly($file['name']);
        $delete = $this->option('delete');
        $modulePath = app_path("Modules/{$name}");

        if ($delete) {
            $this->deleteModule($name, $modulePath);
        } else {
            $this->createModule($file, $modulePath, $name);
        }
    }

    protected function createModule($file, $modulePath, $moduleName)
    {
        if (File::exists($modulePath)) {
            $this->error("Module '{$moduleName}' already exists!");
            return;
        }

        // CREATE DIRECTORY STRUCTURE
        File::makeDirectory($modulePath, 0755, true);
        File::makeDirectory("{$modulePath}/Livewire", 0755, true);
        File::makeDirectory("{$modulePath}/Models", 0755, true);
        File::makeDirectory("{$modulePath}/Database/Migrations", 0755, true);
        File::makeDirectory("{$modulePath}/Database/Seeders", 0755, true);
        File::makeDirectory("{$modulePath}/Resources", 0755, true);
        File::makeDirectory("{$modulePath}/Resources/Views", 0755, true);
        File::makeDirectory("{$modulePath}/Resources/Lang", 0755, true);
        File::makeDirectory("{$modulePath}/Resources/Lang/en", 0755, true);
        File::makeDirectory("{$modulePath}/Resources/Lang/pt_BR", 0755, true);
        File::makeDirectory("{$modulePath}/Routes", 0755, true);
        File::makeDirectory("{$modulePath}/Providers", 0755, true);

        // CREATE ROUTE WEB FILE
        $this->createRouteWeb($modulePath, $moduleName);

        // CREATE ROUTE API FILE
        $this->createRouteApi($file, $modulePath, $moduleName);

        // CREATE PROVIDER FILE
        $this->createProvider($modulePath, $moduleName);

        // CREATE LIVEWIRE FILE
        $this->createLivewire(json_encode($file), $modulePath, $moduleName);

        //CREATE VIEW FILE
        $this->createView(json_encode($file), $modulePath, $moduleName);

        //CREATE LANG FILE
        $this->createLang($modulePath, $moduleName);

        if (!empty($file['migrations'])) {
            $migrations = $file['migrations'];
            foreach ($migrations as $migration) {
                //CREATE MIGRATION FILE
                $this->createMigration(json_encode($migration), $modulePath, $migration['table']);

                //CREATE MODEL FILE
                $this->createModel(json_encode($migration), $modulePath, $migration['table']);
            }

            // CALL MIGRATE
            Artisan::call('migrate', [
                '--path' => "app/Modules/{$moduleName}/Database/Migrations",
            ]);
        }

        $role_admin = Role::where('name', 'ADM')->first();

        $permission_create = Permission::create(['name' => strtolower($moduleName) . '_create']);
        $permission_create->assignRole($role_admin);
        $permission_read = Permission::create(['name' => strtolower($moduleName) . '_read']);
        $permission_read->assignRole($role_admin);
        $permission_update = Permission::create(['name' => strtolower($moduleName) . '_update']);
        $permission_update->assignRole($role_admin);
        $permission_delete = Permission::create(['name' => strtolower($moduleName) . '_delete']);
        $permission_delete->assignRole($role_admin);

        // CALL OPTIMIZE
        Artisan::call('optimize');

        $this->info("Module '{$moduleName}' created successfully!");
    }

    protected function createRouteWeb($modulePath, $moduleName)
    {
        $routeName = Str::of($moduleName)->lower()->plural();
        $webRoutesPath = "{$modulePath}/Routes/web.php";

        if (File::exists($webRoutesPath)) {
            $this->error("Route web '{$routeName}' already exists!");
            return;
        }

        $webRoutesContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse App\\Modules\\{$moduleName}\\Livewire\\{$moduleName}Livewire;\n\nRoute::middleware('auth')->group(function () {\n  Route::get('{$routeName}', {$moduleName}Livewire::class)->name('{$routeName}');\n});";
        File::put($webRoutesPath, $webRoutesContent);

        $this->info("Route web '{$routeName}' created successfully!");
    }

    protected function createRouteApi($file, $modulePath, $moduleName)
    {
        $routeName = Str::of($moduleName)->lower()->plural();
        $apiRoutesPath = "{$modulePath}/Routes/api.php";

        if (File::exists($apiRoutesPath)) {
            $this->error("Route api file already exists!");
            return;
        }

        $apiRoutesContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;";

        if (!empty($file['api'])) {
            $label = $file['api']['label'];
            $value = $file['api']['value'];

            $apiRoutesContent = "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\nuse App\\Modules\\{$moduleName}\\Models\\{$moduleName};\n\nRoute::get('{$routeName}', function () {\n  \$items = {$moduleName}::get()\n    ->map(function (\$item) {\n      return [\n        'label' => \$item->{$label},\n        'value' => \$item->{$value}\n      ];\n    });\n  return response()->json(\$items);\n})->name('api.{$routeName}');\n";
        }

        File::put($apiRoutesPath, $apiRoutesContent);

        $this->info("Route api file created successfully!");
    }

    protected function createProvider($modulePath, $moduleName)
    {
        $serviceProviderPath = "{$modulePath}/Providers/{$moduleName}ServiceProvider.php";

        if (File::exists($serviceProviderPath)) {
            $this->error("Service provider '{$moduleName}ServiceProvider.php' already exists!");
            return;
        }

        $viewName = strtolower($moduleName);
        $serviceProviderContent = "<?php\n\nnamespace App\\Modules\\{$moduleName}\\Providers;\n\nuse Illuminate\\Support\\ServiceProvider;\nuse Illuminate\\Support\\Facades\\Route;\nuse Livewire\\Livewire;\n\nclass {$moduleName}ServiceProvider extends ServiceProvider\n{\n\n  public function boot()\n  {\n    \$this->loadViewsFrom(__DIR__ . '/../Resources/Views', '{$viewName}');\n    \$this->loadTranslationsFrom(__DIR__ . '/../Resources/Lang', 'core');\n    \$this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');\n    Livewire::component('{$viewName}-livewire', 'App\\Modules\\{$moduleName}\\Livewire\\{$moduleName}Livewire');\n  }\n\n  public function register()\n  {\n    Route::middleware(['web', 'auth'])\n      ->namespace('App\\Modules\\{$moduleName}\\Livewire')\n      ->group(base_path('app/Modules/{$moduleName}/Routes/web.php'));\n\n    Route::prefix('api')\n      ->middleware(['api'])\n      ->namespace('App\\Modules\\{$moduleName}\\Livewire')\n      ->group(base_path('app/Modules/{$moduleName}/Routes/api.php'));\n  }\n}";
        File::put($serviceProviderPath, $serviceProviderContent);

        $this->info("Service provider '{$moduleName}ServiceProvider.php' created successfully!");
    }

    protected function createMigration($content, $modulePath, $table)
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_create_{$table}_table.php";
        $migrationPath = "{$modulePath}/Database/Migrations/{$filename}";

        if (File::exists($migrationPath)) {
            $this->error("Migration '{$filename}.php' already exists!");
            return;
        }

        $migrationContent = $this->createFileByAI($content, config('app.azcore.openai.assistant_id_migration'));

        if (!$migrationContent) {
            $this->error("Migration '{$filename}.php' failed to create!");
            return;
        }

        $contentFile = $migrationContent['data'][0]['content'][0]['text']['value'];

        $contentFile = str_replace('<?php', '', $contentFile);
        $contentFile = str_replace('```php', '<?php', $contentFile);
        $contentFile = str_replace('```', '', $contentFile);

        File::put($migrationPath, $contentFile);

        $this->info("Migration '{$filename}.php' created successfully!");
    }

    protected function createModel($content, $modulePath, $moduleName)
    {
        $moduleName = Str::of($moduleName)->studly()->singular()->toString();
        $modelPath = "{$modulePath}/Models/{$moduleName}.php";

        if (File::exists($modelPath)) {
            $this->error("Model '{$moduleName}.php' already exists!");
            return;
        }

        $modelContent = $this->createFileByAI($content, config('app.azcore.openai.assistant_id_model'));

        if (!$modelContent) {
            $this->error("Model '{$moduleName}.php' failed to create!");
            return;
        }

        $contentFile = $modelContent['data'][0]['content'][0]['text']['value'];

        $contentFile = str_replace('<?php', '', $contentFile);
        $contentFile = str_replace('```php', '<?php', $contentFile);
        $contentFile = str_replace('```', '', $contentFile);

        File::put($modelPath, $contentFile);

        $this->info("Model '{$moduleName}.php' created successfully!");
    }

    protected function createLivewire($content, $modulePath, $moduleName)
    {
        $livewirePath = "{$modulePath}/Livewire/{$moduleName}Livewire.php";

        if (File::exists($livewirePath)) {
            $this->error("Livewire '{$moduleName}Livewire.php' already exists!");
            return;
        }

        $livewireContent = $this->createFileByAI($content, config('app.azcore.openai.assistant_id_livewire'));

        if (!$livewireContent) {
            $this->error("Livewire '{$moduleName}Livewire.php' failed to create!");
            return;
        }

        $contentFile = $livewireContent['data'][0]['content'][0]['text']['value'];

        $contentFile = str_replace('<?php', '', $contentFile);
        $contentFile = str_replace('```php', '<?php', $contentFile);
        $contentFile = str_replace('```', '', $contentFile);

        File::put($livewirePath, $contentFile);

        $this->info("Livewire file created successfully!");
    }

    protected function createView($content, $modulePath, $moduleName)
    {
        $viewName = Str::of($moduleName)->lower()->singular()->toString();
        $viewPath = "{$modulePath}/Resources/Views/{$viewName}.blade.php";

        if (File::exists($viewPath)) {
            $this->error("View '{$viewName}.blade.php' already exists!");
            return;
        }

        $viewContent = $this->createFileByAI($content, config('app.azcore.openai.assistant_id_view'));
        $contentFile = $viewContent['data'][0]['content'][0]['text']['value'];

        $contentFile = str_replace('```blade', '', $contentFile);
        $contentFile = str_replace('```', '', $contentFile);

        File::put($viewPath, $contentFile);

        $this->info("View file created successfully!");
    }

    protected function createLang($modulePath, $moduleName)
    {
        $en = "{$modulePath}/Resources/Lang/en/msg.php";
        $pt_BR = "{$modulePath}/Resources/Lang/pt_BR/msg.php";

        $contentFile = "<?php\n\nreturn [];";

        File::put($en, $contentFile);
        File::put($pt_BR, $contentFile);

        $this->info("Language files created successfully!");
    }

    protected function deleteModule($moduleName, $modulePath)
    {
        if (!File::exists($modulePath)) {
            $this->error("Module '{$moduleName}' does not exist!");
            return;
        }

        $permission_create = Permission::where('name', strtolower($moduleName) . '_create')->first();

        if ($permission_create) {
            $permission_create->delete();
        }

        $permission_read = Permission::where('name', strtolower($moduleName) . '_read')->first();

        if ($permission_read) {
            $permission_read->delete();
        }

        $permission_update = Permission::where('name', strtolower($moduleName) . '_update')->first();

        if ($permission_update) {
            $permission_update->delete();
        }

        $permission_delete = Permission::where('name', strtolower($moduleName) . '_delete')->first();

        if ($permission_delete) {
            $permission_delete->delete();
        }

        // Rollback migrations
        Artisan::call('migrate:rollback', [
            '--path' => "app/Modules/{$moduleName}/Database/Migrations",
        ]);

        // Delete the module directory
        File::deleteDirectory($modulePath);

        $this->info("Module '{$moduleName}' deleted successfully!");
    }

    public function createFileByAI($content, $assistantId)
    {
        $thread = $this->createThreadAndRun($content, $assistantId);

        $status = $this->getThreadStatus($thread['thread_id']);

        if (!$status) {
            return false;
        }

        $messages = $this->getThreadMessages($thread['thread_id']);

        return $messages;
    }

    public function createThreadAndRun($content, $assistantId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/threads/runs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . config('app.azcore.openai.api_key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            "assistant_id" => $assistantId,
            "model" => "gpt-4o-mini",
            "thread" => [
                "messages" => [
                    [
                        "role" => "user",
                        'content' => $content,
                    ]
                ]
            ]
        ]));

        $ch_response = curl_exec($ch);
        $response = json_decode($ch_response, true);
        curl_close($ch);

        return $response;
    }

    public function getThreadStatus($threadId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/threads/' . $threadId . '/runs');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . config('app.azcore.openai.api_key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ]);

        $count = 0;
        $result = false;

        while (true) {
            $ch_response = curl_exec($ch);
            $response = json_decode($ch_response, true);

            $count++;

            if (isset($response['data'][0]['status']) && $response['data'][0]['status'] == 'completed') {
                $result = true;
                break;
            }

            if ($count > 10) {
                $result = false;
                break;
            }

            sleep(5);
        }

        curl_close($ch);

        return $result;
    }

    public function getThreadMessages($threadId)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/threads/' . $threadId . '/messages');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . config('app.azcore.openai.api_key'),
            'Content-Type: application/json',
            'OpenAI-Beta: assistants=v2',
        ]);

        $ch_response = curl_exec($ch);
        $response = json_decode($ch_response, true);
        curl_close($ch);

        return $response;
    }
}
