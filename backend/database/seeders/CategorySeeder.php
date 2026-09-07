<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Bahasa Indonesia descriptions keyed by the same index as the
     * predefined category names in CategoryFactory (single source of
     * truth for names lives in the factory, per Metis #11).
     */
    private const DESCRIPTIONS = [
        'Laporan untuk tumpukan sampah yang dibuang sembarangan di lahan kosong atau pinggir jalan.',
        'Laporan untuk tempat sampah umum yang sudah penuh dan belum dikosongkan.',
        'Laporan untuk sampah yang tidak terangkut sesuai jadwal pengangkutan.',
        'Laporan untuk pembuangan limbah B3 (bahan berbahaya dan beracun) secara ilegal.',
        'Laporan untuk saluran air yang tersumbat akibat penumpukan sampah.',
        'Laporan untuk sampah yang berserakan di taman atau fasilitas umum lainnya.',
        'Laporan untuk aktivitas pembakaran sampah yang dilakukan sembarangan.',
        'Laporan untuk kontainer atau tempat sampah besar yang rusak dan tidak bisa digunakan.',
        'Laporan untuk sampah medis yang dibuang tanpa prosedur yang benar.',
        'Laporan keluhan kebersihan lainnya di luar kategori yang tersedia.',
    ];

    /**
     * Seed the 10 predefined waste categories (idempotent via firstOrCreate).
     */
    public function run(): void
    {
        for ($i = 0; $i < 10; $i++) {
            // Read the shared name list through the factory helper so the
            // seeder and factory never drift apart.
            $name = Category::factory()->predefined($i)->make()->name;

            Category::firstOrCreate(
                ['name' => $name],
                ['description' => self::DESCRIPTIONS[$i], 'icon' => null],
            );
        }
    }
}
