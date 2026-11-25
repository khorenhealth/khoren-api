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
        Schema::create('home_visit_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('street_address');
            $table->string('state');
            $table->string('local_government_area');
            $table->string('landmark')->nullable();
            $table->text('access_directions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_visit_addresses');
    }
};
