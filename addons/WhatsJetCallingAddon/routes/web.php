<?php

use Illuminate\Support\Facades\Route;
use App\Http\Middleware\VendorAccessCheckpost;
use App\Http\Middleware\CentralAccessCheckpost;
use Addons\WhatsJetCallingAddon\Yantrana\Controllers\WhatsJetCallingAddonController;


Route::middleware([
    'web',
])->group(function () {
    Route::middleware([
        VendorAccessCheckpost::class,
    ])->prefix('vendor-console/whatsapp-calling')
        ->group(function () {
            // Process Store Vendor Related Settings
            Route::post('/process-store-vendor-setting', [
                WhatsJetCallingAddonController::class,
                'processStoreVendorSettings',
            ])->name('addon.vendor.write.process_store_vendor_setting');
            
            // Update Call Data
            Route::post('/update-call-details', [
                WhatsJetCallingAddonController::class,
                'processUpdateCallDetails',
            ])->name('addon.vendor.write.process_update_call_details');

            // Answer to incoming call from whatsapp user
            Route::post('/answer-user-initiated-call', [
                WhatsJetCallingAddonController::class,
                'answerUserInitiatedCall',
            ])->name('addon.vendor.write.answer_user_initiated_call');

            // Get the details of user call permission
            Route::get('/get-user-call-permissions/{contactUid}', [
                WhatsJetCallingAddonController::class,
                'getCurrentUserCallPermission',
            ])->name('addon.vendor.read.get_user_call_permission');

            // Send Free Form Call Permission Request
            Route::post('/send-free-form-call=permission-request', [
                WhatsJetCallingAddonController::class,
                'sendFreeFormCallPermissionRequest',
            ])->name('addon.vendor.write.send_free_form_call_permission_request');

            // Initiate business whatsapp call
            Route::post('/business-initiated-call', [
                WhatsJetCallingAddonController::class,
                'businessInitiatedCall',
            ])->name('addon.vendor.write.business_initiated_call');

            // Stop in progress call
            Route::post('/stop-in-progress-call', [
                WhatsJetCallingAddonController::class,
                'stopInProgressCall',
            ])->name('addon.vendor.write.stop_in_progress_call');
        });
        Route::middleware([
            CentralAccessCheckpost::class,
            ])->prefix('/addons/WhatsJetCallingAddon')
            ->group(function () {
                // server the assets
                Route::get('/assets/{path}', [
                    WhatsJetCallingAddonController::class,
                    'assetServe'
                ])->name('addon.WhatsJetCallingAddon.assets');

                Route::get('/setup', [
                    WhatsJetCallingAddonController::class,
                    'setupView'
                ])->name('addon.WhatsJetCallingAddon.setup_view');

                Route::post('/process-activation', [
                    WhatsJetCallingAddonController::class,
                    'processAddonActivation'
                ])->name('addon.WhatsJetCallingAddon.processAddonActivation');

                Route::post('/process-deactivation', [
                    WhatsJetCallingAddonController::class,
                    'processAddonDeactivation'
                ])->name('addon.WhatsJetCallingAddon.processAddonDeactivation');
        });
});
