<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('invoice_number', 64)->unique();
            $table->date('billing_period_start');
            $table->date('billing_period_end');
            // Stable key (e.g. 2026-07) that makes invoice generation idempotent.
            $table->string('period_key', 32);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('suspension_date')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2);
            $table->string('status', 16)->default('unpaid');
            $table->string('generated_by', 16)->default('system');
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'period_key']);
            $table->index(['isp_id', 'status']);
            $table->index(['isp_id', 'issue_date']);
            $table->index(['isp_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
