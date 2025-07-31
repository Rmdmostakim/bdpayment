@extends('bdpayment::layout.app')
@section('title', 'bKash Payment')
@section('content')
    @include('bdpayment::components.transaction-status', ['status' => $status, 'message' => $message ?? null])
@endsection
