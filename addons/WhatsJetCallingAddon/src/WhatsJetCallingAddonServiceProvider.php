<?php

namespace Addons\WhatsJetCallingAddon;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use App\Events\WhatsappWebhookReceived;
use Addons\WhatsJetCallingAddon\Yantrana\Controllers\WhatsJetCallingAddonController;

class WhatsJetCallingAddonServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(
            __DIR__ . '/../config/lwSystem.php',
            'lwSystem-WhatsJetCallingAddon'
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
        // add to vendor settings
        $this->mergeConfigFrom(
            __DIR__ . '/../config/addon-vendor-settings.php',
            '__vendor-settings.items'
        );
    }

    public function boot(WhatsJetCallingAddonController $whatsJetCallingAddonController)
    {
        // Listen whatsapp calling webhook events
        Event::listen(WhatsappWebhookReceived::class, function ($event) use ($whatsJetCallingAddonController) {
            $this->preparePlanConfiguration();
            $whatsJetCallingAddonController->processWhatsappCallingWebhook($event->webhookData, $event->vendorUid);
        });

        if (swaksharyipadtalniforadditionals('WhatsJetCallingAddon')) {
            // append to plans
            $this->preparePlanConfiguration();
            // Append views to respective stacks
            View::composer('layouts.app', function ($view) {
                $view->getFactory()->startPush('globalViewsStack', view('WhatsJetCallingAddon::whatsapp-calling')->render());
                $view->getFactory()->startPush('vendorChannelBroadcastStack', view('WhatsJetCallingAddon::whatsapp-call-initiate')->render());
                $view->getFactory()->startPush('appScripts', view('WhatsJetCallingAddon::whatsapp-inbound-call')->render());
                $view->getFactory()->startPush('appScripts', view('WhatsJetCallingAddon::whatsapp-outbound-call')->render());
            });

            View::composer('whatsapp.chat', function ($view) {
                $view->getFactory()->startPush('whatsappCallButton', view('WhatsJetCallingAddon::whatsapp-call-button')->render());
            });

            View::composer('vendors.settings.whatsapp-cloud-api-setup', function ($view) {
                $view->getFactory()->startPush('whatsappPhoneNumberStack', view('WhatsJetCallingAddon::whatsapp-call-vendor-setup')->render());
            });
        }

        // Load views
        $this->loadViewsFrom(
            __DIR__ . '/../resources/views',
            'WhatsJetCallingAddon'
        );
        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        // Publish resources
       /*  $this->publishes([
            // __DIR__ . '/../config/addon-embedded-signup.php' => config_path('addon-embedded-signup.php'),
            __DIR__ . '/../resources/views' => resource_path('views/vendor/WhatsJetCallingAddon'),
        ], 'WhatsJetCallingAddon'); */

        $this->publishes([
            __DIR__ . '/../resources/css/whatsapp-addon.css' => public_path('WhatsJetCallingAddon/whatsapp-addon.css'),
        ], 'WhatsJetCallingAddon');
    }

    protected function preparePlanConfiguration()
    {
        if (__isEmpty(config('lw-plans.free.features.WhatsJetCallingAddon'))) {
            $planConfiguration = [
                'type' => 'switch', // on or off
                'description' => __tr('Whatsapp Calling API'),
                'limit' => 1, // 0 for none, 1 for enable
            ];
            config([
                'lw-plans.free.features.WhatsJetCallingAddon' => $planConfiguration
            ]);
            foreach (config('lw-plans.paid') as $paidPlanKey => $paidPlan) {
                config([
                    "lw-plans.paid.$paidPlanKey.features.WhatsJetCallingAddon" => $planConfiguration
                ]);
            }
        }
    }
}
