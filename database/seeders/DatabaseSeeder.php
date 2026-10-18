<?php

namespace Database\Seeders;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed aplikasi dengan satu bisnis contoh dan dua pengguna demo.
     *
     * Password kedua akun demo adalah "password" (lihat UserFactory).
     */
    public function run(): void
    {
        $business = Business::factory()->create([
            'name' => 'TokoFlow Demo',
            'slug' => 'tokoflow-demo',
        ]);

        $owner = User::factory()->create([
            'name' => 'Owner Demo',
            'email' => 'owner@tokoflow.test',
        ]);

        $business->users()->attach($owner->getKey(), [
            'role' => BusinessRole::Owner->value,
        ]);

        $staff = User::factory()->create([
            'name' => 'Staff Demo',
            'email' => 'staff@tokoflow.test',
        ]);

        $business->users()->attach($staff->getKey(), [
            'role' => BusinessRole::Staff->value,
        ]);
    }
}
