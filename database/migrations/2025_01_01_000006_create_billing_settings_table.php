<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->unique()->constrained('isp_profiles')->cascadeOnDelete();
            $table->decimal('default_bill_amount', 14, 2)->default(0);
            $table->string('default_billing_cycle', 16)->default('monthly');
            $table->time('invoice_generation_time')->default('00:05:00');
            $table->unsignedSmallInteger('default_due_days')->default(10);
            $table->unsignedSmallInteger('grace_period_days')->default(3);
            $table->time('suspension_time')->default('00:00:00');
            $table->boolean('auto_suspend_enabled')->default(true);
            $table->boolean('auto_restore_enabled')->default(true);
            $table->boolean('notify_on_invoice')->default(true);
            $table->boolean('notify_before_due')->default(true);
            $table->unsignedSmallInteger('notify_before_due_days')->default(3);
            $table->boolean('notify_on_suspension')->default(true);
            $table->string('sms_provider', 32)->default('none');
            $table->json('sms_config')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_settings');
    }
};
