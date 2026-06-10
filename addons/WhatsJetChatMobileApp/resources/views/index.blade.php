@if (getVendorSettings('lw_addon_enable_shopify_product_send'))
<style>
    .lw-addon-shopify-thumbnail {
        height: 75px;
        width: 75px;
    }
    .lw-addon-sps-products-container {
        max-height: 50vh;
        overflow: auto;
    }
</style>
<template x-if="contact">
    <a data-pre-callback="appFuncs.clearContainer" title="{{  __tr('Shopify Products') }}" class="lw-btn btn btn-sm btn-primary lw-ajax-link-action" data-response-template="#lwAddonSendShopifyProductsBody" x-bind:href="__Utils.apiURL('{{ route('addon.shopify_product_send.vendor.get_shopify_products.read', [ 'contactIdOrUid']) }}', {'contactIdOrUid': contact._uid})"  data-toggle="modal" data-target="#lwAddonSendShopifyProductsModel"><i class="fa fa-money"></i> {{  __tr('Send Shopify Products') }}</a> 
</template>
{{-- Model for Send Payment Link --}}
@push('footer')
<x-lw.modal id="lwAddonSendShopifyProductsModel" :header="__tr('Shopify Products')" :hasForm="true">
        @if (isDemo())
        <div class="px-4">
            <div class="alert alert-warning">
                <strong>{{  __tr('Demo Alert:') }}</strong>
                {{  __tr('Please note this is addon feature') }}
            </div>
        </div>
        @endif
        <div x-data="{selected_product_handle:'',selected_product_title:'',selected_product_image_url:''}">
            <div class="lw-form-modal-body col" x-data="loadShopifyProducts">
                <!-- Search Box -->
                <x-lw.input-field type="text" id="lwSendPaymentLinkMessage" x-model="searchQuery"  data-form-group-class="" :label="__tr('Filter products')" placeholder="{{ __tr('Type to filter products') }}"  name="lw_send_payment_link_message"  />
                <!-- Product List -->
                <ul class="list-unstyled col lw-addon-sps-products-container pt-3">
                    <template x-if="shopifyProductsLoadingText">
                    <div class="text-center py-4" x-text="shopifyProductsLoadingText"></div>
                    </template>
                    <template x-for="product in filteredProductsCollection" :key="product.id">
                        <li class="lw-addon-shopify-thumbnail-box">
                            <label :for="'shopify_product_'+ product.handle" class="mb-0">
                            <!-- Radio Button -->
                            <input :id="'shopify_product_'+ product.handle" type="radio" @click="selected_product_handle = product.handle;selected_product_title = product.title;selected_product_image_url = product.images[0]['src'];" name="product" class="mr-3">
                            <!-- Thumbnail -->
                            <img :src="product.images[0]['src']" class="w-16 h-16 object-cover rounded mr-4 lw-addon-shopify-thumbnail">
                            <!-- Product Info -->
                            <a target="_blank" :href="'{{ getVendorSettings('lw_addon_sps_store_link') }}/products/' + product.handle" class="lw-addon-shopify-thumbnail" x-text="product.title"></a>
                        </label>
                        <hr>
                        </li>
                    </template>
                </ul>
            </div>
            <x-lw.form id="lwAddonSendShopifyProductsForm" :action="route('addon.shopify_product_send.vendor.send_shopify_product.write')"
            :data-callback-params="['modalId' => '#lwAddonSendShopifyProductsModel']"
            data-callback="appFuncs.modelSuccessCallback">
                        <!-- form body -->
            <div id="lwAddonSendShopifyProductsBody" class="lw-form-modal-body" ></div>
            <script type="text/template" id="lwAddonSendShopifyProductsBody-template">
                <input type="hidden" name="contactIdOrUid" value="<%- __tData.contactUid %>" />
                <input type="hidden" x-model="selected_product_handle" name="selected_product_handle" value="" />
                <input type="hidden" x-model="selected_product_title" name="selected_product_title" value="" />
                <input type="hidden" x-model="selected_product_image_url" name="selected_product_image_url" value="" />
            </script>
                    <!-- form footer -->
        <div class="modal-footer">
            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary">{{ __('Send') }}</button>
            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Close') }}</button>
        </div>
    </x-lw.form>
        </div>
    <!--/  Edit Contact Form -->
</x-lw.modal>
@endpush
<script>
    (function() {
       'use strict';
    document.addEventListener('alpine:init', () => {
       Alpine.data('loadShopifyProducts', () => ({
        searchQuery: '',
        shopifyProducts: [],
        shopifyProductsLoadingText: `{{ __tr('Please wait while load products for you.') }}`,
        filteredProductsCollection : function() {
          return this.shopifyProducts.filter(product =>
            product.title.toLowerCase().includes(this.searchQuery.toLowerCase())
          );
        }
    }));
    });
})();
</script>
@endif