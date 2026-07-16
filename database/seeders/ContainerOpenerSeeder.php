<?php

namespace Database\Seeders;

use App\Models\ContainerOpener;
use Illuminate\Database\Seeder;

class ContainerOpenerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [
            ['Port Clear DZ', '0553003001', 'clearance@portclear.test', 'NIF-CL-001'],
            ['Maghreb Transit Services', '0553003002', 'ops@maghreb-transit.test', 'NIF-CL-002'],
            ['Atlas Customs Broker', '0553003003', 'desk@atlas-customs.test', 'NIF-CL-003'],
        ];

        foreach ($rows as $row) {
            ContainerOpener::query()->updateOrCreate(
                ['email' => $row[2]],
                [
                    'name' => $row[0],
                    'phone' => $row[1],
                    'address' => fake()->address(),
                    'nif' => $row[3],
                    'notes' => 'Demo customs and container opening contact.',
                ]
            );
        }
    }
}
