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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_code', 6)->unique();
            $table->foreignId('property_id')->constrained('properties')->onDelete('cascade');
            $table->foreignId('property_type_id')->nullable()->constrained('property_types')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('contact_name');
            $table->string('contact_email');
            $table->string('contact_phone');
            $table->string('institution')->nullable();
            $table->string('status')->default('booked');
            $table->dateTime('payment_time_limit')->nullable();
            $table->timestamps();
        });

        Schema::create('booking_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_schedules');
        Schema::dropIfExists('bookings');
    }
};
