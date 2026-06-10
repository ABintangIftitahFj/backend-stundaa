<?php

namespace Addons\WhatsJetChatMobileApp;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class WhatsJetChatMobileAppServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lwSystem.php',
            'lwSystem-WhatsJetChatMobileApp'
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
            __DIR__ . '/../config/addon-chat-mobile-app.php',
            'addon-chat-mobile-app'
        );
        // add to vendor settings
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-vendor-settings.php',
            '__vendor-settings.items'
        );
    }

    public function boot()
    {
        if (swaksharyipadtalniforadditionals('WhatsJetChatMobileApp')) {
            // append to plans
           /*  $planConfiguration = [
                'type' => 'switch', // on or off
                'description' => __tr('Mobile App'),
                'limit' => 1, // 0 for none, 1 for enable
            ];
            config([
                'lw-plans.free.features.WhatsJetChatMobileApp' => $planConfiguration
            ]);
            foreach (config('lw-plans.paid') as $paidPlanKey => $paidPlan) {
                config([
                    "lw-plans.paid.$paidPlanKey.features.WhatsJetChatMobileApp" => $planConfiguration
                ]);
            } */
            // main sidebar
            View::composer('layouts.navbars.sidebar', function ($view) {
                // $vendorPlanDetails = vendorPlanDetails('WhatsJetChatMobileApp', 0, getVendorId());
                // if ($vendorPlanDetails['is_limit_available']) {
                    // Push content to the stack
                    $view->getFactory()->startPush('centralSidebarSettingsLinks', view('WhatsJetChatMobileApp::sidebar')->render());
                // }
            });
        }

        // Load views
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'WhatsJetChatMobileApp'
        );
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // Publish resources
        $this->publishes([
            __DIR__ . '/../config/addon-chat-mobile-app.php' => config_path('addon-chat-mobile-app.php'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/WhatsJetChatMobileApp'),
        ], 'WhatsJetChatMobileApp');
    }
}
