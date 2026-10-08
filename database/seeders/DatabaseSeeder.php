<?php

namespace Database\Seeders;

use App\Services\Inventory\InitialInventory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);
        app(InitialInventory::class)->import(); // the company's real equipment (D73)
    }
}
