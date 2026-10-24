<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_routers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->string('api_username');
            // Stored encrypted at rest via the model's encrypted cast.
            $table->text('api_password');
            $table->boolean('use_ssl')->default(false);
            $table->string('connection_mode', 16)->default('api');
            $table->text('notes')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('last_status', 16)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['isp_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_routers');
    }
};
