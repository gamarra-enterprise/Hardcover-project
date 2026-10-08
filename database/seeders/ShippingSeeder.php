<?php

namespace Database\Seeders;

use App\Models\ShippingDistrict;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Zones and rates taken from the AxisLab requirements: Lima Metropolitana from S/ 10 and
 * Lima Provincia from S/ 15. The 43 districts of Lima Metropolitana are loaded; the districts of
 * Lima Provincia (and any other area) are added by the staff from the panel.
 * Running it again keeps the rates that were edited.
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

    public function run(): void
    {
        $metropolitana = $this->zone('Lima Metropolitana', '10.00', 0);
        $this->zone('Lima Provincia', '15.00', 1);

        foreach (self::LIMA_METROPOLITANA as $ubigeo => $name) {
            ShippingDistrict::firstOrCreate(
                ['ubigeo' => $ubigeo],
                ['shipping_zone_id' => $metropolitana->id, 'name' => $name, 'cost' => $metropolitana->min_cost],
            );
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
