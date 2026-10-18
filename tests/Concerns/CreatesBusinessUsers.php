<?php

namespace Tests\Concerns;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\User;

trait CreatesBusinessUsers
{
    /**
     * Buat satu bisnis dengan satu pengguna sebagai anggotanya.
     *
     * @param  array<string, mixed>  $businessAttributes
     * @return array{0: User, 1: Business}
     */
    protected function businessWithUser(
        BusinessRole $role = BusinessRole::Owner,
        array $businessAttributes = [],
    ): array {
        $business = Business::factory()->create($businessAttributes);
        $user = User::factory()->create();

        $business->users()->attach($user->getKey(), ['role' => $role->value]);

        return [$user, $business];
    }
}
