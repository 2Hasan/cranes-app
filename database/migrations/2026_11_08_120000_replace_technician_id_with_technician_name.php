<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('technician_name', 120)->nullable()->after('crane_id');
        });

        // Keep the names of technicians already linked to a record.
        DB::table('maintenance_records')
            ->whereNotNull('technician_id')
            ->orderBy('id')
            ->each(function (object $record): void {
                $name = DB::table('users')->where('id', $record->technician_id)->value('name');

                DB::table('maintenance_records')
                    ->where('id', $record->id)
                    ->update(['technician_name' => $name]);
            });

        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropForeign(['technician_id']);
            $table->dropColumn('technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->foreignId('technician_id')->nullable()->after('crane_id')->constrained('users')->nullOnDelete();
            $table->dropColumn('technician_name');
        });
    }
};
