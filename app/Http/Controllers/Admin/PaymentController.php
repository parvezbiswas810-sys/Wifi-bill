<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString() ?: PaymentSubmission::STATUS_PENDING;

        $query = PaymentSubmission::with(['customer:id,name,customer_code,phone', 'invoice:id,invoice_number,balance,amount', 'method:id,name']);

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($search = $request->string('q')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                    ->orWhere('sender_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%"));
            });
        }

        $submissions = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $counts = [
            'pending' => PaymentSubmission::where('status', PaymentSubmission::STATUS_PENDING)->count(),
            'approved' => PaymentSubmission::where('status', PaymentSubmission::STATUS_APPROVED)->count(),
            'rejected' => PaymentSubmission::where('status', PaymentSubmission::STATUS_REJECTED)->count(),
            'info_requested' => PaymentSubmission::where('status', PaymentSubmission::STATUS_INFO_REQUESTED)->count(),
            'pending_amount' => (float) PaymentSubmission::where('status', PaymentSubmission::STATUS_PENDING)->sum('amount'),
        ];

        return view('admin.payments.index', compact('submissions', 'status', 'counts'));
    }

    public function approve(Request $request, PaymentSubmission $submission, PaymentService $service)
    {
        $data = $request->validate([
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->approve(
            $submission,
            $request->user('isp'),
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['note'] ?? null
        );

        if (! $result['ok']) {
            return back()->with('error', $result['message']);
        }

        $message = $result['message'];
        if ($result['restored'] ?? false) {
            $message .= ' Internet has been restored and confirmed by the router.';
        }

        return back()->with('success', $message);
    }

    public function reject(Request $request, PaymentSubmission $submission, PaymentService $service)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $service->reject($submission, $request->user('isp'), $data['rejection_reason']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function requestInfo(Request $request, PaymentSubmission $submission, PaymentService $service)
    {
        $data = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $result = $service->requestInfo($submission, $request->user('isp'), $data['admin_note']);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /** Serves the uploaded payment proof to an authorised administrator only. */
    public function screenshot(PaymentSubmission $submission)
    {
        if (! $submission->screenshot_path) {
            abort(404);
        }

        $path = storage_path('app/private/'.$submission->screenshot_path);
        if (! is_file($path)) {
            $path = storage_path('app/'.$submission->screenshot_path);
        }

        abort_unless(is_file($path), 404);

        return response()->file($path);
    }
}
