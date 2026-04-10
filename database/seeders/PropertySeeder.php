<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PropertySeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [];



        // CLASSROOMS
        $classrooms = [
            'Borobudur' => 80,
            'Prambanan' => 70,
            'Mendut' => 50,
            'Boko' => 50
        ];
        foreach ($classrooms as $room => $capacity) {
            $rooms[] = [
                'property_type_id' => 1,
                'name' => 'Ruang Kelas ' . $room,
                'description' => 'Ruang Kelas ' . $room,
                'capacity' => $capacity,
                'status' => 'available',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        $rooms[] = [
            'property_type_id' => 2,
            'name' => 'Ruang Rapat',
            'description' => 'Ruang Meeting',
            'capacity' => 20, // Default capacity
            'status' => 'available',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];

        // KAMAR VIP (5 kamar)
        $VIPRooms = ['101','102','103','104','129','131','133','135'];

        foreach ($VIPRooms as $room) {
            $rooms[] = [
                'property_type_id' => 3,
                'name' => 'Kamar '.$room,
                'description' => 'Kamar tipe VIP',
                'capacity' => 1,
                'status' => 'available',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // KAMAR BIASA 2 BED (17 kamar)
        $bed2Rooms = [
            '105','106','107','137','139','141',
            '201','202','203','204','207','208','205','206'
        ];

        foreach ($bed2Rooms as $room) {
            $rooms[] = [
                'property_type_id' => 4,
                'name' => 'Kamar '.$room,
                'description' => 'Kamar 2 bed',
                'capacity' => 2,
                'status' => 'available',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        // KAMAR BIASA 3 BED (27 kamar)
        $bed3Rooms = [
            '108','109','110','111','112','113','114','115',
            '116','117','118','119','120','121','122','123',
            '124','125','126','127',
            '128','130','132','134','136','138','140'
        ];

        foreach ($bed3Rooms as $room) {
            $rooms[] = [
                'property_type_id' => 5,
                'name' => 'Kamar '.$room,
                'description' => 'Kamar 3 bed',
                'capacity' => 3,
                'status' => 'available',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ];
        }

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('properties')->truncate(); // Clear existing to avoid ID conflicts or old data
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        DB::table('properties')->insert($rooms);
    }
}