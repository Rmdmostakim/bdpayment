@props(['status' => 'failed', 'message' => null])

@php
    $statusConfig = [
        'success' => [
            'title' => 'Transaction Successful',
            'icon' => 'check-circle',
            'color' => 'green',
            'defaultMessage' => 'Your payment was completed successfully.',
        ],
        'cancelled' => [
            'title' => 'Transaction Cancelled',
            'icon' => 'x-circle',
            'color' => 'yellow',
            'defaultMessage' => 'You cancelled the payment. No money was charged.',
        ],
        'failed' => [
            'title' => 'Transaction Failed',
            'icon' => 'alert-triangle',
            'color' => 'red',
            'defaultMessage' => 'Something went wrong. Please try again later.',
        ],
    ];

    $config = $statusConfig[$status] ?? $statusConfig['failed'];
@endphp

<div class="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-10">
    <div class="max-w-md w-full bg-white shadow-xl rounded-lg overflow-hidden">
        <div class="p-8 text-center">
            {{-- Icon --}}
            <div class="flex justify-center mb-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-16 w-16 text-{{ $config['color'] }}-500" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    @switch($config['icon'])
                        @case('check-circle')
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 12l2 2l4 -4m5 2a9 9 0 1 1 -18 0a9 9 0 0 1 18 0z" />
                        @break

                        @case('x-circle')
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 9l-6 6m0 -6l6 6M12 21a9 9 0 1 1 0 -18a9 9 0 0 1 0 18z" />
                        @break

                        @case('alert-triangle')
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a1.5 1.5 0 0 0 1.29 2.25h17.78a1.5 1.5 0 0 0 1.29-2.25L13.71 3.86a1.5 1.5 0 0 0-2.42 0z" />
                        @break
                    @endswitch
                </svg>
            </div>

            {{-- Title --}}
            <h2 class="text-2xl font-bold text-gray-800 mb-2">{{ $config['title'] }}</h2>

            {{-- Message --}}
            <p class="text-gray-600">{{ $message ?? $config['defaultMessage'] }}</p>

            {{-- Button --}}
            <div class="mt-6">
                <a href="{{ url('/') }}"
                    class="inline-block bg-{{ $config['color'] }}-500 hover:bg-{{ $config['color'] }}-600 text-white font-semibold px-5 py-2 rounded shadow transition duration-150 ease-in-out">
                    Go to Home
                </a>
            </div>
        </div>
    </div>
</div>
