<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\MikrotikRouter;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\PppAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ISP administrator dashboard.
 *
 * Every figure is computed from the database. Collected money counts approved
 * payments only - pending submissions are deliberately excluded.
 */
class DashboardController extends Controller
{
    public function index()
    {
        $ispId = current_isp_id();
        $today = now()->startOfDay();
        $monthStart = now()->startOfMonth();

        // ---- Money -------------------------------------------------------
        $totalCollected = (float) Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->sum('amount');

        $collectedThisMonth = (float) Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('paid_at', '>=', $monthStart)
            ->sum('amount');

        $totalInvoiced = (float) Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->sum('amount');

        $invoicedThisMonth = (float) Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->where('issue_date', '>=', $monthStart->toDateString())
            ->sum('amount');

        $totalOutstanding = (float) Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->sum('balance');

        $overdueAmount = (float) Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_PARTIAL])
            ->whereDate('due_date', '<', $today->toDateString())
            ->sum('balance');

        // ---- Customers ---------------------------------------------------
        $totalCustomers = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->whereNull('deleted_at')->count();

        $billingCustomers = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->where('billing_enabled', true)
            ->where('status', 'active')->whereNull('deleted_at')->count();

        $suspendedCustomers = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->where('internet_status', 'suspended')->count();

        $pendingApprovals = PaymentSubmission::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('status', PaymentSubmission::STATUS_PENDING)
            ->count();

        // ---- PPP ---------------------------------------------------------
        $pppTotal = PppAccount::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->count();

        $pppActive = PppAccount::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->where('is_active_session', true)->count();

        $pppInactive = max(0, $pppTotal - $pppActive);

        // ---- Router status ----------------------------------------------
        $routers = MikrotikRouter::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)->whereNull('deleted_at')->get();

        $onlineRouters = $routers->where('last_status', 'online')->count();
        $latestSync = $routers->max('last_synced_at');

        // ---- Charts (last 12 months / last 14 days) ----------------------
        $monthlyCollection = $this->monthlyCollection($ispId, 12);
        $dailyCollection = $this->dailyCollection($ispId, 14);

        // ---- Lists -------------------------------------------------------
        $recentPayments = Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->with(['customer:id,name,customer_code', 'invoice:id,invoice_number'])
            ->orderByDesc('paid_at')->limit(8)->get();

        $recentInvoices = Invoice::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->with('customer:id,name,customer_code')
            ->orderByDesc('id')->limit(8)->get();

        $upcomingSuspensions = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('billing_enabled', true)
            ->where('status', 'active')
            ->where('internet_status', '!=', 'suspended')
            ->whereNotNull('next_suspension_date')
            ->whereDate('next_suspension_date', '>=', $today->toDateString())
            ->orderBy('next_suspension_date')
            ->limit(8)->get();

        $recentCustomers = Customer::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->orderByDesc('id')->limit(6)->get();

        $recentSyncLogs = \App\Models\MikrotikSyncLog::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->orderByDesc('id')->limit(5)->get();

        return view('admin.dashboard', compact(
            'totalCollected', 'collectedThisMonth', 'totalInvoiced', 'invoicedThisMonth',
            'totalOutstanding', 'overdueAmount', 'totalCustomers', 'billingCustomers',
            'suspendedCustomers', 'pendingApprovals', 'pppTotal', 'pppActive', 'pppInactive',
            'routers', 'onlineRouters', 'latestSync', 'monthlyCollection', 'dailyCollection',
            'recentPayments', 'recentInvoices', 'upcomingSuspensions', 'recentCustomers',
            'recentSyncLogs'
        ));
    }

    /**
     * Month bucket expression.
     *
     * DATE_FORMAT is MySQL-only, so SQLite gets an equivalent expression to keep
     * the dashboard working in tests and in any local development database.
     */
    protected function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    /** @return array{labels: array<int,string>, values: array<int,float>} */
    protected function monthlyCollection(int $ispId, int $months): array
    {
        $rows = Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('paid_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw($this->monthExpression('paid_at').' as bucket, SUM(amount) as total')
            ->groupBy('bucket')->pluck('total', 'bucket');

        $labels = [];
        $values = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $labels[] = now()->subMonths($i)->format('M Y');
            $values[] = round((float) ($rows[$key] ?? 0), 2);
        }

        return compact('labels', 'values');
    }

    /** @return array{labels: array<int,string>, values: array<int,float>} */
    protected function dailyCollection(int $ispId, int $days): array
    {
        $rows = Payment::withoutGlobalScope('isp')
            ->where('isp_id', $ispId)
            ->where('paid_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(paid_at) as bucket, SUM(amount) as total')
            ->groupBy('bucket')->pluck('total', 'bucket');

        $labels = [];
        $values = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('d M');
            $values[] = round((float) ($rows[$date->toDateString()] ?? 0), 2);
        }

        return compact('labels', 'values');
    }
}
