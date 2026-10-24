<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internet_suspension_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('mikrotik_routers')->nullOnDelete();
            $table->string('action', 16);
            $table->string('status', 16);
            $table->string('reason')->nullable();
            $table->text('message')->nullable();
            $table->text('router_response')->nullable();
            $table->string('performed_by', 32)->default('system');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['isp_id', 'action', 'created_at']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_suspension_logs');
    }
};
