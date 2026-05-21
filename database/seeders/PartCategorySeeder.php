<?php

namespace Database\Seeders;

use App\Models\PartCategory;
use Illuminate\Database\Seeder;

class PartCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['key' => 'compressor', 'name' => 'Compressor', 'sort_order' => 1],
            ['key' => 'evaporator', 'name' => 'Evaporator', 'sort_order' => 2],
            ['key' => 'fan_motor', 'name' => 'Fan Motor', 'sort_order' => 3],
            ['key' => 'controls', 'name' => 'Controls', 'sort_order' => 4],
            ['key' => 'electrical', 'name' => 'Electrical', 'sort_order' => 5],
            ['key' => 'refrigerant', 'name' => 'Refrigerant', 'sort_order' => 6],
            ['key' => 'seals', 'name' => 'Seals', 'sort_order' => 7],
        ];

        foreach ($rows as $row) {
            PartCategory::query()->firstOrCreate(
                ['key' => $row['key']],
                ['name' => $row['name'], 'sort_order' => $row['sort_order'], 'is_active' => true]
            );
        }
    }
}
