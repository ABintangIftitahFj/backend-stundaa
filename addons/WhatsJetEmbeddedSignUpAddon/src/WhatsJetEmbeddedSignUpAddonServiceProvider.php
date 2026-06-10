<?php

namespace Addons\WhatsJetEmbeddedSignUpAddon;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WhatsJetEmbeddedSignupAddonServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lwSystem.php',
            'lwSystem-WhatsJetEmbeddedSignUpAddon'
        );
        // translation source folders
        $this->mergeConfigFrom(
            __DIR__ . '/../config/translation-source-folders.php',
            '__misc.translation_source_folders'
        );
        // app settings items
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-app-settings.php',
            '__settings.items'
        );
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-embedded-signup.php',
            'addon-embedded-signup'
        );
        // add to vendor settings
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-vendor-settings.php',
            '__vendor-settings.items'
        );
    }

    public function boot()
    {
        // Load views
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'WhatsJetEmbeddedSignUpAddon'
        );
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // Publish resources
        $this->publishes([
            __DIR__ . '/../config/addon-embedded-signup.php' => config_path('addon-embedded-signup.php'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/WhatsJetEmbeddedSignUpAddon'),
        ], 'WhatsJetEmbeddedSignUpAddon');
    }
}
