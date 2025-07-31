@php
    $mode = config('bdpayment.drivers.bkash.mode', 'sandbox');
    $script = $mode === 'production'
        ? 'https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js'
        : 'https://scripts.sandbox.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout-sandbox.js';
@endphp

<script src="{{ $script }}"></script>
