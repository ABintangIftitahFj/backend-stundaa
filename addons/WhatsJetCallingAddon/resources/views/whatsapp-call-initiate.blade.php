/******************************************************************************************
 * Check the incoming data is Whatsapp calling webhook data
*******************************************************************************************/
if (data.is_webhook_data) {
    
    if ((!isRestrictedVendorUser || (isRestrictedVendorUser && (data.assignedUserId == loggedInUserId)))) {
        
        // Check if it is incoming call
        if (data.is_incoming_call) {
            openWidget($(data.templateData));

            _.defer(function() {
                const allCallsDataElement = document.getElementById('lwWhatsAppCallMainContainer');
                var allCallsExistingData = Alpine.$data(allCallsDataElement).allCallsData;
                allCallsExistingData[data.contact_phone_number] = data.whatsAppCallData;
                window.allIncomingCallData[data.contact_phone_number] = data.whatsAppCallData;
            });        

            handleIncomingCall(data.sdp, data.contact_phone_number);
        }

        // Check if if incoming or outgoing call rejected
        if (data.is_call_terminated) {
            if (data.call_direction == 'USER_INITIATED') {
                window.hideWhatsappIncomingCallingModel(data.contact_phone_number);
            } else if (data.call_direction == 'BUSINESS_INITIATED') {
                window.hideWhatsappOutgoingCallingModel(data.contact_phone_number)
            }        
        }

        // Check if coming webhook is outgoing call
        if (data.is_outgoing_call && data.status != 'REJECTED') {
            _.defer(function() {
                const allCallsDataElement = document.getElementById('lwWhatsAppCallMainContainer');
                var allCallsExistingData = Alpine.$data(allCallsDataElement).allCallsData;
                allCallsExistingData[data.contact_phone_number] = data.whatsAppCallData;
            });        

            handleOutgoingCall(data);
        }
    }
    
    // Check for if one call accepted then remove other calls, and also if call reject then remove them from all team member
    if (!_.isUndefined(data.remove_call_widget_when_accept_call) && data.remove_call_widget_when_accept_call) {
        var userId = '{{ getUserID() }}';
        if ((userId != data.user_id) || (!window.isCallAccepted)) {
            hideWhatsappIncomingCallingModel(data.contact_phone_number);
        }
        
        if (userId == data.user_id && !_.isEmpty(data.other_incoming_calls)) {
            _.forEach(data.other_incoming_calls, function(value) {
                hideWhatsappIncomingCallingModel(value);
            })
        }

        if (userId == data.user_id && !_.isEmpty(data.other_outgoing_calls)) {
            _.forEach(data.other_outgoing_calls, function(value) {
                hideWhatsappOutgoingCallingModel(value);
            })
        }
    }

    // Check if outgoing call accepted
    if (!_.isUndefined(data.is_outgoing_call_accepted) && data.is_outgoing_call_accepted) {
        if (!_.isEmpty(window.allIncomingCallData) && loggedInUserId == data.by_user_id) {
            _.forEach(window.allIncomingCallData, function(item, index) {
                if (data.contact_phone_number != index) {
                    hideWhatsappIncomingCallingModel(item.uniqueId);
                }
            });
        }
    }
};