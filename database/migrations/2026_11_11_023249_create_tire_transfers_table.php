<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tire_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignId('to_site_id')->constrained('sites')->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('handled_by', 120);
            $table->timestamp('moved_at');
            $table->string('note', 500)->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('moved_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tire_transfers');
    }
};
