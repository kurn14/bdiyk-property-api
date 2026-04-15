<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PropertyType;

class PropertyTypeSeeder extends Seeder
{
    public function run(): void
    {
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        \App\Models\PropertyType::truncate();
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();

        $types = [
            ['id' => 1, 'name' => 'Ruang Kelas', 'description' => 'Borobudur, Prambanan, Mendut, Boko', 'is_continuous_booking' => false],
            ['id' => 2, 'name' => 'Meeting Room', 'description' => 'Ruang Rapat', 'is_continuous_booking' => false],
            ['id' => 3, 'name' => 'Kamar VIP', 'description' => 'Kamar Type VIP', 'is_continuous_booking' => true],
            ['id' => 4, 'name' => 'Kamar Inap 2 Bed', 'description' => 'Kamar Tidur 2 Orang', 'is_continuous_booking' => true],
            ['id' => 5, 'name' => 'Kamar Inap 3 Bed ', 'description' => 'Kamar Tidur 3 Orang', 'is_continuous_booking' => true],
        ];

        foreach ($types as $type) {
            \Illuminate\Support\Facades\DB::table('property_types')->insert($type);
        }
    }
}
