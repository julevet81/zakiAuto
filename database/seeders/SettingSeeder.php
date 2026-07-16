<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'company_name' => 'Zaki Auto',
            'company_phone' => '+213550000000',
            'company_email' => 'contact@zaki-auto.test',
            'default_currency' => 'DZD',
            'invoice_prefix' => 'INV',
            'order_prefix' => 'ORD',
            'current_exchange_rate' => '240.00',
        ];

        foreach ($settings as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
