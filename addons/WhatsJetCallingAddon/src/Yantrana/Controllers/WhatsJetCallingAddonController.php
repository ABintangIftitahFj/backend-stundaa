<?php

namespace Addons\WhatsJetCallingAddon\Yantrana\Controllers;

use App\Events\VendorChannelBroadcast;
use App\Yantrana\Base\BaseRequestTwo;
use App\Yantrana\Base\AddonBaseController;
use Addons\WhatsJetCallingAddon\Yantrana\Services\WhatsAppCallConnectApiService;
use App\Yantrana\Components\WhatsAppService\Repositories\WhatsAppMessageLogRepository;
use App\Yantrana\Components\Contact\Repositories\ContactRepository;
use Addons\WhatsJetCallingAddon\Yantrana\Repositories\WhatsJetCallingAddonRepository;
use App\Yantrana\Components\WhatsAppService\WhatsAppServiceEngine;
use App\Yantrana\Components\Vendor\VendorSettingsEngine;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class WhatsJetCallingAddonController extends AddonBaseController
{
    /**
     * Addon Namespace
     *
     * @var string
     */
    protected $addonNamespace = "WhatsJetCallingAddon";

    /**
     * @var WhatsAppCallConnectApiService - WhatsApp Connect Service
     */
    protected $whatsAppCallConnectApiService;

    /**
     * @var WhatsAppMessageLogRepository - Status repository
     */
    protected $whatsAppMessageLogRepository;

    /**
     * @var ContactRepository - Contact Repository
     */
    protected $contactRepository;

    /**
     * @var WhatsAppServiceEngine - WhatsAppService Engine
     */
    protected $whatsAppServiceEngine;

    /**
     * @var VendorSettingsEngine - VendorSettings Engine
     */
    protected $vendorSettingsEngine;

    /**
     * @var WhatsJetCallingAddonRepository - Calling repository
     */
    protected $whatsJetCallingAddonRepository;

    /**
     * Constructor
     *
     * @param  WhatsAppCallConnectApiService  $whatsAppCallConnectApiService  - WhatsApp Connect Service
     * @param  WhatsAppMessageLogRepository  $whatsAppMessageLogRepository  - WhatsApp Message Log Repository
     * @param  ContactRepository  $contactRepository  - Contact Repository
     * @param  VendorSettingsEngine  $vendorSettingsEngine  - VendorSettings Engine
     * @param  WhatsJetCallingAddonRepository  $whatsJetCallingAddonRepository  - Calling repository
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct(
        WhatsAppCallConnectApiService $whatsAppCallConnectApiService,
        WhatsAppMessageLogRepository $whatsAppMessageLogRepository,
        ContactRepository $contactRepository,
        WhatsAppServiceEngine $whatsAppServiceEngine,
        VendorSettingsEngine $vendorSettingsEngine,
        WhatsJetCallingAddonRepository  $whatsJetCallingAddonRepository
    ) {
        $this->whatsAppCallConnectApiService = $whatsAppCallConnectApiService;
        $this->whatsAppMessageLogRepository = $whatsAppMessageLogRepository;
        $this->contactRepository = $contactRepository;
        $this->whatsAppServiceEngine = $whatsAppServiceEngine;
        $this->vendorSettingsEngine = $vendorSettingsEngine;
        $this->whatsJetCallingAddonRepository = $whatsJetCallingAddonRepository;
    }

    /**
     * Handle Whatsapp Calling API Webhook
     *
     * @param Array $webhookData
     * @param string|int $vendorUid
     * @return void
     */
    public function processWhatsappCallingWebhook($webhookData, $vendorUid)
    {
        emptyFlashCache(); // Clear flash cache before processing

        $vendorId = is_int($vendorUid) ? $vendorUid : getPublicVendorId($vendorUid);
        if (! $vendorId) {
            return false;
        }
        if (is_int($vendorUid)) {
            $vendorUid = getPublicVendorUid($vendorId);
        }

        $messageEntry = $webhookData['entry'];
        $phoneNumberId = data_get($messageEntry, '0.changes.0.value.metadata.phone_number_id');
        $calls = data_get($messageEntry, '0.changes.0.value.calls.0');
        $callId = data_get($calls, 'id');
        $callEvent = data_get($calls, 'event');
        $callDirection = data_get($calls, 'direction');
        $fromNumber = data_get($calls, 'from');
        $timestamp = data_get($calls, 'timestamp');
        $statuses = data_get($messageEntry, '0.changes.0.value.statuses.0');
        $whatsappWaId = $fromNumber;

        $whatsappPhoneNumber = getVendorSettings('whatsapp_phone_numbers', null, null, $vendorUid);
        $phoneNumberIds = [];
        // Check if whatsapp phone numbers exists
        if (!__isEmpty($whatsappPhoneNumber)) {
            foreach ($whatsappPhoneNumber as $phoneNumber) {
                $phoneNumberIds[] = $phoneNumber['id'];
            }
        }
        // Check if phone number exists in current vendor user
        if (!in_array($phoneNumberId, $phoneNumberIds)) {
            return false;
        }

        $vendorPlanDetails = vendorPlanDetails('WhatsJetCallingAddon', 1, $vendorId);
        // Check if addon limit is available for the vendor
        if (! $vendorPlanDetails['is_limit_available']) {
            return response()->json(['status' => 'ignored']);
        }

        // Set whatsapp calling data
        $whatsAppCallingData = [
            'is_webhook_data' => true,
            'is_incoming_call' => false,
            'is_call_terminated' => false,
            'is_outgoing_call' => false,
            'sdp' => null,
            'callId' => $callId,
            'userName' => '',
            'from' => $fromNumber,
            'status' => '',
            'call_direction' => $callDirection,
        ];

        $duration = data_get($calls, 'duration');
        $userAction = '';
        $callStatus = 'RINGING';
        // Check if whatsapp user initiate call and request for inbound call
        if ($callEvent == 'connect' and $callDirection == 'USER_INITIATED') {
            $callSdp = data_get($messageEntry, '0.changes.0.value.calls.0.session.sdp');
            $userName = data_get($messageEntry, '0.changes.0.value.contacts.0.profile.name');

            if (!__isEmpty($callSdp)) {
                $whatsAppCallingData['is_incoming_call'] = true;
                $whatsAppCallingData['sdp'] = $callSdp;
            }
            $callStatus = 'RINGING';
            // Check if Business Initiated Call
        } elseif ($callEvent == 'connect' and $callDirection == 'BUSINESS_INITIATED') {
            $callSdp = data_get($messageEntry, '0.changes.0.value.calls.0.session.sdp');
            $whatsappWaId = data_get($calls, 'to');
            if (!__isEmpty($callSdp)) {
                $whatsAppCallingData['is_outgoing_call'] = true;
                $whatsAppCallingData['sdp'] = $callSdp;
            }
            $callStatus = 'RINGING';
            // Check if call Reject or Terminate from both side (User and Business Account)
        } elseif (($callEvent == 'terminate') and ($callDirection == 'USER_INITIATED' or $callDirection == 'BUSINESS_INITIATED')) {
            $whatsAppCallingData['is_call_terminated'] = true;
            $whatsappWaId = ($callDirection == 'USER_INITIATED') ? $fromNumber : data_get($calls, 'to');
            $callStatus = 'TERMINATE';
            $userAction = ($duration > 0) ? 'TERMINATE' : 'REJECTED';
        } elseif (!__isEmpty($statuses)) {
            $whatsAppCallingData['is_outgoing_call'] = true;
            $whatsAppCallingData['status'] = data_get($statuses, 'status');
            $callId = $whatsAppCallingData['callId'] = data_get($statuses, 'id');
            $whatsappWaId = data_get($statuses, 'recipient_id');
            $callEvent = strtolower($whatsAppCallingData['status']);
            $callStatus = $whatsAppCallingData['status'];
        }

        // Check if multiple ringing status webhook received
        if ($callStatus == 'RINGING') {
            $existingCall = $this->whatsJetCallingAddonRepository->fetchIt([
                'wacid' => $callId,
                'status' =>  'RINGING',
                'call_direction' => 'USER_INITIATED'
            ]);

            // Check if call already exists
            if (!__isEmpty($existingCall)) {
                return false;
            }
        }

        $messageLogEntry = $this->whatsAppMessageLogRepository->fetchIt([
            'wamid' => $callId,
            'vendors__id' => $vendorId,
        ]);

        $contact = $this->contactRepository->fetchIt([
            'vendors__id' => $vendorId,
            'wa_id' => $whatsappWaId,
        ]);

        if (__isEmpty($contact)) {
            // check the feature limit
            $vendorPlanDetails = vendorPlanDetails('contacts', $this->contactRepository->countIt([
                'vendors__id' => $vendorId
            ]), $vendorId);
            if (!$vendorPlanDetails['is_limit_available']) {
                // return false;
                return false;
            }
            $profileName = Arr::get($messageEntry, '0.changes.0.value.contacts.0.profile.name');
            $firstName = Arr::get(explode(' ', $profileName), '0');
            $contact = $this->contactRepository->storeContact([
                'first_name' => $firstName,
                'last_name' => str_replace($firstName, ' ', $profileName),
                'phone_number' => $whatsappWaId,
            ], $vendorId);
        }

        $userFullName = $contact->first_name . ' ' . $contact->last_name;
        $initials = strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1));
        $whatsAppCallingData['userName'] = __tr('__userName__ calling...', ['__userName__' => $userFullName]);
        if ($callEvent == 'accepted') {
            $whatsAppCallingData['userName'] = __tr('Connected to __userName__', ['__userName__' => $userFullName]);
            $userAction = 'ACCEPTED';
        }

        // Check webhook already received or not
        if (__isEmpty($messageLogEntry)) {
            $this->whatsAppMessageLogRepository->storeIncomingMessage(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $whatsappWaId,
                $callId,
                $messageEntry,
                null,
                $timestamp
            );
        } elseif (!__isEmpty($messageLogEntry)) {
            $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
                $phoneNumberId,
                $contact->_id,
                $vendorId,
                $whatsappWaId,
                $callId,
                $callEvent,
                $messageEntry,
                null,
                $timestamp,
                null,
                true, // do not create new record if not found
                ['is_incoming_message' => ($callDirection == 'USER_INITIATED') ? true : false]
            );
        }

        $whatsAppCallingData['contact_phone_number'] = $contact->wa_id;

        $whatsJetCallingDbData = $this->whatsJetCallingAddonRepository->fetchIt([
            'wacid' => $callId,
            'contacts__id' =>  $contact->_id,
        ]);

        $whatsJetCallingData = [
            'status' => $callStatus,
            'contacts__id' =>  $contact->_id,
            'wacid' => $callId,
            'ended_at' => ($duration > 0 and $callStatus == 'TERMINATE') ? now() : null,
            'wa_call_duration' => $duration,
            'user_action' => $userAction,
            'wab_phone_number_id' => $phoneNumberId,
            'contact_wa_id' => $contact->wa_id,
        ];

        if ($callStatus == 'ACCEPTED') {
            $whatsJetCallingData['started_at'] = now();

            event(new VendorChannelBroadcast($vendorUid, [
                'is_webhook_data' => true,
                'is_outgoing_call_accepted' => true,
                'contact_phone_number' => $contact->wa_id,
                'by_user_id' => $whatsJetCallingDbData->by_users__id ?? null
            ]));
        }

        if (__isEmpty($whatsJetCallingDbData)) {
            // Store whatsapp calling(Incoming/Outgoing) data
            $whatsJetCallingData['__data'] = [
                'call_direction' => $callDirection,
                'webhook_responses' => [
                    'incoming' => $messageEntry,
                ]
            ];

            $whatsJetCallingData['call_direction'] = $callDirection;
            $this->whatsJetCallingAddonRepository->storeIt($whatsJetCallingData);
        } else {
            // Store whatsapp calling(Incoming/Outgoing) data
            $whatsJetCallingData['__data'] = [
                'webhook_responses' => [
                    $callEvent => $messageEntry
                ]
            ];
            $this->whatsJetCallingAddonRepository->updateIt($whatsJetCallingDbData, $whatsJetCallingData);
        }

        if ($callDirection == 'BUSINESS_INITIATED' and $callStatus == 'RINGING') {
            return true;
        }

        $callData = [
            'isCallRinging' => true,
            'contactName' => $whatsAppCallingData['userName'],
            'callId' => $callId,
            'uniqueId' => $contact->wa_id,
            'answer' => '',
            'callStatus' => ($whatsAppCallingData['is_incoming_call'])
                ? __tr('Ringing • Mobile')
                : __tr('Calling • Mobile'),
            'incomingCallTimer' => __tr('00:00'),
            'outgoingCallTimer' => __tr('00:00')
        ];

        $whatsAppCallingData['whatsAppCallData'] = $callData;

        $whatsAppCallingData['templateData'] = view('WhatsJetCallingAddon::whatsapp-calling-partials', [
            'contactPhoneNumber' => $contact->wa_id,
            'isIncomingCall' => $whatsAppCallingData['is_incoming_call'],
            'isOutgoingCall' => $whatsAppCallingData['is_outgoing_call'],
            'initials' => $initials
        ])->render();

        $whatsAppCallingData['contactWaId'] = $contact->wa_id;
        $whatsAppCallingData['assignedUserId'] = $contact->assigned_users__id;

        event(new VendorChannelBroadcast($vendorUid, $whatsAppCallingData));

        event(new VendorChannelBroadcast($vendorUid, [
            'message_status' => $callEvent ?? null,
            'contactUid' => $contact->_uid,
            'contactWaId' => $contact->wa_id,
            'isNewIncomingMessage' => true,
            'lastMessageUid' => $contact->lastMessage?->_uid,
            'assignedUserId' => $contact->assigned_users__id,
            'formatted_last_message_time' => $contact->lastMessage?->formatted_message_time,
            'contactDescription' => ($contact->full_name ?: $contact->wa_id)
        ]));
    }

    /**
     * Process Store Vendor Settings
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function processStoreVendorSettings(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');

        $inputValue = $request->get('lw_addon_enable_whatsapp_calling');
        $phoneNumberId = $request->get('phone_number_id');

        $whatsappCallingSettingData = $this->whatsAppCallConnectApiService->getPhoneNumberCallingSetting(
            $phoneNumberId,
            getVendorSettings('whatsapp_access_token'),
        );

        // Check whatsapp calling status
        if ($inputValue) {
            // Enable calls webhook on whatsapp dashboard
            $this->whatsAppCallConnectApiService->setWhatsappCallWebhook(getVendorSettings('facebook_app_id'), getVendorSettings('facebook_app_secret'), getVendorUid());

            sleep(1); // wait for a second to reflect the changes

            $this->whatsAppCallConnectApiService->storePhoneNumberCallingSetting(
                $phoneNumberId,
                getVendorSettings('whatsapp_access_token'),
            );
        } elseif (($whatsappCallingSettingData['calling']['status'] == 'ENABLED') and !$inputValue) {
            $this->whatsAppCallConnectApiService->storePhoneNumberCallingSetting(
                $phoneNumberId,
                getVendorSettings('whatsapp_access_token'),
                'DISABLED'
            );
        }

        $whatsappCallingConfig = getVendorSettings('lw_addon_enable_whatsapp_calling');

        if (!__isEmpty($whatsappCallingConfig)) {
            $whatsappCallingConfig = is_int($whatsappCallingConfig) ? [] : $whatsappCallingConfig;
            $whatsappCallingConfig = array_map(fn() => false, $whatsappCallingConfig);
        }

        $phoneNumberIds[$phoneNumberId] = $request->get('lw_addon_enable_whatsapp_calling');
        $callingSettingData['lw_addon_enable_whatsapp_calling'] = $phoneNumberIds + $whatsappCallingConfig;

        $processReaction = $this->vendorSettingsEngine->updateProcess($request->pageType, $callingSettingData);

        return $this->responseAction($this->processResponse($processReaction, [], [], true));
    }

    /**
     * Message get update data
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function answerUserInitiatedCall(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');

        $userId = getUserID();

        $contact = $this->contactRepository->fetchIt([
            'wa_id' => $request->get('uniqueId'),
            'vendors__id' => getVendorId()
        ]);

        $isRestrictedVendorUser = (!hasVendorAccess() ? hasVendorAccess('assigned_chats_only') : false);

        if (($isRestrictedVendorUser && ($userId != (int) $contact->assigned_users__id))) {
            return $this->processResponse(2, [], [
                'message' => __tr('You does not have permission to accept/reject this call.')
            ]);
        }

        $callData = $this->whatsJetCallingAddonRepository->fetchIt([
            'wacid' => $request->get('callId')
        ]);

        // Check if call already received by another user
        if (
            !__isEmpty($callData)
            and !__isEmpty($callData->by_users__id)
            and $callData->by_users__id != $userId
        ) {
            return $this->processResponse(2, [], [
                'message' => __tr('Another user already received a call.')
            ]);
        }
        
        $isCallInAcceptedState = ($callData == 'ACCEPTED') ? true : false;
        $type = $request->get('type');
        $status = ($type == 'accept') ? 'ACCEPTED' : 'REJECTED';

        // Get current logged in user in progress call details
        $fetchCurrentUserAcceptedCall = $this->whatsJetCallingAddonRepository->fetchIt([
            'by_users__id' => $userId,
            'status' => 'ACCEPTED'
        ]);

        // Check if logged in user try to accept another call
        if (!__isEmpty($fetchCurrentUserAcceptedCall) and $status == 'ACCEPTED') {
            return $this->processResponse(2, [], [
                'message' => __tr('Another call already in progress.')
            ]);
        }

        // Check if any other outgoing call is ringing
        $ringingOutgoingCall = $this->whatsJetCallingAddonRepository->fetchRingingOutgoingCall();

        if ($status == 'ACCEPTED') {
            if (!__isEmpty($ringingOutgoingCall) and $ringingOutgoingCall->by_users__id == $userId) {
                $this->whatsAppCallConnectApiService->answerUserInitiateCall(getVendorSettings('current_phone_number_id'), [
                    'type' => 'terminate',
                    'callId' => $ringingOutgoingCall->wacid
                ], getVendorSettings('whatsapp_access_token'));
            }
        }

        // ask engine to process the request
        $processReaction = $this->whatsAppCallConnectApiService->answerUserInitiateCall(getVendorSettings('current_phone_number_id'), $request->all(), getVendorSettings('whatsapp_access_token'));

        if ($processReaction->success()) {
            if (!__isEmpty($callData)) {
                $this->whatsJetCallingAddonRepository->updateIt($callData, [
                    'by_users__id' => $userId,
                    'user_session_id' => $request->session()->getId(),
                    'status' => $status
                ]);

                if ($status == 'ACCEPTED') {
                    $incomingCallData = $this->whatsJetCallingAddonRepository->fetchAllIncomingCall($request->get('uniqueId'));

                    event(new VendorChannelBroadcast(getVendorUid(), [
                        'is_webhook_data' => true,
                        'remove_call_widget_when_accept_call' => true,
                        'contact_phone_number' => $request->get('uniqueId'),
                        'user_id' => $userId,
                        'other_incoming_calls' => $incomingCallData->where('call_direction', 'USER_INITIATED')->pluck('contact_wa_id'),
                        'other_outgoing_calls' => $incomingCallData->where('call_direction', 'BUSINESS_INITIATED')->pluck('contact_wa_id'),
                        'contactWaId' => $request->get('uniqueId')
                    ]));
                }

                $webhookData = $callData->__data;
                $whatsappWaId = data_get($webhookData, 'webhook_responses.connect.0.changes.0.value.calls.0.from');

                $this->whatsAppMessageLogRepository->updateOrCreateWhatsAppMessageFromWebhook(
                    $callData->wab_phone_number_id,
                    $contact->_id,
                    getVendorId(),
                    $whatsappWaId,
                    $callData->wacid,
                    strtolower($status),
                    $processReaction->data(),
                    null,
                    null,
                    null,
                    true, // do not create new record if not found
                    ['is_incoming_message' => true]
                );

                event(new VendorChannelBroadcast(getVendorUid(), [
                    'message_status' => strtolower($status) ?? null,
                    'contactUid' => $contact->_uid,
                    'contactWaId' => $contact->wa_id,
                    'isNewIncomingMessage' => true,
                    'lastMessageUid' => $contact->lastMessage?->_uid,
                    'assignedUserId' => $contact->assigned_users__id,
                    'formatted_last_message_time' => $contact->lastMessage?->formatted_message_time,
                    'contactDescription' => ($contact->full_name ?: $contact->wa_id),
                    'campaignUid' => (!$isCallInAcceptedState and $status == 'REJECTED') ? $contact->_uid : null,
                ]));
            }
        }

        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
     * Get Current User Call Permission Details
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function getCurrentUserCallPermission($contactUid)
    {
        validateVendorAccess('messaging');

        $contact = $this->contactRepository->fetchIt([
            '_uid' => $contactUid,
            'vendors__id' => getVendorId()
        ]);

        // Check if contact exist
        if (__isEmpty($contact)) {
            return $this->processResponse(2, [], [
                'message' => __tr('Contact does not exist.')
            ]);
        }

        $userWaId = $contact->wa_id;

        $isAnyCallAlreadyInProgress = false;
        // Get is any In-Progress call exists
        $whatsJetCallingDbData = $this->whatsJetCallingAddonRepository->fetchBusinessInitOngoingCall();

        // Check if calling data exists
        if (!__isEmpty($whatsJetCallingDbData)) {
            $isAnyCallAlreadyInProgress = true;
        }

        // ask engine to process the request
        $processReaction = $this->whatsAppCallConnectApiService->getCurrentCallPermissionState(getVendorSettings('current_phone_number_id'), $userWaId, getVendorSettings('whatsapp_access_token'));
        $processReaction['data']['isAnyCallAlreadyInProgress'] = $isAnyCallAlreadyInProgress;
        $processReaction['data']['inProgressCallId'] = $whatsJetCallingDbData ? $whatsJetCallingDbData->_uid : null;
        $vendorPlanDetails = vendorPlanDetails('WhatsJetCallingAddon', 1, getVendorId());
        $processReaction['data']['isLimitAvailable'] = $vendorPlanDetails['is_limit_available'];
        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
     * Send Free Form Call Permission Request
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function sendFreeFormCallPermissionRequest(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');
        $inputData = $request->all();
        $currentPhoneNumberId = getVendorSettings('current_phone_number_id');
        // ask engine to process the request
        $processReaction = $this->whatsAppCallConnectApiService->sendFreeFormCallPermissionRequestMessage($currentPhoneNumberId, $inputData, getVendorSettings('whatsapp_access_token'));

        if ($processReaction->success()) {

            $vendorId = getVendorId();
            $contact = $this->contactRepository->fetchIt([
                'vendors__id' => $vendorId,
                'wa_id' => $inputData['user_wa_id'],
            ]);
            $processResponseData = $processReaction->data();

            $initialData = [
                "accepted" => [
                    "contacts" => [
                        [
                            "input" => $currentPhoneNumberId,
                            "wa_id" => $currentPhoneNumberId
                        ]
                    ],
                    "messages" => [
                        [
                            "id" => $processResponseData['messages'][0]['id']
                        ]
                    ],
                    "messaging_product" => "whatsapp"
                ]
            ];

            $verifiedName = '';
            $whatsAppPhoneNumberData = getVendorSettings('whatsapp_phone_numbers');
            // Check if whatsapp phone number data exists
            if (!__isEmpty($whatsAppPhoneNumberData)) {
                $phoneNumberId = getVendorSettings('current_phone_number_id', null, null, getVendorUid());
                foreach ($whatsAppPhoneNumberData as $phoneNumber) {
                    if ($phoneNumber['id'] == $phoneNumberId) {
                        $verifiedName = $phoneNumber['verified_name'];
                    }
                }
            }

            $bodyText = __tr('Can __verifiedName__ call you?', ['__verifiedName__' => $verifiedName]);

            $jsonData = [
                'options' => [
                    "is_free_form_call_permission_request" => true,
                    "bot_reply" =>  false,
                    "ai_bot_reply" =>  false,
                    "interaction_message_data" =>  [
                        "cta_url" =>  [
                            "url" =>  "",
                            "display_text" =>  "Choose preference"
                        ],
                        "body_text" =>  $bodyText,
                        "interactive_type" =>  "cta_url"
                    ]
                ],
                'initial_response' => $initialData,
                'webhook_responses' => $initialData,
                'interaction_message_data' => [
                    'body_text' => $bodyText,
                    'header_text' => '',
                    'footer_text' => '',
                    'interactive_type' => 'button',
                    'buttons' => [
                        'Choose preference'
                    ]
                ]
            ];

            $this->whatsAppMessageLogRepository->storeIt([
                'wab_phone_number_id' => $currentPhoneNumberId,
                'contact_wa_id' => $inputData['user_wa_id'],
                'wamid' => $processResponseData['messages'][0]['id'],
                'status' => 'initiate',
                'message' => '',
                'is_incoming_message' => 0,
                'vendors__id' => $vendorId,
                'contacts__id' => $contact->_id,
                '__data' => $jsonData,
                'messaged_at' => now(),
                'replied_to_whatsapp_message_logs__uid' => null,
                'is_forwarded' => null,
            ]);
        }

        // get back to controller with engine response
        return $this->processResponse($processReaction, [], [], true);
    }

    /**
     * Business Initiate a new audio call
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function businessInitiatedCall(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');
        // ask engine to process the request
        $processReaction = $this->whatsAppCallConnectApiService->businessInitiateCall(getVendorSettings('current_phone_number_id'), $request->all(), getVendorSettings('whatsapp_access_token'));

        if ($processReaction->success()) {

            $vendorId = getVendorId();
            $contact = $this->contactRepository->fetchIt([
                'vendors__id' => $vendorId,
                'wa_id' => $request->get('phone_number'),
            ]);
            $whatsappCallData = $processReaction->data();

            $userFullName = $contact->first_name . ' ' . $contact->last_name;

            $this->whatsJetCallingAddonRepository->storeIt([
                'status' => 'RINGING',
                'contacts__id' =>  $contact->_id,
                'wacid' => data_get($whatsappCallData, 'calls.0.id'),
                'call_direction' => 'BUSINESS_INITIATED',
                'by_users__id' => getUserID(),
                'wab_phone_number_id' => getVendorSettings('current_phone_number_id'),
                'contact_wa_id' => $request->get('phone_number'),
                'user_session_id' => $request->session()->getId(),
                '__data' => [
                    'calling_data' => [
                        'contactName' => $userFullName,
                        'callId' => data_get($whatsappCallData, 'calls.0.id'),
                        'uniqueId' => $contact->wa_id,
                    ]
                ]
            ]);

            $initials = strtoupper(substr($contact->first_name, 0, 1) . substr($contact->last_name, 0, 1));

            $callData = [
                'isCallRinging' => true,
                'contactName' => __tr('Connecting to __userName__', ['__userName__' => $userFullName]),
                'callId' => data_get($whatsappCallData, 'calls.0.id'),
                'uniqueId' => $contact->wa_id,
                'answer' => '',
                'callStatus' => __tr('Calling • Mobile'),
                'incomingCallTimer' => __tr('00:00'),
                'outgoingCallTimer' => __tr('00:00')
            ];

            $whatsAppCallingData = [
                'whatsAppCallData' => $callData,
                'status' => '',
                'is_outgoing_call' => true,
                'initials' => $initials
            ];

            $whatsAppCallingData['templateData'] = view('WhatsJetCallingAddon::whatsapp-calling-partials', [
                'contactPhoneNumber' => $contact->wa_id,
                'isIncomingCall' => false,
                'isOutgoingCall' => true,
                'initials' => $initials
            ])->render();

            $processReaction['data']['outgoingCallData'] = $whatsAppCallingData;

            updateClientModels([
                'allCallsData' => [
                    $contact->wa_id => $callData
                ]
            ]);
            

            // get back to controller with engine response
            return $this->processResponse($processReaction, [], [], true);
        }
    }

    /**
     * Process Update Call Details
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function processUpdateCallDetails(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');

        $callData = $this->whatsJetCallingAddonRepository->fetchIt([
            'wacid' => $request->get('callId')
        ]);

        // Check if call data exists
        if (__isEmpty($callData)) {
            return $this->processResponse(2, [], [], true);
        }

        $this->whatsJetCallingAddonRepository->updateIt($callData, [
            '__data' => [
                'calling_data' => $request->only('contactName', 'callId', 'uniqueId')
            ]
        ]);

        return $this->processResponse(1, [], [], true);
    }

    /**
     * Process Update Call Details
     *
     * @param  mix  $messageIdOrUid
     * @return json object
     *---------------------------------------------------------------- */
    public function stopInProgressCall(BaseRequestTwo $request)
    {
        validateVendorAccess('messaging');

        $callData = $this->whatsJetCallingAddonRepository->fetchIt([
            '_uid' => $request->in_progress_call_id
        ]);

        // Check if call data exists
        if (__isEmpty($callData)) {
            return $this->processResponse(2, [], [], true);
        }
        // Update status to terminate
        $this->whatsJetCallingAddonRepository->updateIt($callData, [
            'status' => 'TERMINATE'
        ]);

        // End call on meta if it is in progress
        $this->whatsAppCallConnectApiService->answerUserInitiateCall(getVendorSettings('current_phone_number_id'), [
            'callId' => $callData->wacid,
            'type' => 'terminate'
        ], getVendorSettings('whatsapp_access_token'));

        return $this->processResponse(1, [], [], true);
    }
}
