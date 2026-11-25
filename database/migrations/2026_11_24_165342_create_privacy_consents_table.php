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
        Schema::create('privacy_consents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('care_consent')->default(false);
            $table->timestamp('care_consent_given_at')->nullable();
            $table->boolean('anonymised_data_sharing')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('privacy_consents');
    }
};
