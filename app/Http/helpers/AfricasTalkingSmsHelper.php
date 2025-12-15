<?php

namespace App\Http\helpers;

use AfricasTalking\SDK\AfricasTalking;
use Exception;

class AfricasTalkingSmsHelper
{
    public static function sendSmsNotification($customer_phone, $message)
    {
        // Set your app credentials
        $username = config('services.sms.africas_talking_username');
        $apiKey = config('services.sms.africas_talking_password');

        // Initialize the SDK
        $AT = new AfricasTalking($username, $apiKey);

        // Get the SMS service
        $sms = $AT->sms();

        $from = config('services.sms.africas_talking_sender');

        try {

            $result = $sms->send([
                'to' => $customer_phone,
                'message' => $message,
                'from' => $from
            ]);

        } catch (Exception $e) {
            logger()->error($e);
        }
    }
}
