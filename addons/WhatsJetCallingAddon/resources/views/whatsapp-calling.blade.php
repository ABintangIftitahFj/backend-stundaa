@php
    $phoneNumberId = getVendorSettings('current_phone_number_id');
@endphp
@if(getVendorSettings('lw_addon_enable_whatsapp_calling', $phoneNumberId, false))
@php
$cssFile = public_path('WhatsJetCallingAddon/whatsapp-addon.css');
@endphp
@if(file_exists($cssFile))
<link rel="stylesheet" href="{{ asset('WhatsJetCallingAddon/whatsapp-addon.css') }}">
@endif

<!-- Whatsapp Incoming/Outgoing Call Container -->
<div id="lwWhatsappCallingContainer">
    <audio id="lwWhatsappIncomingCallRingtone" src="<?= asset('/static-assets/audio/whatsapp_incoming_call.mp3'); ?>" loop></audio>
    <audio id="lwWhatsappOutgoingCallRingtone" src="<?= asset('/static-assets/audio/whatsapp_outgoing_tone.mp3'); ?>" loop></audio>
</div>
<!-- /Whatsapp Incoming/Outgoing Call Container -->

<!-- Whatsapp Outbound Calling Modal -->
<x-lw.modal id="lwWhatsappCallOutboundButton" :header="__tr('Whatsapp Calling')" :hasForm="true">
        <div id="lwWhatsappCallOutboundButtonBody" class="lw-form-modal-body"></div>
        <script type="text/template" id="lwWhatsappCallOutboundButtonBody-template">
            @if (isDemo())
                <div class="m-4">
                    <div class="alert alert-warning">
                        <strong>{{  __tr('Demo Alert:') }}</strong>
                        {{  __tr('Please note this is addon feature') }}
                    </div>
                </div>
            @endif
            <% if (__tData.isLimitAvailable) { %>
            <% if (!__tData.isAnyCallAlreadyInProgress) { %>
            <fieldset class="text-center">
                <legend for="">{{  __tr('Call Permission') }}</legend>
                <h3>
                    <% if (__tData.permission_status_key == 'no_permission') { %>
                        <div>
                            <i class="fa fa-3x fa-ban text-danger"></i>
                        </div>
                    <% } %>
                   <strong>{{ __tr('Current Status') }}: <%= __tData.permission_status %></strong>
                </h3>
                <div class="card-header">
                    <dl>
                        <% if (!_.isEmpty(__tData?.expire_at)) { %>
                            <dt>{{ __tr('Expire At') }}</dt>
                            <dd><%= __tData.expire_at %></dd>
                        <% } %>
                        <dt>{{ __tr('Able to initiate a call?') }}</dt>
                        <% if (__tData?.start_call) { %>
                        <div class="mt-3">
                            <i class="fa fa-2x fa-check-circle text-success"></i>
                        </div>
                        <% } else { %>
                            <div class="mt-3">
                            <i class="fa fa-2x fa-ban text-danger"></i>
                        </div>
                        <% } %>
                        <dd><%= __tData?.start_call ? "{{ __tr('Yes') }}" : "{{ __tr('No') }}" %></dd>
                        <dt>{{ __tr('Outgoing Call Limit Allowed') }}</dt>
                        <dd><h2><%= __tData.call_limit_allowed %></h2></dd>
                        <dt>{{ __tr('Able to send call permission requests?') }}</dt>
                        <% if (__tData?.send_call_request_permission) { %>
                        <div class="mt-3">
                            <i class="fa fa-2x fa-check-circle text-success"></i>
                        </div>
                        <% } else { %>
                            <div class="mt-3">
                            <i class="fa fa-2x fa-ban text-danger"></i>
                        </div>
                        <% } %>
                        <dd><%= __tData.send_call_request_permission ? "{{ __tr('Yes') }}" : "{{ __tr('No') }}" %></dd>
                    </dl>
                </div>
                <x-lw.form id="lwWhatsappCallOutboundButtonForm" class="text-center" :action="route('addon.vendor.write.send_free_form_call_permission_request')" :data-callback-params="['modalId' => '#lwWhatsappCallOutboundButton']" data-callback="appFuncs.modelSuccessCallback">
                    <% if(__tData.send_call_request_permission && (__tData?.permission_status_key != 'permanent')) { %>
                        <input type="hidden" name="user_wa_id" value="<%= __tData.user_wa_id %>">
                        <button type="submit" class="btn btn-primary mt-4">{{ __tr('Send Call Permission Request') }}</button>
                    <% } %>
                </x-lw.form>
                <% if (__tData?.start_call) { %>
                    <button id="lwStartCallButton" type="button" class="btn btn-primary btn-lg mt-4 mr-2" @click="prepareOutgoingCall(<%= __tData.user_wa_id %>)"> <i class="fa fa-phone-alt"></i> {{ __tr('Start Call') }}</button>

                    <button id="lwConnectingButton" type="button" class="btn btn-primary btn-lg mt-4 mr-2 d-none" disable>{{ __tr('Connecting...') }}</button>
                <% } %>
            </fieldset>
            <% } else { %>
            <div class="text-center">
                <div class="alert alert-warning m-4">
                {{ __tr('A previous call is still in progress, please end the current one.') }}                
                </div>
                <button type="button" class="btn btn-danger mr-2" @click="endInProgressCall('<%= __tData.inProgressCallId %>')"> <i class="fa fa-ban"></i> {{ __tr('End Now') }}</button>
            </div>
            <% } %>
            <% } else { %>
                <div class="alert alert-danger">
                    {{  __tr('Whatsapp Calling Access is not available in your plan, please upgrade your subscription plan.') }}
                </div>
            <% } %>
        </script>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __tr('Close') }}</button>
        </div>
    
</x-lw.modal>
<!--/ Whatsapp Outbound Calling Modal -->
@endif

@push('appScripts')
<script>
    window.endInProgressCall = function(inProgressCallId) {
        __DataRequest.post('{{ route('addon.vendor.write.stop_in_progress_call') }}', {
            in_progress_call_id: inProgressCallId
        }, function(responseData) {
            var requestData = responseData.data;
            hidePermissionDetailsModal();
        });
    }
</script>
@endpush