<?php

namespace App\Http\Controllers;

use App\Models\SubscriptionPayment;
use App\Services\SubscriptionService;

class SubscriptionController extends Controller
{
    /** Comprobante de un pago (disco privado; nunca en public/) */
    public function receipt(SubscriptionPayment $payment, SubscriptionService $subscriptions)
    {
        return $subscriptions->receiptDownload($payment);
    }
}
