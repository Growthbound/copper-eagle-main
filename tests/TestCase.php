<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\URL;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'http://localhost');
        URL::forceRootUrl('http://localhost');

        // The suite asserts the behaviour of the real public site, so the test
        // host is the canonical one unless a test deliberately says otherwise.
        config()->set('search.canonical_host', 'localhost');
    }
}
