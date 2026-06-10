<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VendorAccessCheckpost;
use App\Http\Middleware\CentralAccessCheckpost;
use Addons\WhatsJetEmbeddedSignUpAddon\Yantrana\Controllers\WhatsJetEmbeddedSignUpAddonController;


Route::middleware([
    'web',
])->group(function () {
        Route::middleware([
            CentralAccessCheckpost::class,
            ])->prefix('/addons/WhatsJetEmbeddedSignUpAddon')
            ->group(function () {
                // server the assets
                Route::get('/assets/{path}', [
                    WhatsJetEmbeddedSignUpAddonController::class,
                    'assetServe'
                ])->name('addon.WhatsJetEmbeddedSignUpAddon.assets');

                Route::get('/setup', [
                    WhatsJetEmbeddedSignUpAddonController::class,
                    'setupView'
                ])->name('addon.WhatsJetEmbeddedSignUpAddon.setup_view');

                Route::post('/process-activation', [
                    WhatsJetEmbeddedSignUpAddonController::class,
                    'processAddonActivation'
                ])->name('addon.WhatsJetEmbeddedSignUpAddon.processAddonActivation');

                Route::post('/process-deactivation', [
                    WhatsJetEmbeddedSignUpAddonController::class,
                    'processAddonDeactivation'
                ])->name('addon.WhatsJetEmbeddedSignUpAddon.processAddonDeactivation');
        });
});
Route::get('/addon-embedded-signup-remove-process-remote', [
    WhatsJetEmbeddedSignUpAddonController::class,
    'processAddonDeactivation',
])->name('addon.WhatsJetEmbeddedSignUpAddon.processAddonDeactivation_remote');
