<?php

namespace Modules\Tenancy\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Tenancy\Models\BusinessType;

/**
 * Seeds the catalog of supported business types. Each is pure configuration
 * data (default units, attributes, receipt sections) — no code anywhere
 * branches on these keys. Adding a new business type later is a data change,
 * never a deploy.
 */
class BusinessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'key' => 'meat_shop',
                'name' => 'Meat Shop',
                'default_config' => [
                    'default_units' => ['kg', 'g', 'pcs'],
                    'sell_by_weight_default' => true,
                    'default_attributes' => ['expiry_date', 'batch_number'],
                    'default_customer_groups' => ['Retail', 'Credit'],
                    'receipt_sections' => ['items', 'weight', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'grocery',
                'name' => 'Grocery',
                'default_config' => [
                    'default_units' => ['kg', 'g', 'pcs', 'packet', 'liter'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['expiry_date'],
                    'default_customer_groups' => ['Retail', 'Credit'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'department_store',
                'name' => 'Department Store',
                'default_config' => [
                    'default_units' => ['pcs'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['size', 'color'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'restaurant',
                'name' => 'Restaurant',
                'default_config' => [
                    'default_units' => ['pcs', 'plate', 'bowl'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['spice_level'],
                    'default_customer_groups' => ['Table', 'Takeaway', 'Delivery'],
                    'receipt_sections' => ['items', 'totals', 'service_charge', 'thank_you'],
                ],
            ],
            [
                'key' => 'clothing',
                'name' => 'Clothing',
                'default_config' => [
                    'default_units' => ['pcs'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['size', 'color', 'material'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'stationery',
                'name' => 'Stationery',
                'default_config' => [
                    'default_units' => ['pcs', 'packet', 'dozen'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => [],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'pharmacy',
                'name' => 'Pharmacy',
                'default_config' => [
                    'default_units' => ['pcs', 'strip', 'bottle'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['expiry_date', 'batch_number', 'prescription_required'],
                    'default_customer_groups' => ['Retail', 'Credit'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'hardware',
                'name' => 'Hardware',
                'default_config' => [
                    'default_units' => ['pcs', 'meter', 'kg'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => [],
                    'default_customer_groups' => ['Retail', 'Credit'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'electronics',
                'name' => 'Electronics',
                'default_config' => [
                    'default_units' => ['pcs'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['warranty_months', 'serial_number'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'furniture',
                'name' => 'Furniture',
                'default_config' => [
                    'default_units' => ['pcs'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['material', 'dimensions'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'bakery',
                'name' => 'Bakery',
                'default_config' => [
                    'default_units' => ['pcs', 'kg', 'g', 'packet'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['expiry_date'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'agriculture_store',
                'name' => 'Agriculture Store',
                'default_config' => [
                    'default_units' => ['kg', 'g', 'packet', 'liter'],
                    'sell_by_weight_default' => true,
                    'default_attributes' => ['expiry_date'],
                    'default_customer_groups' => ['Retail', 'Credit'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'pet_shop',
                'name' => 'Pet Shop',
                'default_config' => [
                    'default_units' => ['pcs', 'kg', 'packet'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => ['expiry_date'],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
            [
                'key' => 'custom',
                'name' => 'Custom / Other Retail',
                'default_config' => [
                    'default_units' => ['pcs'],
                    'sell_by_weight_default' => false,
                    'default_attributes' => [],
                    'default_customer_groups' => ['Retail'],
                    'receipt_sections' => ['items', 'totals', 'thank_you'],
                ],
            ],
        ];

        foreach ($types as $type) {
            BusinessType::updateOrCreate(['key' => $type['key']], $type);
        }
    }
}
