<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>bKash Payment</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />

    <!-- Include jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- bKash Live Checkout SDK -->
    <script src="https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js"></script>
    <script src="https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js"></script>
</head>

<body>
    <button id="bKash_button" style="display: none"></button>
    <script>
        $(document).ready(function() {
            const payAmount = @json($amount);
            const bookingId = @json($bookingId);
            const purpose = @json($purpose);
            const frontendUrl = @json($frontendUrl);

            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            let paymentID = null;
            let tranId = "";

            bKash.init({
                paymentMode: 'checkout',
                paymentRequest: {
                    amount: payAmount,
                    intent: 'sale',
                },
                createRequest: function(request) {
                    $.ajax({
                        url: '/api/bkash/create-payment',
                        type: 'POST',
                        contentType: 'application/json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        data: JSON.stringify({
                            booking_id: bookingId,
                            purpose: purpose
                        }),
                        success: function(data) {
                            if (data.paymentID) {
                                paymentID = data.paymentID;
                                tranId = data.tran_id;
                                bKash.create().onSuccess(data);
                            } else {
                                console.error('Payment creation failed:', data);
                                bKash.create().onError();
                            }
                        },
                        error: function(err) {
                            console.error('Error during payment creation:', err);
                            bKash.create().onError();
                        }
                    });
                },
                executeRequestOnAuthorization: function() {
                    $.ajax({
                        url: '/api/bkash/execute-payment',
                        type: 'POST',
                        contentType: 'application/json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        data: JSON.stringify({
                            paymentID: paymentID
                        }),
                        success: function(data) {
                            if (data.paymentID && data.transactionStatus === 'Completed') {
                                $.ajax({
                                    url: '/api/bkash/confirm',
                                    type: 'POST',
                                    contentType: 'application/json',
                                    headers: {
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    data: JSON.stringify({
                                        purpose: purpose,
                                        tran_id: tranId
                                    }),
                                    success: function(data) {
                                        // console.log(data);
                                    },
                                    error: function(err) {
                                        console.error(err);
                                    }
                                });
                                window.location.href = `${frontendUrl}/reservations`;
                            } else if (data.errorMessage) {
                                window.location.href =
                                    `${frontendUrl}/payment/${bookingId}`;
                            } else {
                                window.location.href =
                                    `${frontendUrl}/payment/${bookingId}`;
                            }
                        },
                        error: function(err) {
                            console.error(err);
                        }
                    });
                },
                onClose: function() {
                    window.location.href = `/`;
                }
            });

            // Automatically trigger the bKash payment flow on page load
            $('#bKash_button').trigger('click');
        });
    </script>

</body>

</html>
