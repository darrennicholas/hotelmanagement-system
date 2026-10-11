<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\HotelSetting;
use Illuminate\Http\Request;

class HotelSettingController extends Controller
{
    public function show(): HotelSetting
    {
        return HotelSetting::current();
    }

    public function update(Request $request): HotelSetting
    {
        $data = $request->validate([
            'tax_rate' => 'required|numeric|min:0|max:100',
            'service_charge_rate' => 'required|numeric|min:0|max:100',
            'currency' => 'sometimes|string|size:3',
        ]);

        $settings = HotelSetting::current();
        $settings->update($data);

        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'hotel_settings.updated',
            'entity_type' => 'hotel_setting',
            'entity_id' => $settings->id,
            'description' => 'Tax '.$settings->tax_rate.'%, service charge '.$settings->service_charge_rate.'%',
        ]);

        return $settings->fresh();
    }
}
