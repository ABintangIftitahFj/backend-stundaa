<script>
document.addEventListener('DOMContentLoaded', function() {

    var __incomingCallRTCPeerConnection = [];

    /******************************************************************************************
     * Initialize RTCPeer Connection for Incoming call
    *******************************************************************************************/
   window.initializeRTCPeerConnection = function (uniqueId) {
        __incomingCallRTCPeerConnection[uniqueId] = new RTCPeerConnection({ iceServers: [{ urls: "stun:stun.l.google.com:19302" }] });
   };

    // Get Incoming Call ringtone element
    const incomingCallRingtone = document.getElementById("lwWhatsappIncomingCallRingtone"),
        lwIncomingCallStatus = document.getElementById('lwIncomingCallStatus'),
        lwIncomingCallRingingDots = document.getElementById('lwIncomingCallRingingDots'),
        lwIncomingCallConnectedControls = document.getElementById('lwIncomingCallConnectedControls'),
        lwIncomingCallActions = document.getElementById('lwIncomingCallActions'),
        lwIncomingCallEndAction = document.getElementById('lwIncomingCallEndAction'),
        lwIncomingCallTimer = document.getElementById('lwIncomingCallTimer'),
        lwIncomingCallScreen = document.getElementById('lwIncomingCallScreen');

    // Get incoming call counter element
    window.isCallInProgress = false;
    window.isCallIsRinging = false;
    window.isCallAccepted = false;
    window.allIncomingCallData = {};
    var timerInterval = {},
        callInProgressArray = {},
        secondsObj = {};

    /******************************************************************************************
     * Format Incoming call timer
    *******************************************************************************************/
    const formatTime = (s) => {
      const m = Math.floor(s/60).toString().padStart(2,'0');
      const sec = (s%60).toString().padStart(2,'0');
      return `${m}:${sec}`;
    };

    /******************************************************************************************
     * Sanitize incoming call SDP, it is coming through Whatsapp Webhook
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
     * When whatsapp user/contact initiate call to business account, then whatsapp send webhook
     * with SDP.
    *******************************************************************************************/    
    window.handleIncomingCall = async function (whatsappSdp, uniqueId) {
        if (_.isEmpty(whatsappSdp)) {
            return false;
        }

        // Initialize new RTC Peer connection
        initializeRTCPeerConnection(uniqueId);

        const __audioPeerConnection = new RTCPeerConnection();

        // When remote audio track arrives
        __audioPeerConnection.ontrack = (event) => {
            let audio = document.getElementById(`incomingAudio_${uniqueId}`);
            if (!audio) {
                audio = document.createElement('audio');
                audio.id = `incomingAudio_${uniqueId}`;
                audio.autoplay = true;
                document.body.appendChild(audio);
            }
            audio.srcObject = event.streams[0];
        };

        __incomingCallRTCPeerConnection[uniqueId] = __audioPeerConnection;

        // Play ringtone
        incomingCallRingtone.play().catch(err => err);
        
        // Sanitize incoming SDP
        const sanitized = sanitizeSdp(whatsappSdp);

        // Check ICE connection: i.e connected, disconnected etc.
        __incomingCallRTCPeerConnection[uniqueId].oniceconnectionstatechange = () => {};

        // received incoming audio stream and add track to our audio control
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => __audioPeerConnection.addTrack(track, stream));
        } catch (err) {
            return;
        }

        // Set SDP to remote connection
        await __incomingCallRTCPeerConnection[uniqueId].setRemoteDescription({ type: 'offer', sdp: sanitized });

        // Create an answer SDP and set to local connection and send back to whatsapp
        const answer = await __incomingCallRTCPeerConnection[uniqueId].createAnswer();

        updateAlpineArray('lwIncomingCallData', uniqueId, 'answer', answer);

        const incomingCallData = Alpine.$data(document.querySelector('#lwIncomingCallData'));

        __DataRequest.post('{{ route("addon.vendor.write.process_update_call_details") }}', 
        incomingCallData.callDetails[uniqueId], 
            async function(responseData) {}
        );
        
        // Open incoming call dialog and hide other open dialog
        $('.modal').modal('hide');
        window.isCallIsRinging = true;
    };

    /******************************************************************************************
     * When business user accept incoming call
    *******************************************************************************************/
    window.acceptIncomingCall = async function(answer, callId, uniqueId) {
        incomingCallRingtone.pause();
        incomingCallRingtone.currentTime = 0;
        window.isCallAccepted = true;
        
        // Disable all other incoming call accept button
        // $('#lwWhatsAppCallMainContainer [id="lwIncomingCallAcceptBtn"]').addClass('disabled').prop('disabled', true);
        
        // Answer user initiated call
        __DataRequest.post('{{ route("addon.vendor.write.answer_user_initiated_call") }}', {
            'answerData': JSON.stringify({ sdp: answer.sdp }),
            'callId': callId,
            'type': 'accept',
            'uniqueId': uniqueId,
            'answer_status': 'MANUAL_ACCEPTED'
        }, async function(responseData) {
            if (responseData.reaction == 1) {
                window.isCallInProgress = true;
                window.isCallIsRinging = false;
                callInProgressArray[uniqueId] = true;
                await __incomingCallRTCPeerConnection[uniqueId].setLocalDescription(answer);
                // start timer
                secondsObj[uniqueId] = 0;
                updateAlpineArray('lwIncomingCallData', uniqueId, {
                    incomingCallTimer: formatTime(secondsObj[uniqueId]),
                    isCallRinging: false,
                });

                timerInterval[uniqueId] = setInterval(() => { 
                    secondsObj[uniqueId]++;
                    updateAlpineArray('lwIncomingCallData', uniqueId, {
                        incomingCallTimer: formatTime(secondsObj[uniqueId])
                    });
                }, 1000);

                updateAlpineArray('lwIncomingCallData', uniqueId, {
                    // isCallConnected: true,
                    callStatus: "{{ __tr('Connected') }}",
                    isCallRinging: false,
                });
                
            }
        });
    };

    /******************************************************************************************
     * When business user reject incoming call
    *******************************************************************************************/
    window.rejectIncomingCall = function(callId, uniqueId) {
        if (callId) {
            // Check rejection type - Reject call before receive OR after received
            let type = 'reject';
            // Check if call is in progress
            if (callInProgressArray[uniqueId]) {
                type = 'terminate';
                // end call
                updateAlpineArray('lwIncomingCallData', uniqueId, {
                    callStatus: "{{ __tr('Call Ended') }}",
                    isCallRinging: false,
                    incomingCallTimer: '—'
                });

                clearInterval(timerInterval[uniqueId]);
            } else {
                // show rejected state
                updateAlpineArray('lwIncomingCallData', uniqueId, {
                    callStatus: "{{ __tr('Call Rejected') }}",
                    isCallRinging: false,
                });
            }

            // Answer user initiated call
            __DataRequest.post('{{ route("addon.vendor.write.answer_user_initiated_call") }}', {
                'callId': callId,
                'type': type,
                'uniqueId': uniqueId,
                'answer_status': 'MANUAL_REJECTED'
            }, function(responseData) {
                if (responseData.data.success) {
                    window.hideWhatsappIncomingCallingModel(uniqueId);
                }
            });

            window.isCallIsRinging = false;
        }
    };

    /******************************************************************************************
     * Hide incoming call modal when anyone (business / user) reject / terminate call
    *******************************************************************************************/
    window.hideWhatsappIncomingCallingModel = function(uniqueId) {
        
        // Stop rengtone and // Set timer to 0
        incomingCallRingtone.pause();
        incomingCallRingtone.currentTime = 0;
        
        // Stop sending local mic tracks
        if (!_.isEmpty(__incomingCallRTCPeerConnection[uniqueId]?.getSenders())) {
            __incomingCallRTCPeerConnection[uniqueId].getSenders().forEach(sender => {
                if (sender.track) {
                    sender.track.stop();
                    __incomingCallRTCPeerConnection[uniqueId].removeTrack(sender);
                }
            });
        }

        if (!_.isEmpty(__incomingCallRTCPeerConnection[uniqueId]?.getReceivers())) {
            __incomingCallRTCPeerConnection[uniqueId].getReceivers().forEach(receiver => {
                if (receiver.track) receiver.track.stop();
            });
        }

        // Close any active data channels (if you have them)
        if (!_.isEmpty(__incomingCallRTCPeerConnection[uniqueId]?.dataChannels)) {
            __incomingCallRTCPeerConnection[uniqueId].dataChannels?.forEach(dc => {
                if (dc.readyState !== 'closed') dc.close();
            });
        }

        // Finally, close the connection
        if (__incomingCallRTCPeerConnection[uniqueId]) {
            __incomingCallRTCPeerConnection[uniqueId].close();
        }

        // Optional: clear the variable to avoid reuse
        __incomingCallRTCPeerConnection[uniqueId] = null;

        clearInterval(timerInterval[uniqueId]);
        timerInterval[uniqueId] = null;
        secondsObj[uniqueId] = 0;
        window.isCallInProgress = false;
        window.isCallIsRinging = false;
        callInProgressArray[uniqueId] = false;

        $('#lwIncomingWhatsAppCallContainer'+uniqueId).fadeOut(200, function() {
            $(this).remove();
        });

        const allCallsDataElement = document.getElementById('lwWhatsAppCallMainContainer');
        var allCallsExistingData = Alpine.$data(allCallsDataElement).allCallsData;
        if (!_.isUndefined(allCallsExistingData[uniqueId])) {
            delete allCallsExistingData[uniqueId];  
        }
    };

    // Append whatsapp calling container to body
    if ($('#lwWhatsAppCallMainContainer').length === 0) {
        const container = $('<div id="lwWhatsAppCallMainContainer" x-data="{ allCallsData: [] }" class="lw-whatsapp-call-main-container"></div>');
        $('body').append(container);
    }

    /******************************************************************************************
     * Open Incoming and outgoing call widget.
    *******************************************************************************************/
    window.openWidget = function (callContainer) {
        const wpIncomingCallContainer = $(callContainer).clone();
        wpIncomingCallContainer.removeClass('d-none');
        $('#lwWhatsAppCallMainContainer').append(wpIncomingCallContainer);
    };

    /******************************************************************************************
     * Update nested level of alpine Data
    *******************************************************************************************/
    window.updateAlpineArray = function (el, index, keyItem, newValue) {
        const component = Alpine.$data(document.querySelector(`#${el}`));
        
        if (component && component.callDetails && component.callDetails[index] !== undefined) {
            const item = component.callDetails[index];

            if (typeof keyItem === 'object' && typeof item === 'object' && item[keyItem] !== null && !Array.isArray(item[keyItem])) {
                // Merge object keys (preserve old ones)
                component.callDetails[index] = {
                    ...item,
                    ...keyItem
                };
            } else {
                // Replace directly for primitive values
                component.callDetails[index][keyItem] = newValue;
            }

        } else {
            console.warn('Invalid component ID or array index');
        }
    };
});
</script>