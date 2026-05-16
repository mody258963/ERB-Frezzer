<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Laravel\Passport\Client;
use Laravel\Passport\ClientRepository;

class PassportClientSeeder extends Seeder
{
    public function run(): void
    {
        $clients = app(ClientRepository::class);

        if (! $this->clientWithGrant('personal_access')) {
            $clients->createPersonalAccessGrantClient(
                config('app.name').' Personal Access',
                'users'
            );
        }

        if (! $this->clientWithGrant('password')) {
            $client = $clients->createPasswordGrantClient(
                config('app.name').' Password Grant',
                'users',
                confidential: true
            );

            if ($client->plainSecret) {
                $this->command?->warn('Save OAuth password client credentials in your .env:');
                $this->command?->line('PASSPORT_PASSWORD_CLIENT_ID='.$client->getKey());
                $this->command?->line('PASSPORT_PASSWORD_CLIENT_SECRET='.$client->plainSecret);
            }
        }
    }

    private function clientWithGrant(string $grant): bool
    {
        return Client::query()
            ->where('revoked', false)
            ->get()
            ->contains(fn (Client $client) => $client->hasGrantType($grant));
    }
}
