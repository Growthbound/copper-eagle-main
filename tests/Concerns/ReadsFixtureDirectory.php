<?php

namespace Tests\Concerns;

/**
 * Points a page or record repository at a directory of the test's choosing.
 *
 * @see UsesFixtureContent
 */
trait ReadsFixtureDirectory
{
    public function __construct(private readonly string $fixtureDirectory) {}

    public function directory(): string
    {
        return $this->fixtureDirectory;
    }
}
