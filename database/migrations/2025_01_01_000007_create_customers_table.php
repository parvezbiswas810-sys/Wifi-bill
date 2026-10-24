<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->string('customer_code', 32);
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('username', 64);
            $table->string('password');
            $table->rememberToken();

            $table->foreignId('router_id')->nullable()->constrained('mikrotik_routers')->nullOnDelete();
            // One PPP account may back at most one customer - enforced by the unique index.
            $table->foreignId('ppp_account_id')->nullable()->unique()->constrained('ppp_accounts')->nullOnDelete();
            $table->string('ppp_username')->nullable();

            $table->decimal('monthly_bill_amount', 14, 2)->default(0);
            $table->boolean('billing_enabled')->default(true);
            $table->date('billing_start_date')->nullable();
            $table->unsignedTinyInteger('bill_generation_day')->default(1);
            $table->time('bill_generation_time')->default('00:05:00');
            $table->date('next_bill_date')->nullable();
            $table->date('last_billed_on')->nullable();
            $table->unsignedSmallInteger('due_days')->default(10);
            $table->unsignedSmallInteger('grace_period_days')->default(3);
            $table->date('next_suspension_date')->nullable();
            $table->time('suspension_time')->default('00:00:00');
            $table->boolean('auto_suspend')->default(true);

            $table->string('internet_status', 16)->default('unknown');
            // True only when this system disabled the PPP secret; prevents restoring
            // an account that an operator disabled for an unrelated reason.
            $table->boolean('suspension_owned_by_billing')->default(false);
            $table->timestamp('last_suspended_at')->nullable();
            $table->timestamp('last_restored_at')->nullable();

            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['isp_id', 'username']);
            $table->unique(['isp_id', 'customer_code']);
            $table->index(['isp_id', 'billing_enabled', 'status']);
            $table->index(['isp_id', 'next_bill_date']);
            $table->index(['isp_id', 'next_suspension_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
