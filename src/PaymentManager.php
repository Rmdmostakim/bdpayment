<?php

/**
 * PaymentManager
 *
 * Provides access to payment gateway drivers (Bkash, Nagad, etc).
 * Use this manager to interact with supported gateways in a unified way.
 *
 * @package RmdMostakim\BdPayment
 */

namespace RmdMostakim\BdPayment;

use RmdMostakim\BdPayment\Drivers\BkashDriver;
use RmdMostakim\BdPayment\Drivers\NagadDriver;

/**
 * Class PaymentManager
 *
 * @method BkashDriver bkash()
 * @method NagadDriver nagad()
 */
class PaymentManager
{
    /**
     * Get the Bkash payment driver.
     * @return BkashDriver
     */
    public function bkash(): BkashDriver
    {
        return new BkashDriver();
    }

    /**
     * Get the Nagad payment driver.
     * @return NagadDriver
     */
    public function nagad(): NagadDriver
    {
        return new NagadDriver();
    }

    // Add: public function sslcommerz() {...} for future gateway support
}
