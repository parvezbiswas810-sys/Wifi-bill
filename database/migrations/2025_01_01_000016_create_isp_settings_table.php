<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isp_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('isp_id')->constrained('isp_profiles')->cascadeOnDelete();
            $table->string('key', 96);
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['isp_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isp_settings');
    }
};
