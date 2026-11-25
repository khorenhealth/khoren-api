<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('visit_reminders')->default(true);
            $table->boolean('medication_reminders')->default(true);
            $table->boolean('visit_reports_ready')->default(true);
            $table->boolean('clinical_alerts')->default(true);
            $table->boolean('channel_email')->default(true);
            $table->boolean('channel_sms')->default(true);
            $table->boolean('channel_in_app')->default(true);
            $table->boolean('promotions')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
