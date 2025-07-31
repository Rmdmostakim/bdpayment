<?php

namespace RmdMostakim\BdPayment;

use RmdMostakim\BdPayment\Drivers\BkashDriver;

class PaymentManager
{
    public function bkash(): BkashDriver
    {
        return new BkashDriver();
    }

    // Add: public function nagad() {...}, public function sslcommerz() {...}
}
