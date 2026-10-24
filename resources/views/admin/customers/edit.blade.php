@extends('layouts.app')

@section('title', 'Edit '.$customer->name)
@section('subtitle', $customer->customer_code.' · '.$customer->ppp_username)

@section('content')
    <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="space-y-4">
        @csrf
        @method('PUT')
        @include('admin.customers._form')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
                Save changes
            </button>
        </div>
    </form>
@endsection
