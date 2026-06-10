<!-- Incoming Call Widget -->
@if($isIncomingCall)
    <div id="lwIncomingWhatsAppCallContainer{{ $contactPhoneNumber }}">
        <div id="lwIncomingCallData" class="lw-whatsapp-call-container" x-data="{
            callDetails: allCallsData,
            uniqueId: '{{ $contactPhoneNumber }}', 
            get isCallRinging() {
                return this.callDetails[this.uniqueId]?.isCallRinging ?? false;
            },
            get contactName() {
                return this.callDetails[this.uniqueId]?.contactName ?? '';
            },
            get callId() {
                return this.callDetails[this.uniqueId]?.callId ?? '';
            },
            get answer() {
                return this.callDetails[this.uniqueId]?.answer ?? '';
            },
            callStatus() {
                return this.callDetails[this.uniqueId]?.callStatus ?? '{{ __tr('Ringing • Mobile') }}';
            },
            get incomingCallTimer() {
                return this.callDetails[this.uniqueId]?.incomingCallTimer ?? '{{ __tr('00:00') }}';
            },
            mask(item) {
                if (window.appConfig.hide_contact_phone_number) {
                    if (!item || item.length <= 2) return item;
                    return item[0] + '*'.repeat(item.length - 2) + item[item.length - 1];
                } else {
                    return item;
                }
            }
        }">
            <div class="loader"></div>
            <div class="lw-whatsapp-call-header">
                <div class="lw-signal" aria-hidden></div>
                <div>
                    <div class="lw-whatsapp-call-title">{{ __tr('WhatsApp') }}</div>
                    <div class="lw-whatsapp-call-sub">{{ __tr('Incoming Call') }}</div>
                    <div x-show="!isCallRinging" style="opacity:.9" x-text="incomingCallTimer"></div>
                </div>
            </div>

            <section class="lw-whatsapp-call-main" id="lwIncomingCallScreen">
                <div class="lw-whatsapp-call-avatar" id="lwIncomingCallAvatar">{{ $initials }}</div>
                <div class="lw-incoming-call-user-name" id="lwIncomingCallCallerName"  x-text="contactName"></div>
                <div class="lw-incoming-call-mobile-number" x-text="mask(uniqueId)"></div>
                <div class="lw-incoming-call-status" x-text="callStatus"></div>

                <div x-show="isCallRinging" class="lw-incoming-call-ringing">
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                </div>

                <div x-show="isCallRinging" class="lw-incoming-call-actions">
                    <button class="lw-incoming-call-btn lw-incoming-call-decline" id="lwIncomingCallRejectBtn" @click="rejectIncomingCall(callId, uniqueId)">{{ __tr('Decline') }}</button>
                    <button class="lw-incoming-call-btn lw-incoming-call-accept" id="lwIncomingCallAcceptBtn" @click="acceptIncomingCall(answer, callId, uniqueId)">{{ __tr('Accept') }}</button>
                </div>
                <small x-show="isCallRinging">
                    <a href="#" class="link-dark text-dark float-right mt-2 text-primary" @click="hideWhatsappIncomingCallingModel(uniqueId)"><strong>{{ __tr('Ignore') }}</strong> >>></a>
                </small>
                <div x-show="!isCallRinging" class="lw-incoming-call-actions">
                    <button class="lw-incoming-call-btn lw-incoming-call-decline" id="lwIncomingCallEndBtn" @click="rejectIncomingCall(callId, uniqueId)">{{ __tr('End Call') }}</button>
                </div>
            </section>

            <div id="playbackContainer">
                <audio class="lw-recorded-audio d-none" id="incomingAudio_{{ $contactPhoneNumber }}" autoplay playsinline controls></audio>
            </div>
        </div>
    </div>
@endif
<!-- /Incoming Call Widget -->

<!-- Outgoing Call Widget -->
@if($isOutgoingCall)
    <div id="lwOutgoingWhatsAppCallContainer{{ $contactPhoneNumber }}">
        <div id="lwOutgoingCallData" class="lw-whatsapp-call-container" x-data="{
            callDetails: allCallsData,
            uniqueId: '{{ $contactPhoneNumber }}', 
            get isCallRinging() {
                return this.callDetails[this.uniqueId]?.isCallRinging ?? false;
            },
            get contactName() {
                return this.callDetails[this.uniqueId]?.contactName ?? '';
            },
            get callId() {
                return this.callDetails[this.uniqueId]?.callId ?? '';
            },
            callStatus() {
                return this.callDetails[this.uniqueId]?.callStatus ?? '{{ __tr('Calling • Mobile') }}';
            },
            get outgoingCallTimer() {
                return this.callDetails[this.uniqueId]?.outgoingCallTimer ?? '{{ __tr('00:00') }}';
            },
            mask(item) {
                if (window.appConfig.hide_contact_phone_number) {
                    if (!item || item.length <= 2) return item;
                    return item[0] + '*'.repeat(item.length - 2) + item[item.length - 1];
                } else {
                    return item; 
                }
            }
        }">
            <div class="lw-whatsapp-call-header">
                <div class="lw-signal" aria-hidden></div>
                <div>
                    <div class="lw-whatsapp-call-title">{{ __tr('WhatsApp') }}</div>
                    <div class="lw-whatsapp-call-sub">{{ __tr('Outgoing Call') }}</div>
                    <div x-show="!isCallRinging" style="opacity:.9" x-text="outgoingCallTimer"></div>
                </div>
            </div>

            <section class="lw-whatsapp-call-main" id="lwOutgoingCallScreen">
                <div class="lw-whatsapp-call-avatar" id="lwOutgoingCallAvatar"><i class="fa fa-phone-volume"></i></div>
                <div class="lw-incoming-call-user-name" id="lwOutgoingCallCalleeName" x-text="contactName"></div>
                <div class="lw-incoming-call-mobile-number" x-text="mask(uniqueId)"></div>
                <div class="lw-incoming-call-status" x-text="callStatus"></div>

                <div x-show="isCallRinging" class="lw-incoming-call-ringing" id="lwIncomingCallRingingDots">
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                    <div class="lw-incoming-call-pulse" aria-hidden></div>
                </div>

                <div class="lw-incoming-call-actions">
                    <button class="lw-incoming-call-btn lw-incoming-call-decline" id="lwOutgoingCallCancelBtn" @click="rejectOutgoingCallCall(callId, uniqueId)">{{ __tr('End Call') }}</button>
                </div>
            </section>

            <div id="playbackContainer">
                <audio class="lw-recorded-audio d-none" id="outgoingAudio" autoplay playsinline controls></audio>
            </div>
        </div>
    </div>
@endif
<!-- /Outgoing Call Widget -->