@php
    $phoneNumberId = getVendorSettings('current_phone_number_id');
    $vendorPlanDetails = vendorPlanDetails('WhatsJetCallingAddon', 1, getVendorId());
@endphp
@if(getVendorSettings('lw_addon_enable_whatsapp_calling', $phoneNumberId, false) and $vendorPlanDetails['is_limit_available'])
    <a title="{{ __tr('Whatsapp Voice Call') }}" href="#" class="lw-whatsapp-bar-icon-btn mr-3 text-white lw-ajax-link-action" data-response-template="#lwWhatsappCallOutboundButtonBody" x-bind:href="__Utils.apiURL('{{ route('addon.vendor.read.get_user_call_permission', ['contactUid']) }}', { 'contactUid': contact?._uid })"  data-toggle="modal" data-target="#lwWhatsappCallOutboundButton" data-pre-callback="appFuncs.clearContainer">
        <i class="fas fa-phone fa-rotate-90"></i>
    </a>
@endif