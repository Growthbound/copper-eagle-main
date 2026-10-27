<?php

namespace Tests\Concerns;

/**
 * Reads the JSON-LD a page emits. Each node is its own script block, so this
 * returns every block in page order.
 */
trait ReadsStructuredData
{
    /**
     * @return list<array<string, mixed>>
     */
    protected function structuredDataIn(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">\s*(.*?)\s*<\/script>/s', $html, $matches);

        return array_map(function (string $json): array {
            $node = json_decode(html_entity_decode($json), true);

            $this->assertIsArray($node, 'A JSON-LD block did not parse: '.$json);

            return $node;
        }, $matches[1]);
    }
}
