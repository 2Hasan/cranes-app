<?php

namespace Database\Seeders;

use App\Models\Site;
use Illuminate\Database\Seeder;

class SiteSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['سافرة', 'المرور'] as $siteName) {
            Site::firstOrCreate(['name' => $siteName]);
        }
    }
}
