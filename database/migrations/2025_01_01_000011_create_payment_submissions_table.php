<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('method_name')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('sender_number', 40)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('screenshot_path')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            // Set once, when approval records the immutable payment row.
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();

            // Blocks the same transaction reference from being submitted twice.
            $table->unique(['isp_id', 'payment_method_id', 'reference'], 'payment_sub_ref_unique');
            $table->index(['isp_id', 'status']);
            $table->index(['invoice_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_submissions');
    }
};
