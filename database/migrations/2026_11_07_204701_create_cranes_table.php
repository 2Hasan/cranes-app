<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cranes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name')->nullable();
            $table->string('type', 40);
            $table->decimal('capacity_ton', 8, 2)->nullable();
            $table->string('serial_number')->nullable();
            $table->unsignedSmallInteger('manufacture_year')->nullable();
            $table->foreignId('site_id')->constrained('sites')->restrictOnDelete();
            $table->string('status', 20)->default('working')->index();
            // Denormalized from km_readings (latest reading). Kept here so lists stay fast.
            $table->unsignedInteger('current_km')->default(0);
            $table->unsignedSmallInteger('service_interval_days')->nullable();
            $table->unsignedInteger('service_interval_km')->nullable();
            // Baseline values for the first service, used until a periodic record exists.
            $table->date('last_service_date')->nullable();
            $table->unsignedInteger('last_service_km')->nullable();
            $table->text('notes')->nullable();
            // Optimistic locking: an update must send the version it read.
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cranes');
    }
};
