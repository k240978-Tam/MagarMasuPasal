<?php

namespace Modules\Units\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Units\Models\Unit;

/**
 * Seeds the platform-wide default units (business_id = null), grouped by
 * measurement family with one base unit each. Tenants can add their own
 * custom units (e.g. "crate", "sack") on top of these without touching
 * this data.
 */
class DefaultUnitSeeder extends Seeder
{
    public function run(): void
    {
        $kg = Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'kg'],
            ['name' => 'Kilogram', 'conversion_factor' => 1],
        );

        Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'g'],
            ['name' => 'Gram', 'base_unit_id' => $kg->id, 'conversion_factor' => 0.001],
        );

        $liter = Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'L'],
            ['name' => 'Liter', 'conversion_factor' => 1],
        );

        Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'mL'],
            ['name' => 'Milliliter', 'base_unit_id' => $liter->id, 'conversion_factor' => 0.001],
        );

        $pcs = Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'pcs'],
            ['name' => 'Piece', 'conversion_factor' => 1],
        );

        Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'dz'],
            ['name' => 'Dozen', 'base_unit_id' => $pcs->id, 'conversion_factor' => 12],
        );

        Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'pkt'],
            ['name' => 'Packet', 'conversion_factor' => 1],
        );

        Unit::updateOrCreate(
            ['business_id' => null, 'symbol' => 'm'],
            ['name' => 'Meter', 'conversion_factor' => 1],
        );
    }
}
