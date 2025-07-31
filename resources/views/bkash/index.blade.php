@extends('bdpayment::layout.app')

@section('title', 'bKash Payment Gateway')

@section('content')
    <!-- Flash Message Container -->
    <div id="message-container"
        class="hidden max-w-lg w-full mb-6 px-4 py-3 rounded-md shadow-lg text-white text-base font-medium flex items-center justify-between transition duration-300">
        <span id="message-text"></span>
        <button type="button" id="close-message"
            class="ml-4 text-xl font-bold leading-none focus:outline-none hover:text-gray-300">
            &times;
        </button>
    </div>


    <!-- Hidden Trigger Button -->
    <button id="bKash_button" class="hidden"></button>
@endsection

@push('bkash')
    @include('bdpayment::components.bkash-script')
@endpush

@push('scripts')
    <script>
        $(document).ready(function() {
            const bkashMode = @json(config('bdpayment.drivers.bkash.mode', 'sandbox'));
            const csrfToken = $('meta[name="csrf-token"]').attr('content');
            const payAmount = @json($amount);
            let paymentID = null;
            let tranId = "";

            const messageContainer = $('#message-container');
            const messageText = $('#message-text');

            let messageTimeout = null;

            function showMessage(message, type = 'error') {
                const typeClasses = {
                    error: 'bg-red-500',
                    success: 'bg-green-500',
                    info: 'bg-blue-500',
                };

                const bgClass = typeClasses[type] || 'bg-gray-600';

                if (messageTimeout) {
                    clearTimeout(messageTimeout);
                }

                messageContainer
                    .removeClass('hidden bg-red-500 bg-green-500 bg-blue-500 bg-gray-600')
                    .addClass(bgClass)
                    .fadeIn();

                messageText.html(message);

                // Auto-dismiss and redirect after 5 seconds
                messageTimeout = setTimeout(() => {
                    redirectToHome();
                }, 5000);
            }

            function clearMessage() {
                if (messageTimeout) {
                    clearTimeout(messageTimeout);
                    messageTimeout = null;
                }

                messageContainer.fadeOut(200, () => {
                    messageText.text('');
                    messageContainer
                        .removeClass('bg-red-500 bg-green-500 bg-blue-500 bg-gray-600')
                        .addClass('hidden');
                });
            }

            function redirectToHome() {
                window.location.href = "/";
            }

            // Close button click event
            $('#close-message').on('click', function() {
                clearMessage();
                redirectToHome();
            });



            function handleAjaxError(jqXHR, context = "Request") {
                const status = jqXHR.status;
                const response = jqXHR.responseJSON;
                let msg = "";

                if (status === 403) {
                    msg =
                        `${context} failed: Unauthorized. <a href="/" class="underline text-white font-bold ml-2">Home</a>`;
                } else if (status === 422) {
                    if (response?.errors) {
                        const firstKey = Object.keys(response.errors)[0];
                        msg = response.errors[firstKey][0];
                    } else {
                        msg = response?.message || "Unprocessable request.";
                    }
                } else if (status === 500) {
                    msg = `${context} failed: Internal Server Error.`;
                } else {
                    msg = `${context} failed: Unexpected error (status ${status}).`;
                }

                showMessage(msg, 'error');
                console.error(`${context} error [${status}]:`, jqXHR);
            }

            bKash.init({
                paymentMode: 'checkout',
                paymentRequest: {
                    amount: payAmount,
                    intent: 'sale',
                    currency: 'BDT'
                },
                createRequest: function(request) {
                    clearMessage();
                    $.ajax({
                        url: "{{ route('gateway.bkash.create') }}",
                        type: 'POST',
                        contentType: 'application/json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        data: JSON.stringify({
                            amount: payAmount
                        }),
                        success: function(data) {
                            if (data.paymentID) {
                                paymentID = data.paymentID;
                                tranId = data.tran_id;
                                let bkashURL = data.bkashURL || data.paymentURL;
                                if (bkashMode === 'sandbox') {
                                    // Redirect in sandbox
                                    window.location.href = bkashURL;
                                } else {
                                    // Use SDK in production
                                    bKash.create().onSuccess(data);
                                }
                            } else {
                                showMessage('Failed to initiate payment.', 'error');
                                bKash.create().onError();
                            }
                        },
                        error: function(jqXHR) {
                            handleAjaxError(jqXHR, "Create Payment");
                            bKash.create().onError();
                        }
                    });
                },
                executeRequestOnAuthorization: function() {
                    clearMessage();
                    $.ajax({
                        url: "{{ route('gateway.bkash.execute') }}",
                        type: 'POST',
                        contentType: 'application/json',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken
                        },
                        data: JSON.stringify({
                            transaction_id: tranId
                        }),
                        success: function(data) {
                            if (data.paymentID && data.transactionStatus === 'Completed') {
                                showMessage("✅ Payment successful!", "success");
                            } else {
                                showMessage("Payment failed or not completed.", "error");
                            }
                        },
                        error: function(jqXHR) {
                            handleAjaxError(jqXHR, "Execute Payment");
                        }
                    });
                },
                onClose: function() {
                    window.location.href = "/";
                }
            });

            $('#bKash_button').trigger('click');
        });
    </script>
@endpush
