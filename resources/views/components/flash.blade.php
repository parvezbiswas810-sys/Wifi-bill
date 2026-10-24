@if (session('success'))
    <div class="alert alert-success mb-5" role="status">{{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="alert alert-danger mb-5" role="alert">{{ session('error') }}</div>
@endif
@if (session('warning'))
    <div class="alert alert-warning mb-5" role="status">{{ session('warning') }}</div>
@endif
@if (session('info'))
    <div class="alert alert-info mb-5" role="status">{{ session('info') }}</div>
@endif
@if ($errors->any() && ! isset($suppressErrorSummary))
    <div class="alert alert-danger mb-5" role="alert">
        <div>
            <p class="font-semibold">Please correct the following:</p>
            <ul class="mt-1 list-inside list-disc">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif
