<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Novela', 'Poesía', 'Ensayo', 'Infantil', 'Historia', 'Desarrollo personal', 'Tecnología',
            'Papelería', 'Accesorios', 'Figuras',
        ];

        foreach ($names as $position => $name) {
            Category::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'position' => $position, 'is_active' => true],
            );
        }
    }
}
