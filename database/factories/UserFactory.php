<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $suffix = Str::lower(Str::random(8));

        return [
            'name' => $this->fakerName(),
            'email' => $this->fakerEmail($suffix),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Admin,
            'branch_id' => null,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function fakerName(): string
    {
        if (function_exists('fake')) {
            return fake()->name();
        }

        return 'User '.$this->fakerSuffix();
    }

    private function fakerEmail(string $suffix): string
    {
        if (function_exists('fake')) {
            return fake()->unique()->safeEmail();
        }

        return "user-{$suffix}@example.com";
    }

    private function fakerSuffix(): string
    {
        return Str::lower(Str::random(8));
    }
}
