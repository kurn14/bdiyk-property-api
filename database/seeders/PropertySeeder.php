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

        $classrooms = [
            'Borobudur' => [
                'capacity' => 80,
                'desc' => 'Ruang auditorium luas yang ideal untuk
 seminar skala besar atau workshop. Dilengkapi dengan AC,
 proyektor HD, dan sistem suara berkualitas.'
            ],
            'Prambanan' => [
                'capacity' => 70,
                'desc' => 'Ruang kelas kapasitas besar dengan pen
cahayaan alami yang baik, sangat cocok untuk kegiatan pelatihan a
tau presentasi bisnis.'
            ],
            'Mendut' => [
                'capacity' => 50,
                'desc' => 'Ruang kelas dengan desain modern, memastikan suasana belajar yang kondusif dan fokus bagi peserta.'
            ],
            'Boko' => [
                'capacity' => 50,
                'desc' => 'Ruang kelas multifungsi yang fleksibel untuk berbagai tata letak meja (U-shape, Classroom, atau Theater style).'
            ]
        ];

        foreach ($classrooms as $room => $data) {
            $rooms[] = [
                'property_type_id' => 1,
                'name' => 'Ruang Kelas ' . $room,
                'description' => $data['desc'],
                'capacity' => $data['capacity'],
                'status' => 'available'
            ];
        }

        $rooms[] = [
            'property_type_id' => 2,
            'name' => 'Ruang Rapat',
            'description' => 'Ruang meeting eksklusif dengan fasilitas Video Wall (susunan 4 layar monitor 2x2) untuk presentasi data yang presisi. Dilengkapi high-speed Wi-Fi dan meja rapat modular.',
            'capacity' => 20,
            'status' => 'available'
        ];

        // KAMAR VIP (5 kamar)
        $VIPRooms = ['101','102','103','104','129','131','133','135'];

        foreach ($VIPRooms as $room) {
            $rooms[] = [
                'property_type_id' => 3,
                'name' => 'Kamar '.$room,
                'description' => 'Kamar premium dengan kenyamanan eks
tra. Fasilitas mencakup kasur King Size, AC, TV, kamar mand
i dalam dengan air panas (water heater).',
                'capacity' => 1,
                'status' => 'available'
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
                'description' => 'Kamar menginap standar yang nyaman dengan 2 tempat tidur (Twin Bed). Dilengkapi AC, lemari pakaian, dan lingkungan yang tenang untuk beristirahat.',
                'capacity' => 2,
                'status' => 'available'
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
                'description' => 'Pilihan ekonomis untuk grup kecil atau keluarga. Kamar luas dengan 3 tempat tidur, AC, lemari pakaian, dan akses mudah ke area umum.',
                'capacity' => 3,
                'status' => 'available'
            ];
        }

        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        DB::table('properties')->truncate(); // Clear existing to avoid ID conflicts or old data
        \Illuminate\Support\Facades\Schema::enableForeignKeyConstraints();
        DB::table('properties')->insert($rooms);
    }
}