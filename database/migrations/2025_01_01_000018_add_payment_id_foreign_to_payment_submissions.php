<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * payment_submissions.payment_id and payments.submission_id point at each
 * other. MySQL rejects the cycle when both constraints are declared at table
 * creation time (errno 150), and SQLite silently accepts it, which is how this
 * reached production installs. payments is created after payment_submissions,
 * so the constraint is attached here once both tables exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->foreign('payment_id')
                ->references('id')->on('payments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropForeign(['payment_id']);
        });
    }
};
