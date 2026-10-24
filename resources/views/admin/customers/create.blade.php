@extends('layouts.app')

@section('title', 'Add customer')
@section('subtitle', 'Create a billing account and link it to a PPP account')

@section('content')
    <form method="POST" action="{{ route('admin.customers.store') }}" class="space-y-4">
        @csrf
        @include('admin.customers._form')

        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('admin.customers.index') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>
                Create customer
            </button>
        </div>
    </form>
@endsection
