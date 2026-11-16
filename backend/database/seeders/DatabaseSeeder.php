<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Cliente de prueba para probar login/registro rápidamente:
        // email: cliente@demo.test / password: password
        User::query()->firstOrCreate(
            ['email' => 'cliente@demo.test'],
            [
                'name' => 'Cliente Demo',
                'password' => 'password',
                'phone' => '+503 7000-0000',
                'address' => 'San Salvador, El Salvador',
            ]
        );

        $this->call([
            ProductSeeder::class,
        ]);
    }
}
