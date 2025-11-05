<?php

namespace App\Http\Controllers;

use App\Http\helpers\ResponseHelper;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $setting = Settings::first();
        return ResponseHelper::success(['data' => $setting], 'Settings fetched successfully.', 200);
    }

    public function create(Request $request): JsonResponse
    {
        $request->validate([
            'store_name' => ['required', 'string'],
            'contact_phone' => ['required', 'string'],
            'contact_email' => ['required', 'string'],
            'receive_email_alerts' => ['required', 'boolean'],
            'receive_sms_alerts' => ['required', 'boolean'],
            'deposit_fee_percent' => ['required', 'numeric'],
            'deposit_days_due' => ['required', 'numeric'],
            'balance_days_due' => ['required', 'numeric'],
            'shipping_days_due' => ['required', 'numeric'],
            'anonymous_buying' => ['required', 'boolean'],
            'service_fee_percent' => ['required', 'numeric'],
            'service_fee_cap' => ['required', 'numeric'],
        ]);

        $setting = Settings::firstOrNew([]);
        $setting->fill($request->all());
        $setting->save();
        return ResponseHelper::success(['data' => $setting], 'Setting created successfully.', 200);


    }
}
