<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * The 16 sample products of the UI prototype. Cover colors, ratings and the new/best-seller
 * flags of the prototype have no column yet and are not seeded.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->products() as $data) {
            $category = Category::where('name', $data['category'])->firstOrFail();
            $book = $data['book'] ?? null;
            unset($data['category'], $data['book']);

            $product = Product::updateOrCreate(['sku' => $data['sku']], $data);
            $product->categories()->syncWithoutDetaching([$category->id]);

            if ($book) {
                $product->bookDetail()->updateOrCreate([], $book);
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function products(): array
    {
        return [
            [
                'sku' => 'HB-001', 'name' => 'Conversación en La Catedral', 'slug' => 'conversacion-en-la-catedral',
                'category' => 'Novela', 'price' => 79.9, 'sale_price' => null, 'stock' => 14,
                'weight_grams' => 620, 'width_mm' => 150, 'height_mm' => 230, 'depth_mm' => 40,
                'description' => 'Santiago Zavala intenta entender en qué momento se jodió el Perú. Una conversación de cuatro horas en un bar de Lima abre una novela coral sobre poder, miedo y familia.',
                'book' => ['author' => 'Mario Vargas Llosa', 'publisher' => 'Alfaguara', 'published_year' => 1969, 'pages' => 664, 'format' => 'Tapa dura'],
            ],
            [
                'sku' => 'HB-002', 'name' => 'Los ríos profundos', 'slug' => 'los-rios-profundos',
                'category' => 'Novela', 'price' => 52, 'sale_price' => null, 'stock' => 9,
                'weight_grams' => 310, 'width_mm' => 130, 'height_mm' => 200, 'depth_mm' => 22,
                'description' => 'Ernesto, hijo de un abogado itinerante, llega a un colegio religioso del Cusco. Un clásico sobre la mirada indígena y el paisaje andino.',
                'book' => ['author' => 'José María Arguedas', 'publisher' => 'Alianza Editorial', 'published_year' => 1958, 'pages' => 296, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-003', 'name' => 'Cien años de soledad', 'slug' => 'cien-anos-de-soledad',
                'category' => 'Novela', 'price' => 62, 'sale_price' => 54.9, 'stock' => 22,
                'weight_grams' => 520, 'width_mm' => 140, 'height_mm' => 215, 'depth_mm' => 32,
                'description' => 'La saga de los Buendía en Macondo, del fundador al último descendiente. Edición de tapa dura con cinta marcapáginas.',
                'book' => ['author' => 'Gabriel García Márquez', 'publisher' => 'Penguin Random House', 'published_year' => 1967, 'pages' => 471, 'format' => 'Tapa dura'],
            ],
            [
                'sku' => 'HB-004', 'name' => 'Pedro Páramo', 'slug' => 'pedro-paramo',
                'category' => 'Novela', 'price' => 48, 'sale_price' => null, 'stock' => 11,
                'weight_grams' => 190, 'width_mm' => 125, 'height_mm' => 195, 'depth_mm' => 14,
                'description' => 'Juan Preciado viaja a Comala para buscar a su padre y encuentra un pueblo habitado por murmullos. Breve, seco, inolvidable.',
                'book' => ['author' => 'Juan Rulfo', 'publisher' => 'RM', 'published_year' => 1955, 'pages' => 152, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-005', 'name' => 'Trilce', 'slug' => 'trilce',
                'category' => 'Poesía', 'price' => 39.9, 'sale_price' => null, 'stock' => 8,
                'weight_grams' => 240, 'width_mm' => 120, 'height_mm' => 190, 'depth_mm' => 16,
                'description' => 'Setenta y siete poemas que rompieron el idioma. Edición con notas críticas y cronología del autor.',
                'book' => ['author' => 'César Vallejo', 'publisher' => 'Cátedra', 'published_year' => 1922, 'pages' => 208, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-006', 'name' => 'El principito', 'slug' => 'el-principito',
                'category' => 'Infantil', 'price' => 45, 'sale_price' => null, 'stock' => 30,
                'weight_grams' => 160, 'width_mm' => 135, 'height_mm' => 200, 'depth_mm' => 12,
                'description' => 'Un piloto varado en el desierto conoce a un niño que viene de otro planeta. Con las ilustraciones originales del autor.',
                'book' => ['author' => 'Antoine de Saint-Exupéry', 'publisher' => 'Salamandra', 'published_year' => 1943, 'pages' => 96, 'format' => 'Tapa dura'],
            ],
            [
                'sku' => 'HB-007', 'name' => 'Tradiciones peruanas', 'slug' => 'tradiciones-peruanas',
                'category' => 'Historia', 'price' => 85, 'sale_price' => null, 'stock' => 3,
                'weight_grams' => 780, 'width_mm' => 150, 'height_mm' => 230, 'depth_mm' => 42,
                'description' => 'Anécdotas del virreinato y la república contadas con ironía. Una forma amena de recorrer tres siglos de historia.',
                'book' => ['author' => 'Ricardo Palma', 'publisher' => 'Cátedra', 'published_year' => 1872, 'pages' => 720, 'format' => 'Tapa dura'],
            ],
            [
                'sku' => 'HB-008', 'name' => 'Sapiens', 'slug' => 'sapiens',
                'category' => 'Ensayo', 'price' => 99, 'sale_price' => 84.9, 'stock' => 17,
                'weight_grams' => 560, 'width_mm' => 150, 'height_mm' => 230, 'depth_mm' => 34,
                'description' => 'De animales a dioses: una breve historia de la humanidad, desde la revolución cognitiva hasta la era de los algoritmos.',
                'book' => ['author' => 'Yuval Noah Harari', 'publisher' => 'Debate', 'published_year' => 2011, 'pages' => 496, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-009', 'name' => 'Hábitos atómicos', 'slug' => 'habitos-atomicos',
                'category' => 'Desarrollo personal', 'price' => 74.9, 'sale_price' => null, 'stock' => 25,
                'weight_grams' => 400, 'width_mm' => 145, 'height_mm' => 215, 'depth_mm' => 26,
                'description' => 'Cambios pequeños, resultados notables. Un método práctico para construir buenos hábitos y dejar los malos.',
                'book' => ['author' => 'James Clear', 'publisher' => 'Diana', 'published_year' => 2018, 'pages' => 320, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-010', 'name' => 'Código limpio', 'slug' => 'codigo-limpio',
                'category' => 'Tecnología', 'price' => 129, 'sale_price' => null, 'stock' => 0,
                'weight_grams' => 820, 'width_mm' => 190, 'height_mm' => 235, 'depth_mm' => 28,
                'description' => 'Manual de estilo para escribir software que otros puedan leer, mantener y extender.',
                'book' => ['author' => 'Robert C. Martin', 'publisher' => 'Anaya', 'published_year' => 2008, 'pages' => 464, 'format' => 'Tapa blanda'],
            ],
            [
                'sku' => 'HB-011', 'name' => 'Separadores magnéticos x6', 'slug' => 'separadores-magneticos-x6',
                'category' => 'Papelería', 'price' => 19.9, 'sale_price' => null, 'stock' => 40,
                'weight_grams' => 30, 'width_mm' => 25, 'height_mm' => 60, 'depth_mm' => 3,
                'description' => 'Seis separadores con imán que no se caen de la página. Se pueden usar en cualquier libro, de tapa blanda o dura. Material: Cartulina laminada con imán.',
            ],
            [
                'sku' => 'HB-012', 'name' => 'Llavero Hardcover', 'slug' => 'llavero-hardcover',
                'category' => 'Accesorios', 'price' => 14.9, 'sale_price' => null, 'stock' => 55,
                'weight_grams' => 40, 'width_mm' => 30, 'height_mm' => 45, 'depth_mm' => 4,
                'description' => 'Llavero de metal esmaltado con el logo de la librería. Viene en bolsita de tela. Material: Metal esmaltado.',
            ],
            [
                'sku' => 'HB-013', 'name' => 'Figura Gato Lector', 'slug' => 'figura-gato-lector',
                'category' => 'Figuras', 'price' => 89, 'sale_price' => null, 'stock' => 6,
                'weight_grams' => 280, 'width_mm' => 70, 'height_mm' => 95, 'depth_mm' => 70,
                'description' => 'El gato de la casa leyendo en su rincón favorito. Edición limitada, pintada a mano, numerada en la base. Material: Resina pintada a mano.',
            ],
            [
                'sku' => 'HB-014', 'name' => 'Cuaderno de lectura Bookery', 'slug' => 'cuaderno-de-lectura-bookery',
                'category' => 'Papelería', 'price' => 29.9, 'sale_price' => null, 'stock' => 19,
                'weight_grams' => 220, 'width_mm' => 148, 'height_mm' => 210, 'depth_mm' => 12,
                'description' => 'Registro de lecturas con espacio para citas, valoración y fecha de inicio y fin. Hojas punteadas. Material: Papel 100 g, 120 hojas.',
            ],
            [
                'sku' => 'HB-015', 'name' => 'Tote bag Hardcover', 'slug' => 'tote-bag-hardcover',
                'category' => 'Accesorios', 'price' => 34.9, 'sale_price' => 27.9, 'stock' => 12,
                'weight_grams' => 150, 'width_mm' => 380, 'height_mm' => 420, 'depth_mm' => 5,
                'description' => 'Bolsa de algodón con capacidad para cuatro libros grandes. Asa larga para llevarla al hombro. Material: Algodón 200 g.',
            ],
            [
                'sku' => 'HB-016', 'name' => 'Luz de lectura con clip', 'slug' => 'luz-de-lectura-con-clip',
                'category' => 'Accesorios', 'price' => 44.9, 'sale_price' => null, 'stock' => 5,
                'weight_grams' => 90, 'width_mm' => 40, 'height_mm' => 40, 'depth_mm' => 110,
                'description' => 'Luz cálida con tres intensidades y cuello flexible. Se sujeta al lomo del libro o a la cama. Material: ABS, recargable USB-C.',
            ],
        ];
    }
}
