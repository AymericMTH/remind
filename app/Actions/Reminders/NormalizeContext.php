<?php

namespace App\Actions\Reminders;

class NormalizeContext
{
    private const ALLOWED_KEYS = ['repo', 'repo_label', 'branch', 'file', 'line_start', 'line_end', 'cwd'];

    /**
     * @param  array<string,mixed>|null  $context
     * @return array<string,mixed>|null
     */
    public function run(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $out = array_intersect_key($context, array_flip(self::ALLOWED_KEYS));

        if (! isset($out['repo_label'])) {
            $out['repo_label'] = $this->deriveLabel($out['repo'] ?? null, $out['cwd'] ?? null);
        }

        return $out;
    }

    private function deriveLabel(?string $repo, ?string $cwd): ?string
    {
        if ($repo) {
            // Match git@github.com:owner/repo.git or https://github.com/owner/repo.git
            if (preg_match('#(?:git@|https?://)[^/]+[:/]([^/:]+)/([^/]+?)(?:\.git)?$#', $repo, $m)) {
                return $m[1].'/'.$m[2];
            }
        }

        return $cwd ? basename($cwd) : null;
    }
}
