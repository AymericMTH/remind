<?php

namespace Tests\Feature\Actions\Markdown;

use App\Actions\Markdown\RenderNotes;
use Tests\TestCase;

class RenderNotesTest extends TestCase
{
    public function test_returns_empty_string_for_null(): void
    {
        $this->assertSame('', (new RenderNotes())->run(null));
    }

    public function test_renders_basic_markdown(): void
    {
        $html = (new RenderNotes())->run("**hi**\n\n- a\n- b");
        $this->assertStringContainsString('<strong>hi</strong>', $html);
        $this->assertStringContainsString('<li>a</li>', $html);
    }

    public function test_strips_inline_html(): void
    {
        $html = (new RenderNotes())->run('<script>alert(1)</script> text');
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_rejects_javascript_links(): void
    {
        $html = (new RenderNotes())->run('[x](javascript:alert(1))');
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
