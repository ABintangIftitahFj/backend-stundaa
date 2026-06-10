@if (isDemo())
    <div class="p-0">
        <div class="alert alert-warning">
            <strong>{{  __tr('Demo Alert:') }}</strong>
            {{  __tr('Please note this is addon feature') }}
        </div>
    </div>
@endif
@php
    $phoneNumberId = getVendorSettings('current_phone_number_id');
    $enableWhatsappCalling = (!__isEmpty(getVendorSettings('lw_addon_enable_whatsapp_calling', $phoneNumberId, false))) ? true : false;
    $vendorPlanDetails = vendorPlanDetails('WhatsJetCallingAddon', 1, getVendorId());
@endphp
@if($vendorPlanDetails['is_limit_available'])
<div x-data="{
    submitForm: function (enableWhatsappCalling) {
        $('#lwWhatsAppEnableCallingApiForm').submit();
    },
    enableWhatsappCalling: '{{ $enableWhatsappCalling }}'
}">
    <!-- whatsapp cloud api setup form -->
    <form id="lwWhatsAppEnableCallingApiForm"
        class="lw-ajax-form lw-form" name="whatsapp_setup_calling_api_form" method="post"
        action="<?= route('addon.vendor.write.process_store_vendor_setting') ?>">
        <input type="hidden" name="phone_number_id" x-bind:value="whatsAppPhoneNumber.id">
        <input type="hidden" name="pageType" value="lw_addon_whatsapp_calling">
        <input type="hidden" name="lw_addon_enable_whatsapp_calling" x-bind:value="!enableWhatsappCalling ? 1 : 0">
        <!-- set hidden input field with form type -->

        <x-lw.checkbox id="lwEnableWhatsappCalling" :offValue="0" :checked="getVendorSettings('lw_addon_enable_whatsapp_calling', $phoneNumberId, false)" data-lw-plugin="lwSwitchery" :label="__tr('Enable WhatsApp Calling')" x-model="enableWhatsappCalling" @click="submitForm(!enableWhatsappCalling)"/>
    </form>
</div>
@endif

@push('appScripts')
<script>
window.lwPluginsInit();
</script>
@endpush