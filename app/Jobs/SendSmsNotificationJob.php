<?php

namespace App\Jobs;

use App\Http\helpers\TinggSmsHelper;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $customer_phone;
    protected $message;

    /**
     * Create a new job instance.
     */
    public function __construct($customer_phone, $message)
    {
        $this->customer_phone = $customer_phone;
        $this->message = $message;
    }

    /**
     * Execute the job.
     * @throws Exception
     */
    public function handle(TinggSmsHelper $tinggSmsHelper): void
    {
        try {
            $tinggSmsHelper->sendSmsNotification($this->customer_phone, $this->message);
        } catch (Exception $exception) {
            logger($exception->getMessage());
            throw $exception;
        }
    }
}
