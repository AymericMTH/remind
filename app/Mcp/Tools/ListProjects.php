<?php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-projects')]
#[Description('Return all my Re:Mind projects (lists) with their open-reminder counts. Use before adding a reminder so you know where to file it.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjects extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $request->user();
        $projects = $user->lists()
            ->orderBy('position')
            ->withCount(['reminders as open_count' => fn ($q) => $q->where('status', 'open')])
            ->get(['id', 'name', 'color', 'is_inbox', 'position'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
                'is_inbox' => (bool) $l->is_inbox,
                'open_count' => (int) $l->open_count,
            ])
            ->all();

        return Response::structured(['projects' => $projects]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
