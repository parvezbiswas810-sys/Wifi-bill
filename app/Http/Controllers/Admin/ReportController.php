<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InternetSuspensionLog;
use App\Models\Invoice;
use App\Models\MikrotikSyncLog;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PppAccount;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /** Report catalogue with date filtering and CSV export. */
    public function index(Request $request)
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $type = $request->string('type')->toString() ?: 'daily_collection';

        [$title, $columns, $rows, $totals] = $this->build($type, $from, $to);

        return view('admin.reports.index', compact(
            'type', 'from', 'to', 'title', 'columns', 'rows', 'totals'
        ));
    }

    public function export(Request $request): StreamedResponse
    {
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now();
        $type = $request->string('type')->toString() ?: 'daily_collection';

        [$title, $columns, $rows] = $this->build($type, $from, $to);

        $filename = 'report-'.$type.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_values($columns));
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($c) => $row[$c] ?? '', array_keys($columns)));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Builds one report.
     *
     * @return array{0: string, 1: array<string,string>, 2: array<int,array<string,mixed>>, 3: array<string,mixed>}
     */
    protected function build(string $type, $from, $to): array
    {
        $ispId = current_isp_id();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        return match ($type) {
            'monthly_collection' => $this->monthlyCollection($ispId, $fromDate, $toDate),
            'outstanding' => $this->outstanding($ispId),
            'overdue_customers' => $this->overdueCustomers($ispId),
            'active_ppp' => $this->pppReport($ispId, true),
            'inactive_ppp' => $this->pppReport($ispId, false),
            'suspended_customers' => $this->suspended($ispId),
            'payment_approvals' => $this->approvals($ispId, $fromDate, $toDate),
            'invoice_failures' => $this->invoiceFailures($ispId),
            'mikrotik_failures' => $this->mikrotikFailures($ispId, $fromDate, $toDate),
            default => $this->dailyCollection($ispId, $fromDate, $toDate),
        };
    }

    protected function dailyCollection(int $ispId, string $from, string $to): array
    {
        $rows = Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw('DATE(paid_at) as date, COUNT(*) as payments, SUM(amount) as total')
            ->groupBy('date')->orderBy('date')->get()
            ->map(fn ($r) => ['date' => $r->date, 'payments' => $r->payments, 'total' => number_format((float) $r->total, 2)])
            ->all();

        return ['Daily collection', ['date' => 'Date', 'payments' => 'Payments', 'total' => 'Amount'], $rows,
            ['Total' => number_format(array_sum(array_map(fn ($r) => (float) str_replace(',', '', $r['total']), $rows)), 2)]];
    }

    /** Month bucket expression that works on MySQL and SQLite. */
    protected function monthExpression(string $column): string
    {
        return match (\Illuminate\Support\Facades\DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    protected function monthlyCollection(int $ispId, string $from, string $to): array
    {
        $rows = Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereBetween('paid_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->selectRaw($this->monthExpression('paid_at').' as month, COUNT(*) as payments, SUM(amount) as total')
            ->groupBy('month')->orderBy('month')->get()
            ->map(fn ($r) => ['month' => $r->month, 'payments' => $r->payments, 'total' => number_format((float) $r->total, 2)])
            ->all();

        return ['Monthly collection', ['month' => 'Month', 'payments' => 'Payments', 'total' => 'Amount'], $rows, []];
    }

    protected function outstanding(int $ispId): array
    {
        $rows = Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->with('customer:id,name,customer_code,ppp_username')
            ->orderBy('due_date')->get()
            ->map(fn ($i) => [
                'invoice' => $i->invoice_number,
                'customer' => $i->customer?->name,
                'code' => $i->customer?->customer_code,
                'due' => $i->due_date?->format('Y-m-d'),
                'amount' => number_format((float) $i->amount, 2),
                'balance' => number_format((float) $i->balance, 2),
            ])->all();

        return ['Outstanding invoices', [
            'invoice' => 'Invoice', 'customer' => 'Customer', 'code' => 'Code',
            'due' => 'Due date', 'amount' => 'Amount', 'balance' => 'Balance',
        ], $rows, ['Outstanding total' => number_format(collect($rows)->sum(fn ($r) => (float) str_replace(',', '', $r['balance'])), 2)]];
    }

    protected function overdueCustomers(int $ispId): array
    {
        $rows = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereHas('invoices', fn ($q) => $q
                ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
                ->whereDate('due_date', '<', now()->toDateString()))
            ->with('router:id,name')
            ->get()
            ->map(fn ($c) => [
                'code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'ppp' => $c->ppp_username,
                'outstanding' => number_format($c->outstandingBalance(), 2),
                'suspension' => optional($c->next_suspension_date)->format('Y-m-d') ?? '-',
                'status' => $c->internet_status,
            ])->all();

        return ['Overdue customers', [
            'code' => 'Code', 'name' => 'Customer', 'phone' => 'Phone', 'ppp' => 'PPP user',
            'outstanding' => 'Outstanding', 'suspension' => 'Suspension date', 'status' => 'Internet',
        ], $rows, []];
    }

    protected function pppReport(int $ispId, bool $active): array
    {
        $rows = PppAccount::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('is_active_session', $active)
            ->with('router:id,name')
            ->orderBy('username')->get()
            ->map(fn ($a) => [
                'username' => $a->username,
                'router' => $a->router?->name,
                'profile' => $a->profile,
                'address' => $a->remote_address,
                'uptime' => $a->session_uptime ?? '-',
                'synced' => optional($a->last_synced_at)->format('Y-m-d H:i'),
            ])->all();

        return [
            $active ? 'Active PPP sessions' : 'Inactive PPP accounts',
            ['username' => 'PPP user', 'router' => 'Router', 'profile' => 'Profile', 'address' => 'Address', 'uptime' => 'Uptime', 'synced' => 'Last synced'],
            $rows, ['Count' => count($rows)],
        ];
    }

    protected function suspended(int $ispId): array
    {
        $rows = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('internet_status', 'suspended')
            ->get()
            ->map(fn ($c) => [
                'code' => $c->customer_code,
                'name' => $c->name,
                'ppp' => $c->ppp_username,
                'suspended_at' => optional($c->last_suspended_at)->format('Y-m-d H:i') ?? '-',
                'owned' => $c->suspension_owned_by_billing ? 'Billing' : 'Manual',
                'outstanding' => number_format($c->outstandingBalance(), 2),
            ])->all();

        return ['Suspended customers', [
            'code' => 'Code', 'name' => 'Customer', 'ppp' => 'PPP user',
            'suspended_at' => 'Suspended at', 'owned' => 'Suspended by', 'outstanding' => 'Outstanding',
        ], $rows, ['Count' => count($rows)]];
    }

    protected function approvals(int $ispId, string $from, string $to): array
    {
        $rows = PaymentSubmission::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereBetween('reviewed_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with(['customer:id,name,customer_code', 'reviewer:id,name'])
            ->orderByDesc('reviewed_at')->get()
            ->map(fn ($s) => [
                'customer' => $s->customer?->name,
                'invoice' => $s->invoice_id,
                'amount' => number_format((float) $s->amount, 2),
                'status' => $s->statusLabel(),
                'reviewed_by' => $s->reviewer?->name ?? '-',
                'reviewed_at' => optional($s->reviewed_at)->format('Y-m-d H:i'),
            ])->all();

        return ['Payment approvals', [
            'customer' => 'Customer', 'invoice' => 'Invoice', 'amount' => 'Amount',
            'status' => 'Status', 'reviewed_by' => 'Reviewed by', 'reviewed_at' => 'Reviewed at',
        ], $rows, []];
    }

    protected function invoiceFailures(int $ispId): array
    {
        $rows = \App\Models\AuditLog::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereIn('action', ['invoice.generation_failed', 'invoice.failed'])
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn ($l) => [
                'when' => $l->created_at?->format('Y-m-d H:i'),
                'action' => $l->action,
                'description' => $l->description,
            ])->all();

        return ['Invoice generation failures', ['when' => 'When', 'action' => 'Action', 'description' => 'Details'], $rows,
            ['Count' => count($rows)]];
    }

    protected function mikrotikFailures(int $ispId, string $from, string $to): array
    {
        $rows = MikrotikSyncLog::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('status', 'failed')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->with('router:id,name')
            ->orderByDesc('id')->get()
            ->map(fn ($l) => [
                'when' => $l->created_at?->format('Y-m-d H:i'),
                'router' => $l->router?->name,
                'type' => $l->type,
                'message' => Str::limit((string) $l->message, 160),
            ])->all();

        return ['MikroTik operation failures', [
            'when' => 'When', 'router' => 'Router', 'type' => 'Operation', 'message' => 'Error',
        ], $rows, ['Count' => count($rows)]];
    }
}
