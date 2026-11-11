<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $ownerId = DB::table('tire_owners')->where('name', 'ملكنا')->value('id')
            ?? DB::table('tire_owners')->insertGetId([
                'name' => 'ملكنا',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        foreach (['tire_intakes', 'tire_transfers'] as $table) {
            if (! Schema::hasColumn($table, 'tire_owner_id')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->foreignId('tire_owner_id')->nullable()->constrained('tire_owners')->restrictOnDelete();
                });
            }

            DB::table($table)->whereNull('tire_owner_id')->update(['tire_owner_id' => $ownerId]);

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tire_owner_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['tire_intakes', 'tire_transfers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('tire_owner_id');
            });
        }

        DB::table('tire_owners')->where('name', 'ملكنا')->delete();
    }
};
