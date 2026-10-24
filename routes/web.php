<?php

use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MikrotikController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PppController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\CustomerLoginController;
use App\Http\Controllers\Auth\IspLoginController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\InvoiceController as CustomerInvoiceController;
use App\Http\Controllers\Customer\PaymentController as CustomerPaymentController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer billing portal
|--------------------------------------------------------------------------
| "/" is the customer login page.
*/

Route::middleware('guest:customer')->group(function () {
    Route::get('/', [CustomerLoginController::class, 'showLogin'])->name('customer.login');
    Route::post('/', [CustomerLoginController::class, 'login'])->middleware('throttle:10,1')->name('customer.login.attempt');
});

Route::post('/logout', [CustomerLoginController::class, 'logout'])->name('customer.logout');

Route::middleware(['auth:customer', 'isp'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/dashboard', [CustomerDashboardController::class, 'index'])->name('dashboard');

    Route::get('/invoices', [CustomerInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [CustomerInvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/invoices/{invoice}/print', [CustomerInvoiceController::class, 'print'])->name('invoices.print');

    Route::get('/payments', [CustomerPaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/new', [CustomerPaymentController::class, 'create'])->name('payments.create');
    Route::post('/payments', [CustomerPaymentController::class, 'store'])->name('payments.store');

    Route::get('/connection', [CustomerProfileController::class, 'connection'])->name('connection');
    Route::get('/profile', [CustomerProfileController::class, 'show'])->name('profile');
    Route::put('/profile/password', [CustomerProfileController::class, 'updatePassword'])->name('profile.password');
    Route::get('/notifications', [CustomerProfileController::class, 'notifications'])->name('notifications');
});

/*
|--------------------------------------------------------------------------
| ISP administrator
|--------------------------------------------------------------------------
| "/loginisp" is the ISP administrator login page.
*/

Route::middleware('guest:isp')->group(function () {
    Route::get('/loginisp', [IspLoginController::class, 'showLogin'])->name('isp.login');
    Route::post('/loginisp', [IspLoginController::class, 'login'])->middleware('throttle:10,1')->name('isp.login.attempt');
});

Route::post('/loginisp/logout', [IspLoginController::class, 'logout'])->name('isp.logout');

Route::middleware(['auth:isp', 'isp'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ---- Customers ---------------------------------------------------
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    Route::post('/customers/{customer}/toggle-billing', [CustomerController::class, 'toggleBilling'])->name('customers.toggle-billing');
    Route::post('/customers/{customer}/toggle-internet', [CustomerController::class, 'toggleInternet'])->name('customers.toggle-internet');
    Route::get('/routers/{router}/ppp-options', [CustomerController::class, 'pppOptions'])->name('routers.ppp-options');

    // ---- PPP clients -------------------------------------------------
    Route::get('/ppp-clients', [PppController::class, 'index'])->name('ppp.index');
    Route::post('/routers/{router}/sync', [PppController::class, 'sync'])->name('ppp.sync');

    // ---- MikroTik routers --------------------------------------------
    Route::get('/mikrotik', [MikrotikController::class, 'index'])->name('mikrotik.index');
    Route::post('/mikrotik', [MikrotikController::class, 'store'])->name('mikrotik.store');
    Route::put('/mikrotik/{router}', [MikrotikController::class, 'update'])->name('mikrotik.update');
    Route::delete('/mikrotik/{router}', [MikrotikController::class, 'destroy'])->name('mikrotik.destroy');
    Route::post('/mikrotik/{router}/test', [MikrotikController::class, 'test'])->name('mikrotik.test');
    Route::post('/mikrotik/{router}/sync', [MikrotikController::class, 'sync'])->name('mikrotik.sync');
    Route::post('/mikrotik/{router}/disconnect', [MikrotikController::class, 'disconnect'])->name('mikrotik.disconnect');
    Route::get('/mikrotik-logs', [MikrotikController::class, 'logs'])->name('mikrotik.logs');

    // ---- Billing history ---------------------------------------------
    Route::get('/billing-history', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/billing-history/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/billing-history/{invoice}/print', [InvoiceController::class, 'print'])->name('invoices.print');
    Route::post('/billing-history/generate', [InvoiceController::class, 'generate'])->name('invoices.generate');
    Route::post('/billing-history/{invoice}/payment', [InvoiceController::class, 'recordPayment'])->name('invoices.record-payment');
    Route::post('/billing-history/{invoice}/cancel', [InvoiceController::class, 'cancel'])->name('invoices.cancel');
    Route::post('/billing-history/{invoice}/adjust', [InvoiceController::class, 'adjust'])->name('invoices.adjust');

    // ---- Payment approvals -------------------------------------------
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{submission}/approve', [PaymentController::class, 'approve'])->name('payments.approve');
    Route::post('/payments/{submission}/reject', [PaymentController::class, 'reject'])->name('payments.reject');
    Route::post('/payments/{submission}/request-info', [PaymentController::class, 'requestInfo'])->name('payments.request-info');
    Route::get('/payments/{submission}/screenshot', [PaymentController::class, 'screenshot'])->name('payments.screenshot');

    // ---- Payment methods ---------------------------------------------
    Route::get('/payment-methods', [PaymentMethodController::class, 'index'])->name('payment-methods.index');
    Route::post('/payment-methods', [PaymentMethodController::class, 'store'])->name('payment-methods.store');
    Route::put('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'update'])->name('payment-methods.update');
    Route::delete('/payment-methods/{paymentMethod}', [PaymentMethodController::class, 'destroy'])->name('payment-methods.destroy');

    // ---- Reports, notifications, audit, settings ----------------------
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    Route::get('/audit-log', [AuditController::class, 'index'])->name('audit.index');

    Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
    Route::put('/settings/profile', [SettingController::class, 'updateProfile'])->name('settings.profile');
    Route::put('/settings/billing', [SettingController::class, 'updateBilling'])->name('settings.billing');
    Route::put('/settings/password', [SettingController::class, 'updatePassword'])->name('settings.password');
    Route::post('/settings/revoke-sessions', [SettingController::class, 'logoutOtherSessions'])->name('settings.revoke-sessions');
});
