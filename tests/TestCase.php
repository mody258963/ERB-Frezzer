<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Bearer-only auth in tests — avoid SPA session resolving the user via the `web` guard.
        config([
            'sanctum.stateful' => [],
            'sanctum.guard' => [],
        ]);
    }
}
