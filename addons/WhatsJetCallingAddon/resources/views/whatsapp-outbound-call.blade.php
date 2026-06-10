<script>
document.addEventListener('DOMContentLoaded', function() {

    const outgoingCallOriginalHtml = $("#lwWhatsappCallingModal").html();
    let __outgoingCallRTCPeerConnection = null;

    /******************************************************************************************
     * Initialize RTCPeer Connection for Outgoing call
    *******************************************************************************************/
   window.initializeOutgoingCallRTCPeerConnection = function() {
    __outgoingCallRTCPeerConnection = new RTCPeerConnection({ iceServers: [{ urls: "stun:stun.l.google.com:19302" }] });

    // Receiving an incoming stream of audio from other device
    // and assign them to aur <audio> media
    __outgoingCallRTCPeerConnection.ontrack = (event) => {
        const outgoingCallAudio = document.getElementById('outgoingAudio');
        outgoingCallAudio.srcObject = event.streams[0];
    };
   };

    // Get Outgoing Call ringtone element
    var outgoingCallRingtone = document.getElementById("lwWhatsappOutgoingCallRingtone"),
        isOutgoingCallInProgress = false,
        isOutgoingCallRinging = false,
        outgoingCallTimerInterval = null;
    
    /******************************************************************************************
     * To start outgoing call counter when contact/user receive/accept call
    *******************************************************************************************/
    const startOutgoingCallCounter = (uniqueId) => {
        outgoingCallRingtone.pause();
        seconds = 0;
        __DataRequest.updateModels({
            isCallConnected: true
        });

        updateAlpineArray('lwOutgoingCallData', uniqueId, {
            outgoingCallTimer: "{{ __tr('00:00') }}",
            isCallRinging: false
        });
        
        outgoingCallTimerInterval = setInterval(() => {
            seconds++;
            const mins = String(__Utils.formatAsLocaleNumber(Math.floor(seconds / 60))).padStart(2, __Utils.formatAsLocaleNumber(0));
            const secs = String(__Utils.formatAsLocaleNumber(seconds % 60)).padStart(2, __Utils.formatAsLocaleNumber(0));
            updateAlpineArray('lwOutgoingCallData', uniqueId, {
                outgoingCallTimer: `${mins}:${secs}`,
                isCallRinging: false
            });
        }, 1000);
    };

    /******************************************************************************************
     * To stop counter when user/contact terminate the call
    *******************************************************************************************/
    const stopOutgoingCallCounter = () => {
        clearInterval(outgoingCallTimerInterval);
    };

    /******************************************************************************************
     * Sanitize outgoing call SDP, it is coming through Whatsapp Webhook
    *******************************************************************************************/
    window.sanitizeSdp = function (sdp) {
        const lines = sdp.split(/\r?\n/);
        let clean = [];
        let addedOpus = false;

        for (const line of lines) {
            clean.push(line);
        }

        return clean.join("\r\n") + "\r\n";
    };

    /******************************************************************************************
     * Prepare outgoing call, check for ICE state and then send offer to whatsapp API
     * then close all opened modal and show outgoing call modal
    *******************************************************************************************/
    window.prepareOutgoingCall = async function(userWaId) {

        $('#lwStartCallButton').addClass('d-none');
        $('#lwConnectingButton').removeClass('d-none');

        // Initialize Outgoing call RTC Peer connection
        initializeOutgoingCallRTCPeerConnection();

        // Check ICE connection: i.e connected, disconnected etc.
        __outgoingCallRTCPeerConnection.oniceconnectionstatechange = () => {};

        // received incoming audio stream and add track to our audio control
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => __outgoingCallRTCPeerConnection.addTrack(track, stream));
        } catch (err) {
            return;
        }

        // Create offer from local browser
        const offer = await __outgoingCallRTCPeerConnection.createOffer();
        
        // Send offer to whatsapp for outgoing call
        __DataRequest.post('{{ route("addon.vendor.write.business_initiated_call") }}', {
            'phone_number': userWaId, 
            'sdp': offer.sdp
        }, function(responseData) {
            if (responseData.data.success) {
                isOutgoingCallRinging = true;
                var requestData = responseData.data;
                hidePermissionDetailsModal();
                openWidget($(requestData.outgoingCallData.templateData));
                const avatar = document.getElementById('lwOutgoingCallAvatar');
                avatar.textContent = requestData.outgoingCallData.initials;
                
            } else {
                $('#lwStartCallButton').removeClass('d-none');
                $('#lwConnectingButton').addClass('d-none');
            }
        });
    };

    /******************************************************************************************
     * Hide permission dialog when call initiate by business
    *******************************************************************************************/
    window.hidePermissionDetailsModal = function() {
        $("#lwWhatsappCallOutboundButton").modal('hide');
        $('.modal-backdrop').remove();     // remove overlay
        $('body').removeClass('modal-open'); // restore scroll
    };

    /******************************************************************************************
     * WhatsApp sends the SDP through a webhook, along with different call statuses 
     * (RINGING | ACCEPTED | REJECTED). We use this webhook to trigger actions such as playing 
     * the ringtone, or handling call acceptance or rejection.
    *******************************************************************************************/
    window.handleOutgoingCall = async function (data) {
        // Sanitize SDP received from whatsapp webhook
        if (data.is_outgoing_call && _.isEmpty(data.status) && __outgoingCallRTCPeerConnection) {            
            const sanitized = sanitizeSdp(data.sdp);
            // Set SDP to remote description which is received from whatsapp
            await __outgoingCallRTCPeerConnection.setRemoteDescription({ type: 'offer', sdp: sanitized });
            // Create and set answer to local description
            const answer = await __outgoingCallRTCPeerConnection.createAnswer();
            await __outgoingCallRTCPeerConnection.setLocalDescription(answer);
        }

        // When user whatsapp is Ringing then we received this webhook
        if (data.status == 'RINGING') {
            outgoingCallRingtone.play().catch(err => alert("Autoplay blocked:", err));
        }

        // When user accept call then we received this webhook
        if (data.status == 'ACCEPTED') {
            outgoingCallRingtone.pause();
            startOutgoingCallCounter(data.contact_phone_number);
            isOutgoingCallInProgress = true;
        }

        // When user reject call then we received this webhook
        if (data.status == 'REJECTED') {
            outgoingCallRingtone.pause();
            stopOutgoingCallCounter();
            isOutgoingCallInProgress = false;            

            updateAlpineArray('lwOutgoingCallData', uniqueId, {
                outgoingCallTimer: '-',
                callStatus: "{{ __tr('Call Rejected') }}"
            });
        }
    };

    /******************************************************************************************
     * Whenever business Reject OR Terminate outgoing call
    *******************************************************************************************/
    window.rejectOutgoingCallCall = function(callId, uniqueId) {
        // Check rejection type - Reject call before receive OR after received
        let type = 'terminate';

        updateAlpineArray('lwOutgoingCallData', uniqueId, {
            outgoingCallTimer: '-',
            callStatus: "{{ __tr('Call Ended') }}"
        });

        // Answer user initiated call
        __DataRequest.post('{{ route("addon.vendor.write.answer_user_initiated_call") }}', {
            'callId': callId,
            'type': type,
            'uniqueId': uniqueId,
            'answer_status': 'MANUAL_REJECTED'
        }, function(responseData) {
            if (responseData.data.success) {
                window.hideWhatsappOutgoingCallingModel(uniqueId);
            }
        });
    };

    /******************************************************************************************
     * Hide outgoing call modal when anyone (business / user) reject / terminate call
    *******************************************************************************************/
    window.hideWhatsappOutgoingCallingModel = function(uniqueId) {
        // Stop rengtone and // Set timer to 0
        outgoingCallRingtone.pause();
        outgoingCallRingtone.currentTime = 0;

        // Stop sending local mic tracks
        if (!_.isEmpty(__outgoingCallRTCPeerConnection?.getSenders())) {
            __outgoingCallRTCPeerConnection.getSenders().forEach(sender => {
                if (sender.track) {
                    sender.track.stop();
                    __outgoingCallRTCPeerConnection.removeTrack(sender);
                }
            });
        }

        if (!_.isEmpty(__outgoingCallRTCPeerConnection?.getReceivers())) {
            __outgoingCallRTCPeerConnection.getReceivers().forEach(receiver => {
                if (receiver.track) receiver.track.stop();
            });
        }

        // Close any active data channels (if you have them)
        if (!_.isEmpty(__outgoingCallRTCPeerConnection?.dataChannels)) {
            __outgoingCallRTCPeerConnection.dataChannels?.forEach(dc => {
                if (dc.readyState !== 'closed') dc.close();
            });
        }

        // Finally, close the connection
        if (__outgoingCallRTCPeerConnection) {
            __outgoingCallRTCPeerConnection.close();
        }

        // Optional: clear the variable to avoid reuse
        __outgoingCallRTCPeerConnection = null;
        $("#lwWhatsappCallingModal").html(outgoingCallOriginalHtml);

        stopOutgoingCallCounter();
        isOutgoingCallInProgress = false;
        isOutgoingCallRinging = false;

        $('#lwOutgoingWhatsAppCallContainer'+uniqueId).fadeOut(200, function() {
            $(this).remove();
        });

        const allCallsDataElement = document.getElementById('lwWhatsAppCallMainContainer');
        var allCallsExistingData = Alpine.$data(allCallsDataElement).allCallsData;
        if (!_.isUndefined(allCallsExistingData[uniqueId])) {
            delete allCallsExistingData[uniqueId];  
        }
    };

    window.onbeforeunload = function (e) {
        if(isOutgoingCallRinging || isOutgoingCallInProgress || window.isCallInProgress || window.isCallIsRinging) {
            var message = "{{ __tr('A call is currently active or ringing. If you refresh the page, the call will be terminated..') }}",
            e = e || window.event;
            // For IE and Firefox
            if (e) {
                e.returnValue = message;
            }
            // For Safari
            return message;
        };
    };
});
</script>