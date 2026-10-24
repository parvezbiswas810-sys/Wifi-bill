<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->string('audience', 16)->default('admin');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->string('severity', 16)->default('info');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['isp_id', 'audience', 'read_at']);
            $table->index(['customer_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};
