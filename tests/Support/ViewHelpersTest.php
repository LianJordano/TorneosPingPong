<?php

declare(strict_types=1);

namespace PingPong\Tests\Support;

use PHPUnit\Framework\TestCase;

final class ViewHelpersTest extends TestCase
{
    public function test_icon_is_decorative_by_default(): void
    {
        $svg = icon('trophy', 'icon--lg');
        $this->assertStringContainsString('class="icon icon--lg"', $svg);
        $this->assertStringContainsString('aria-hidden="true"', $svg);
        $this->assertStringContainsString('href="#i-trophy"', $svg);
    }

    public function test_icon_with_label_is_announced_and_escaped(): void
    {
        $svg = icon('check', '', 'Pagó "sí"');
        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('aria-label="Pagó &quot;sí&quot;"', $svg);
        $this->assertStringNotContainsString('aria-hidden', $svg);
    }

    public function test_nav_current_matches_section_and_sub_paths(): void
    {
        $this->assertSame(' aria-current="page"', nav_current('/torneos', '/torneos'));
        $this->assertSame(' aria-current="page"', nav_current('/torneos', '/torneos/5/partidos'));
        $this->assertSame('', nav_current('/torneos', '/torneos-viejos'));
        $this->assertSame('', nav_current('/ranking', '/torneos'));
    }

    public function test_nav_current_home_only_matches_root(): void
    {
        $this->assertSame(' aria-current="page"', nav_current('/', '/'));
        $this->assertSame('', nav_current('/', '/torneos'));
    }

    public function test_current_path_strips_query_and_trailing_slash(): void
    {
        $previous = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/personas/?q=ana&page=2';
        try {
            $this->assertSame('/personas', current_path());
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previous;
            }
        }
    }

    public function test_initials(): void
    {
        $this->assertSame('AM', initials('ana maría pérez'));
        $this->assertSame('Á', initials('  Ángel '));
        $this->assertSame('?', initials(''));
    }
}
