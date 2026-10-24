<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 32)->default('custom');
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->text('instructions')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('requires_reference')->default(true);
            $table->boolean('requires_sender')->default(true);
            $table->boolean('is_enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['isp_id', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
