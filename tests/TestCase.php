<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A test never reaches a real service: an unfaked request fails it instead of calling
        // Intuit or Cloudflare with test keys.
        Http::preventStrayRequests();
    }
}
