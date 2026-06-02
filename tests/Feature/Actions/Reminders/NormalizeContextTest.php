<?php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\NormalizeContext;
use Tests\TestCase;

class NormalizeContextTest extends TestCase
{
    private NormalizeContext $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new NormalizeContext;
    }

    public function test_returns_null_when_input_is_null(): void
    {
        $this->assertNull($this->action->run(null));
    }

    public function test_derives_repo_label_from_github_ssh_url(): void
    {
        $out = $this->action->run(['repo' => 'git@github.com:foo/bar.git']);
        $this->assertSame('foo/bar', $out['repo_label']);
    }

    public function test_derives_repo_label_from_https_github_url(): void
    {
        $out = $this->action->run(['repo' => 'https://github.com/foo/bar.git']);
        $this->assertSame('foo/bar', $out['repo_label']);
    }

    public function test_falls_back_to_cwd_basename_when_repo_unparseable(): void
    {
        $out = $this->action->run(['repo' => '/abs/local/path', 'cwd' => '/home/me/projects/widget']);
        $this->assertSame('widget', $out['repo_label']);
    }

    public function test_drops_unknown_keys(): void
    {
        $out = $this->action->run(['repo' => 'x', 'cwd' => '/a/b', 'evil' => 'no']);
        $this->assertArrayNotHasKey('evil', $out);
    }

    public function test_preserves_known_keys(): void
    {
        $out = $this->action->run([
            'repo' => 'git@github.com:foo/bar.git',
            'branch' => 'main',
            'file' => 'app/X.php',
            'line_start' => 10,
            'line_end' => 20,
            'cwd' => '/x',
        ]);
        $this->assertSame('main', $out['branch']);
        $this->assertSame('app/X.php', $out['file']);
        $this->assertSame(10, $out['line_start']);
        $this->assertSame(20, $out['line_end']);
    }
}
