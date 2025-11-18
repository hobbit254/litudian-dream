<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Settings extends Model
{
    use HasFactory;
    protected $table = 'settings';
    protected $primaryKey = 'id';
    protected $fillable = [
        'store_name',
        'contact_email',
        'contact_phone',
        'receive_email_alerts',
        'receive_sms_alerts',
        'deposit_fee_percent',
        'deposit_days_due',
        'balance_days_due',
        'shipping_days_due',
        'anonymous_buying',
        'service_fee_percent',
        'service_fee_cap',
    ];
}
