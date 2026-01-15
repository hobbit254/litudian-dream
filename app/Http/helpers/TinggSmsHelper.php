<?php

namespace App\Http\helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TinggSmsHelper
{
    private function getToken(): string
    {
        if (Cache::has('tingg_jwt_token')) {
            return Cache::get('tingg_jwt_token');
        }
        return $this->fetchNewToken();
    }

    private function fetchNewToken()
    {
        try {
            logger()->critical(config('services.sms.tingg_auth_url'));
            $response = Http::post(config('services.sms.tingg_auth_url'), [
                'username' => config('services.sms.tingg_username'),
                'password' => config('services.sms.tingg_password'),
            ]);
            logger()->info($response);
            if ($response->failed()) {
                logger()->error($response->json());
            }
            $data = $response->json();

            $token = $data['access_token'] ?? null;
            $expiresIn = $data['expires_in'] ?? 3600;

            if (!$token) {
                logger()->error("Unable to retrieve token");
            }
            Cache::put('tingg_jwt_token', $token, Carbon::now()->addSeconds($expiresIn - 60));
            return $token;
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage());
            return null;
        }
    }

    public function sendSmsNotification($customer_phone, $message): void
    {
        try {
            $payload = $this->generatePayload($customer_phone, $message);
            $token = $this->getToken();
            $response = Http::withToken($token)->post(config('services.sms.tingg_sms_url'), $payload);
            if ($response->status() === 401) {
                $newToken = $this->fetchNewToken();
                $response = Http::withToken($newToken)->post(config('services.sms.tingg_sms_url'), $payload);
            }
            if ($response->failed()) {
                logger()->error("Unable to send out an sms with error::" . json_encode($response->json()));
            }
            logger()->info("SMS has been sent to customer phone: " . $customer_phone . " with response: " . json_encode($response->json()));
        } catch (\Exception $exception) {
            logger()->error($exception->getMessage());
        }
    }

    private function generatePayload($customer_phone, $message): array
    {
        return [
            "notificationType" => "TransactionalAlerts",
            "channels" => ["SMS"],
            "referenceID" => $this->generateUniqueReferenceId(),
            "smsDto" => [
                "smsType" => "TRX",
                "msisdn" => [$customer_phone],
                "message" => $message,
                "params" => ["placeholder" => "value"],
                "extraData" => ["clientinfo" => "additional client info"]
            ]
        ];
    }

    private function generateUniqueReferenceId(): string
    {
        $id = DB::table('reference_ids')->insertGetId([]);
        $formatted = str_pad($id, 12, '0', STR_PAD_LEFT);
        return "LITHUANIA-{$formatted}";
    }
}
