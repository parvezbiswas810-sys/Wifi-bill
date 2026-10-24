<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('mikrotik_routers')->nullOnDelete();
            $table->string('type', 32);
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['isp_id', 'type', 'created_at']);
            $table->index(['router_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_sync_logs');
    }
};
