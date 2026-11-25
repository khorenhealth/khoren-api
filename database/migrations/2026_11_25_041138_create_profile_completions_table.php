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
        Schema::create('profile_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('accuracy_confirmed')->default(false);
            $table->timestamp('accuracy_confirmed_at')->nullable();
            $table->timestamp('setup_completed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profile_completions');
    }
};
