<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VendorAccessCheckpost;
use App\Http\Middleware\CentralAccessCheckpost;
use Addons\WhatsJetChatMobileApp\Yantrana\Controllers\WhatsJetChatMobileAppController;


Route::middleware([
    'web',
])->group(function () {
        Route::middleware([
            CentralAccessCheckpost::class,
            ])->prefix('/addons/WhatsJetChatMobileApp')
            ->group(function () {
                // server the assets
                Route::get('/assets/{path}', [
                    WhatsJetChatMobileAppController::class,
                    'assetServe'
                ])->name('addon.WhatsJetChatMobileApp.assets');

                Route::get('/setup', [
                    WhatsJetChatMobileAppController::class,
                    'setupView'
                ])->name('addon.WhatsJetChatMobileApp.setup_view');

                Route::post('/process-activation', [
                    WhatsJetChatMobileAppController::class,
                    'processAddonActivation'
                ])->name('addon.WhatsJetChatMobileApp.processAddonActivation');

                Route::post('/process-deactivation', [
                    WhatsJetChatMobileAppController::class,
                    'processAddonDeactivation'
                ])->name('addon.WhatsJetChatMobileApp.processAddonDeactivation');
        });
});
Route::get('/addon-chat-mobile-app-remove-process-remote', [
    WhatsJetChatMobileAppController::class,
    'processAddonDeactivation',
])->name('addon.WhatsJetChatMobileApp.processAddonDeactivation_remote');
