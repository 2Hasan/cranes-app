<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crane_id')->constrained('cranes')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity', 20)->default('medium'); // low | medium | high | critical
            $table->string('status', 20)->default('open')->index(); // open | in_progress | resolved
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            // Set when the fault is closed by a maintenance record that fixed it.
            $table->foreignId('maintenance_record_id')->nullable()->constrained('maintenance_records')->nullOnDelete();
            $table->timestamp('reported_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faults');
    }
};
