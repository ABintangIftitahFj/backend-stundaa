@extends('layouts.app', ['title' => __tr('Shopify Products Send')])
@php
$vendorId = getVendorId();
// check the feature limit
$vendorPlanDetails = vendorPlanDetails('api_access', 0, $vendorId);
@endphp
@section('content')
    @include('users.partials.header', [
    'title' => __tr('Shopify Products Send'),
    'description' => '',
    'class' => 'col-lg-7'
    ])
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <!-- card start -->
                <div class="card">
                    <!-- card body -->
                    <div class="card-body">
                        <!-- include related view -->
                        <div class="row">
                            <div class="col-md-8" x-cloak>
                            <!-- Page Heading -->
                            <h1>
                                <?= __tr('Shopify Products Send Setup') ?>
                            </h1>
                            @if (isDemo())
                            <div class="alert alert-warning">
                                <strong>{{  __tr('Demo Alert:') }}</strong>
                                {{  __tr('Please note this is addon feature') }}
                            </div>
                            @endif
                            <form class="lw-ajax-form lw-form" method="post" action="<?= route('vendor.settings.write.update', ['pageType' => 'lw_addon_shopify_product_send']) ?>" >
                                <fieldset>
                                    <x-lw.checkbox name="lw_addon_enable_shopify_product_send" id="lwAddonEnableShopifyProductSend" :checked="getVendorSettings('lw_addon_enable_shopify_product_send')" data-lw-plugin="lwSwitchery" :label="__tr('Enable Shopify Products Sending')" />
                                    <x-lw.input-field type="text" id="lw_addon_sps_store_link" data-form-group-class="col-md-6 col-sm-12" value="{{ getVendorSettings('lw_addon_sps_store_link') }}" :label="__tr('Your Shopify Store Link')" name="lw_addon_sps_store_link"/>
                                    <x-lw.input-field type="number" id="lw_addon_sps_number_of_products_to_load" data-form-group-class="col-md-6 col-sm-12" value="{{ getVendorSettings('lw_addon_sps_number_of_products_to_load') }}" :label="__tr('Number of products to load in the list')" name="lw_addon_sps_number_of_products_to_load"/>
                                    <x-lw.input-field type="text" id="lw_addon_sps_number_details_btn_title" data-form-group-class="col-md-6 col-sm-12" value="{{ getVendorSettings('lw_addon_sps_number_details_btn_title') }}" :label="__tr('Details link button title')" name="lw_addon_sps_number_details_btn_title"/>
                                </fieldset>
                        {{-- submit button --}}
                        <div class="mt-2">
                            <button type="submit" href class="mt-2 btn btn-primary btn-user lw-btn-block-mobile">{{ __tr('Save') }}</button>
                        </div>
                    </form>
                            </div>
                        </div>
                        <!-- /include related view -->
                    </div>
                    <!-- /card body -->
                </div>
                <!-- card start -->
            </div>
        </div>
        </div>
@endsection()