<?php

namespace RmdMostakim\BdPayment\Facades;

use Illuminate\Support\Facades\Facade;

class BdPayment extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'bdpayment';
    }
}