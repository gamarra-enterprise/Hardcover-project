<?php

namespace Database\Seeders;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Zones and rates taken from the AxisLab requirements: Lima Metropolitana from S/ 10 and
 * Lima Provincia from S/ 15. The shop delivers only there for now; any other region is shown as
 * "próximamente" at checkout.
 *
 * Lima Provincia loads the 128 districts of the other nine provinces of the department, but only
 * the coastal provinces start active. The mountain ones are loaded inactive for the administrator
 * to switch on. Ubigeo codes follow the INEI list as I remember it and should be checked once
 * against the official file.
 *
 * Running it again keeps the rates and the active flag that were edited.
 */
class ShippingSeeder extends Seeder
{
    /** ubigeo => district name, province of Lima (1501) */
    private const LIMA_METROPOLITANA = [
        '150101' => 'Lima', '150102' => 'Ancón', '150103' => 'Ate', '150104' => 'Barranco', '150105' => 'Breña',
        '150106' => 'Carabayllo', '150107' => 'Chaclacayo', '150108' => 'Chorrillos', '150109' => 'Cieneguilla',
        '150110' => 'Comas', '150111' => 'El Agustino', '150112' => 'Independencia', '150113' => 'Jesús María',
        '150114' => 'La Molina', '150115' => 'La Victoria', '150116' => 'Lince', '150117' => 'Los Olivos',
        '150118' => 'Lurigancho', '150119' => 'Lurín', '150120' => 'Magdalena del Mar', '150121' => 'Pueblo Libre',
        '150122' => 'Miraflores', '150123' => 'Pachacámac', '150124' => 'Pucusana', '150125' => 'Puente Piedra',
        '150126' => 'Punta Hermosa', '150127' => 'Punta Negra', '150128' => 'Rímac', '150129' => 'San Bartolo',
        '150130' => 'San Borja', '150131' => 'San Isidro', '150132' => 'San Juan de Lurigancho',
        '150133' => 'San Juan de Miraflores', '150134' => 'San Luis', '150135' => 'San Martín de Porres',
        '150136' => 'San Miguel', '150137' => 'Santa Anita', '150138' => 'Santa María del Mar', '150139' => 'Santa Rosa',
        '150140' => 'Santiago de Surco', '150141' => 'Surquillo', '150142' => 'Villa El Salvador',
        '150143' => 'Villa María del Triunfo',
    ];

    /** Province ubigeo prefix => [name, active by default, districts in the official order] */
    private const LIMA_PROVINCIA = [
        '1502' => ['Barranca', true, ['Barranca', 'Paramonga', 'Pativilca', 'Supe', 'Supe Puerto']],
        '1503' => ['Cajatambo', false, ['Cajatambo', 'Copa', 'Gorgor', 'Huancapón', 'Manás']],
        '1504' => ['Canta', false, ['Canta', 'Arahuay', 'Huamantanga', 'Huaros', 'Lachaqui', 'San Buenaventura', 'Santa Rosa de Quives']],
        '1505' => ['Cañete', true, ['San Vicente de Cañete', 'Asia', 'Calango', 'Cerro Azul', 'Chilca', 'Coayllo', 'Imperial', 'Lunahuaná', 'Mala', 'Nuevo Imperial', 'Pacarán', 'Quilmaná', 'San Antonio', 'San Luis', 'Santa Cruz de Flores', 'Zúñiga']],
        '1506' => ['Huaral', true, ['Huaral', 'Atavillos Alto', 'Atavillos Bajo', 'Aucallama', 'Chancay', 'Ihuarí', 'Lampián', 'Pacaraos', 'San Miguel de Acos', 'Santa Cruz de Andamarca', 'Sumbilca', 'Veintisiete de Noviembre']],
        '1507' => ['Huarochirí', false, ['Matucana', 'Antioquía', 'Callahuanca', 'Carampoma', 'Chicla', 'Cuenca', 'Huachupampa', 'Huanza', 'Huarochirí', 'Lahuaytambo', 'Langa', 'Laraos', 'Mariatana', 'Ricardo Palma', 'San Andrés de Tupicocha', 'San Antonio', 'San Bartolomé', 'San Damián', 'San Juan de Iris', 'San Juan de Tantaranche', 'San Lorenzo de Quinti', 'San Mateo', 'San Mateo de Otao', 'San Pedro de Casta', 'San Pedro de Huancayre', 'Sangallaya', 'Santa Cruz de Cocachacra', 'Santa Eulalia', 'Santiago de Anchucaya', 'Santiago de Tuna', 'Santo Domingo de los Olleros', 'Surco']],
        '1508' => ['Huaura', true, ['Huacho', 'Ámbar', 'Caleta de Carquín', 'Checras', 'Hualmay', 'Huaura', 'Leoncio Prado', 'Paccho', 'Santa Leonor', 'Santa María', 'Sayán', 'Vegueta']],
        '1509' => ['Oyón', false, ['Oyón', 'Andajes', 'Caujul', 'Cochamarca', 'Naván', 'Pachangara']],
        '1510' => ['Yauyos', false, ['Yauyos', 'Alis', 'Allauca', 'Ayaviri', 'Azángaro', 'Cacra', 'Carania', 'Catahuasi', 'Chocos', 'Cochas', 'Colonia', 'Hongos', 'Huampara', 'Huancaya', 'Huangáscar', 'Huantán', 'Huañec', 'Laraos', 'Lincha', 'Madeán', 'Miraflores', 'Omas', 'Putinza', 'Quinches', 'Quinocay', 'San Joaquín', 'San Pedro de Pilas', 'Tanta', 'Tauripampa', 'Tomás', 'Tupe', 'Viñac', 'Vitis']],
    ];

    public function run(): void
    {
        $metropolitana = $this->zone('Lima Metropolitana', '10.00', 0);
        $provincia = $this->zone('Lima Provincia', '15.00', 1);

        $this->provincia($provincia);

        foreach (self::LIMA_METROPOLITANA as $ubigeo => $name) {
            ShippingDistrict::firstOrCreate(
                ['ubigeo' => $ubigeo],
                ['shipping_zone_id' => $metropolitana->id, 'name' => $name, 'cost' => $metropolitana->min_cost],
            );
        }
    }

    private function provincia(ShippingZone $zone): void
    {
        foreach (self::LIMA_PROVINCIA as $prefix => [$province, $active, $districts]) {
            foreach ($districts as $i => $name) {
                ShippingDistrict::firstOrCreate(
                    ['ubigeo' => $prefix.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT)],
                    ['shipping_zone_id' => $zone->id, 'name' => $name, 'cost' => $zone->min_cost, 'is_active' => $active],
                );
            }
        }
    }

    private function zone(string $name, string $minCost, int $position): ShippingZone
    {
        return ShippingZone::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'min_cost' => $minCost, 'position' => $position],
        );
    }
}
