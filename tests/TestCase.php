<?php

namespace Tests;

use Database\Seeders\PassportClientSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('oauth_clients')) {
            return;
        }

        if (! file_exists(storage_path('oauth-private.key'))) {
            $this->artisan('passport:keys', ['--force' => true]);
        }

        $this->seed(PassportClientSeeder::class);
    }
}
