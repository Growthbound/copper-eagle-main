<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Keeps the voice reachable from both agents that write for this site.
 *
 * The guidance is one file. Claude Code finds it through a skill in `.claude`,
 * Codex through `AGENTS.md`, and neither discovers the other's route. A rename
 * or a tidy-up that breaks one entry point leaves that agent writing in
 * whatever register it defaults to, which for a coding agent is blunt and
 * matter-of-fact — the exact voice this document exists to replace.
 *
 * Nothing here judges the prose. It only asserts that the paths still connect.
 */
class BrandVoiceSkillTest extends TestCase
{
    private const CANONICAL = 'docs/brand/voice.md';

    public function test_the_voice_document_exists_and_is_the_whole_guide(): void
    {
        $voice = $this->read(self::CANONICAL);

        $this->assertNotSame('', trim($voice), 'The brand voice document is empty.');

        foreach ([
            'Core brand personality',
            'Primary voice principles',
            'Words and phrases to avoid',
            'Accuracy and integrity',
            'Review checklist',
        ] as $section) {
            $this->assertStringContainsString(
                $section,
                $voice,
                "The voice document has lost its \"{$section}\" section.",
            );
        }
    }

    public function test_agents_md_sends_codex_to_the_voice_document(): void
    {
        $agents = $this->read('AGENTS.md');

        $this->assertStringContainsString(
            self::CANONICAL,
            $agents,
            'AGENTS.md no longer points at the brand voice, so Codex will not find it.',
        );

        $this->assertStringContainsString(
            '## Brand voice',
            $agents,
            'AGENTS.md has lost its brand voice section.',
        );
    }

    public function test_the_claude_skill_is_discoverable_and_sends_claude_to_the_same_file(): void
    {
        $skill = $this->read('.claude/skills/brand-voice/SKILL.md');

        // Claude Code only offers a skill it can read frontmatter from.
        $this->assertMatchesRegularExpression(
            '/\A---\r?\n.*?^name:\s*brand-voice\s*$.*?^description:\s*\S.*?\r?\n---\r?\n/ms',
            $skill,
            'The skill is missing the name/description frontmatter that makes it discoverable.',
        );

        $this->assertStringContainsString(
            self::CANONICAL,
            $skill,
            'The skill no longer points at the voice document.',
        );
    }

    private function read(string $path): string
    {
        $absolute = dirname(__DIR__, 2).'/'.$path;

        $this->assertFileExists($absolute, $path.' is missing.');

        return (string) file_get_contents($absolute);
    }
}
