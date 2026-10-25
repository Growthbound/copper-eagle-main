<?php

namespace Tests\Feature;

use Tests\Concerns\IsolatesManagedContent;
use Tests\TestCase;

/**
 * The bare domain and the www domain are one site. Serving both splits links
 * and rankings between two addresses, so the one that is not canonical sends
 * visitors to the one that is. Any other host, such as a staging host, is left
 * alone: it stays reachable and serves noindex.
 */
class CanonicalHostRedirectTest extends TestCase
{
    use IsolatesManagedContent;

    public function test_the_bare_domain_redirects_to_the_canonical_www_host(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        $this->get('https://example.com/drain-cleaning?utm_source=card')
            ->assertStatus(301)
            ->assertHeader('Location', 'https://www.example.com/drain-cleaning?utm_source=card');
    }

    public function test_the_www_domain_redirects_when_the_bare_domain_is_canonical(): void
    {
        config()->set('search.canonical_host', 'example.com');

        $this->get('http://www.example.com/')
            ->assertStatus(301)
            ->assertHeader('Location', 'http://example.com/');
    }

    public function test_the_canonical_host_and_other_hosts_are_not_redirected(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        $this->get('https://www.example.com/robots.txt')->assertOk();
        $this->get('https://staging.agency.test/robots.txt')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_nothing_redirects_without_a_canonical_host(): void
    {
        config()->set('search.canonical_host', null);

        $this->get('https://example.com/robots.txt')->assertOk();
    }

    public function test_a_form_post_to_the_twin_host_is_not_redirected(): void
    {
        config()->set('search.canonical_host', 'www.example.com');

        // A redirect would turn the POST into a GET and drop the submission.
        $this->assertNotSame(301, $this->post('https://example.com/livewire/update')->getStatusCode());
    }
}
