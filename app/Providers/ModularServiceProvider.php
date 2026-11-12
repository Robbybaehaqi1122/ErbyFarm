<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ModularServiceProvider extends ServiceProvider
{
    /**
     * Discovered modules keyed by module name.
     *
     * @var array<string, string>|null
     */
    protected ?array $modules = null;

    /**
     * Register module-level service providers.
     */
    public function register(): void
    {
        foreach ($this->modules() as $module => $path) {
            foreach (File::glob($path.'/Providers/*ServiceProvider.php') ?: [] as $provider) {
                $class = $this->moduleNamespace($module).'\\Providers\\'.basename($provider, '.php');

                if (class_exists($class)) {
                    $this->app->register($class);
                }
            }
        }
    }

    /**
     * Register module resources and the SPA fallback.
     */
    public function boot(): void
    {
        foreach ($this->modules() as $module => $path) {
            $namespace = Str::kebab($module);

            if (File::isDirectory($path.'/Migrations')) {
                $this->loadMigrationsFrom($path.'/Migrations');
            }

            if (File::isDirectory($path.'/Views')) {
                $this->loadViewsFrom($path.'/Views', $namespace);
            }

            if (File::isDirectory($path.'/Lang')) {
                $this->loadTranslationsFrom($path.'/Lang', $namespace);
            }

            if (File::isFile($path.'/Routes/web.php')) {
                Route::middleware('web')->group($path.'/Routes/web.php');
            }

            if (File::isFile($path.'/Routes/api.php')) {
                Route::middleware('api')->prefix('api')->group($path.'/Routes/api.php');
            }
        }

        $this->registerSpaFallback();
    }

    /**
     * Discover modules from the configured path.
     *
     * @return array<string, string>
     */
    protected function modules(): array
    {
        if ($this->modules !== null) {
            return $this->modules;
        }

        $path = config('modules.path', app_path('Modules'));

        if (! File::isDirectory($path)) {
            return $this->modules = [];
        }

        $enabled = array_values(config('modules.enabled', []));
        $disabled = array_values(config('modules.disabled', []));
        $discovered = [];

        if (config('modules.auto_discover', true)) {
            foreach (File::directories($path) as $directory) {
                $discovered[basename($directory)] = $directory;
            }
        } else {
            foreach ($enabled as $module) {
                $directory = $path.'/'.$module;

                if (File::isDirectory($directory)) {
                    $discovered[$module] = $directory;
                }
            }
        }

        if ($enabled !== []) {
            $discovered = array_intersect_key($discovered, array_flip($enabled));
        }

        if ($disabled !== []) {
            $discovered = array_diff_key($discovered, array_flip($disabled));
        }

        if ($enabled === []) {
            ksort($discovered);
        }

        return $this->modules = $discovered;
    }

    /**
     * Build the base namespace for a module.
     */
    protected function moduleNamespace(string $module): string
    {
        return 'App\\Modules\\'.$module;
    }

    /**
     * Register the Vue SPA fallback after all module routes.
     */
    protected function registerSpaFallback(): void
    {
        Route::middleware('web')->group(function (): void {
            Route::view('{any?}', 'application')
                ->where('any', '^(?!api(?:/|$)).*$')
                ->name('spa');
        });
    }
}
