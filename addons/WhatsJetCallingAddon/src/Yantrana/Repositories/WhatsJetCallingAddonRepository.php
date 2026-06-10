<?php

/**
 * WhatsJet Addon
 *
 * This file is part of the WhatsJet software Addon package developed and licensed by livelyworks.
 *
 * You must have a valid license to use this software.
 *
 * © 2024 - 2026 livelyworks. All rights reserved.
 * Redistribution or resale of this file, in whole or in part, is prohibited without prior written permission from the author.
 *
 * For support or inquiries, contact: contact@livelyworks.net
 *
 * @package     WhatsJet Addon
 * @author      livelyworks <contact@livelyworks.net>
 * @copyright   Copyright (c) 2024 - 2026 livelyworks
 * @website     https://livelyworks.net
 */


/**
 * ContactRepository.php - Repository file
 *
 * This file is part of the Contact component.
 *-----------------------------------------------------------------------------*/

namespace Addons\WhatsJetCallingAddon\Yantrana\Repositories;

use App\Yantrana\Base\BaseRepository;
use Addons\WhatsJetCallingAddon\Yantrana\Models\WhatsappCallModel;

class WhatsJetCallingAddonRepository extends BaseRepository
{
    /**
     * primary model instance
     *
     * @var object
     */
    protected $primaryModel = WhatsappCallModel::class;

    public function fetchAllIncomingCall($ignorePhoneNumber)
    {
        return $this->primaryModel::join('contacts', 'whatsapp_calls.contacts__id', '=', 'contacts._id')
            ->where('vendors__id', getVendorId())
            ->where('whatsapp_calls.status', 'RINGING')
            ->whereNotIn('contact_wa_id', [$ignorePhoneNumber])
            ->get();
    }

    public function fetchBusinessInitOngoingCall()
    {
        return $this->primaryModel::join('contacts', 'whatsapp_calls.contacts__id', '=', 'contacts._id')
            ->where('contacts.vendors__id', getVendorId())
            ->where('whatsapp_calls.by_users__id', getUserID())
            ->where(function ($query) {
                $query->where(function ($q) {
                        $q->where('whatsapp_calls.call_direction', 'BUSINESS_INITIATED')
                        ->whereIn('whatsapp_calls.status', ['RINGING', 'ACCEPTED']);
                    })
                    ->orWhere(function ($q) {
                        $q->where('whatsapp_calls.call_direction', 'USER_INITIATED')
                        ->where('whatsapp_calls.status', 'ACCEPTED');
                    });
            })
            ->select(
                'whatsapp_calls.*',
                'contacts._id as contact_id',
                'contacts.vendors__id'
            )
            ->first();
    }

    public function fetchRingingOutgoingCall()
    {
        return $this->primaryModel::join('contacts', 'whatsapp_calls.contacts__id', '=', 'contacts._id')
            ->where('contacts.vendors__id', getVendorId())
            ->where('whatsapp_calls.call_direction', 'BUSINESS_INITIATED')
            ->where('whatsapp_calls.status', 'RINGING')
            ->first();
    }
}
