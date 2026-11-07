<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('km_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crane_id')->constrained('cranes')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('km');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['crane_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('km_readings');
    }
};
