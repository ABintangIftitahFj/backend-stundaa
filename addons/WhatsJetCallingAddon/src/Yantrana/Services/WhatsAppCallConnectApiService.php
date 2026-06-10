<?php

namespace Addons\WhatsJetCallingAddon\Yantrana\Services;

use App\Yantrana\Components\WhatsAppService\Services\WhatsAppConnectApiService;
use Illuminate\Support\Facades\Cache;

class WhatsAppCallConnectApiService extends WhatsAppConnectApiService
{
    /**
     * Constructor
     *
     * @return void
     *-----------------------------------------------------------------------*/
    public function __construct() {}

    /**
     * Setup WhatsApp Calling Webhook
     *
     * @param int $appId
     * @param string $appSecret
     * @return array
     *
     * @link https://developers.facebook.com/docs/graph-api/reference/v2.5/app/subscriptions/#--app-id--subscriptions
     */
    public function setWhatsappCallWebhook($appId, $appSecret, $vendorUid)
    {
        $webhookUrl = getViaSharedUrl(route('vendor.whatsapp_webhook', [
            'vendorUid' => $vendorUid,
        ]));
        $subscriptions = $this->apiPostRequest("{$this->baseApiRequestEndpoint}" . $appId . "/subscriptions?access_token=" . $appId . "|" . $appSecret, [
            'object' => 'whatsapp_business_account',
            'fields' => 'messages,message_template_quality_update,message_template_status_update,account_update,history,smb_app_state_sync,smb_message_echoes,calls',
            'callback_url' => $webhookUrl,
            "verify_token" => sha1($vendorUid)
        ]);
        return $subscriptions;
    }

    /**
     * Get the Phone Number Calling Settings
     *
     * @param int $phoneNumberId
     * @return void
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/call-settings
     */
    public function getPhoneNumberCallingSetting($phoneNumberId, $accessToken)
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        return $this->apiGetRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/settings") ?? null;
    }

    /**
     * Store the Phone Number Calling Settings
     *
     * @param int $phoneNumberId
     * @return void
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/call-settings
     */
    public function storePhoneNumberCallingSetting($phoneNumberId, $accessToken, $status = "ENABLED")
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        return $this->apiPostRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/settings", [
            "calling" => [
                "status" => $status,
                "callback_permission_status" => "ENABLED"
            ]
        ]);
    }

    /**
     * Answer User Initiated Call
     *
     * @param int $phoneNumberId
     * @return Object
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/call-settings
     */
    public function answerUserInitiateCall($phoneNumberId, $inputData, $accessToken)
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        $answerData = [];
        if (!__isEmpty(data_get($inputData, 'answerData'))) {
            $answerData = json_decode($inputData['answerData'], true);
        }

        $answerType = data_get($inputData, 'type');

        $callPayload = [
            'messaging_product' => "whatsapp",
            'call_id' => $inputData['callId'],
            'action' => $answerType
        ];

        if (in_array($answerType, ['accept', 'pre_accept']) and !__isEmpty($answerData)) {
            $callPayload['session'] = [
                'sdp_type' => "answer",
                'sdp' => $answerData['sdp']
            ];
        }

        $engineReaction = $this->apiPostRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/calls", $callPayload);

        return $this->engineSuccessResponse($engineReaction);
    }

    /**
     * Business Initiate a New Audion Call
     *
     * @param int $phoneNumberId
     * @return array|Object
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/business-initiated-calls
     */
    public function businessInitiateCall($phoneNumberId, $inputData, $accessToken)
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        $engineReaction = $this->apiPostRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/calls", [
            'messaging_product' => "whatsapp",
            'to' => $inputData['phone_number'],
            'action' => 'connect',
            'session' => [
                'sdp_type' => "offer",
                'sdp' => $inputData['sdp']
            ]
        ]);

        return $this->engineSuccessResponse($engineReaction);
    }

    /**
     * Get Current Call Permission Details
     *
     * @param int $phoneNumberId
     * @return array
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/user-call-permissions#get-current-call-permission-state
     */
    public function getCurrentCallPermissionState($phoneNumberId, $userWaId, $accessToken)
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        $callPermissionDetails = $this->apiGetRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/call_permissions", [
            'user_wa_id' => $userWaId
        ]) ?? null;

        $userCallPermissionData = [];
        $userCallPermissionData['call_limit_allowed'] = 0;
        $userCallPermissionData['permission'] = 0;
        $expireAt = data_get($callPermissionDetails, 'permission.expiration_time');
        // Check if call permission exists
        if (!__isEmpty($callPermissionDetails)) {
            $userCallPermissionData['permission_status_key'] = $callPermissionDetails['permission']['status'] ?? null;
            if ($callPermissionDetails['permission']['status'] == 'no_permission') {
                $userCallPermissionData['permission_status'] = __tr('No Permission');
            } elseif ($callPermissionDetails['permission']['status'] == 'temporary') {
                $userCallPermissionData['permission_status'] = __tr('Temporary Allowed');
                $userCallPermissionData['expire_at'] = (!__isEmpty($expireAt)) ? formatDate($expireAt) : null;
            } elseif ($callPermissionDetails['permission']['status'] == 'permanent') {
                $userCallPermissionData['permission_status'] = __tr('Allowed');
            }
            if (!__isEmpty($callPermissionDetails['actions'])) {
                foreach ($callPermissionDetails['actions'] as $actions) {
                    if ($actions['action_name'] == 'send_call_permission_request') {
                        $userCallPermissionData['send_call_request_permission'] = $actions['can_perform_action'];
                    }

                    if ($actions['action_name'] == 'start_call') {
                        $userCallPermissionData['start_call'] = $actions['can_perform_action'];
                        $userCallPermissionData['call_limit_allowed'] = data_get($actions, 'limits.0.max_allowed', 0) - data_get($actions, 'limits.0.current_usage', 0);
                    }
                }
            }
        }
        $userCallPermissionData['user_wa_id'] = $userWaId;
        return $this->engineSuccessResponse($userCallPermissionData);
    }

    /**
     * Get Current Call Permission Details
     *
     * @param int $phoneNumberId
     * @return Object
     *
     * @link https://developers.facebook.com/docs/whatsapp/cloud-api/calling/user-call-permissions#send-free-form-call-permission-request-message
     */
    public function sendFreeFormCallPermissionRequestMessage($phoneNumberId, $inputData, $accessToken)
    {
        if ($accessToken) {
            $this->accessToken = $accessToken;
        }

        $engineReaction = $this->apiPostRequest("{$this->baseApiRequestEndpoint}{$phoneNumberId}/messages", [
            "messaging_product" => "whatsapp",
            "recipient_type" => "individual",
            "to" => $inputData['user_wa_id'],
            "type" => "interactive",
            "interactive" => [
                "type" => "call_permission_request",
                "action" => [
                    "name" => "call_permission_request"
                ]
            ]
        ]);

        return $this->engineSuccessResponse($engineReaction);
    }
}
