<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->foreignId('router_id')->constrained('mikrotik_routers')->cascadeOnDelete();
            $table->string('username');
            // RouterOS internal .id, needed to target this secret for enable/disable.
            $table->string('router_secret_id')->nullable();
            $table->string('service', 32)->nullable();
            $table->string('profile')->nullable();
            $table->string('local_address')->nullable();
            $table->string('remote_address')->nullable();
            $table->string('caller_id')->nullable();
            $table->string('comment')->nullable();
            // Mirrors the PPP secret "disabled" flag on the router.
            $table->boolean('disabled')->default(false);
            $table->boolean('is_active_session')->default(false);
            $table->string('session_uptime')->nullable();
            $table->unsignedBigInteger('session_uptime_seconds')->nullable();
            $table->string('session_caller_id')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'username']);
            $table->index(['isp_id', 'is_active_session']);
            $table->index(['isp_id', 'disabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppp_accounts');
    }
};
