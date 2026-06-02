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

#[Name('list-reminders')]
#[Description('Search or browse my reminders. Defaults to open reminders only; pass status to include done. Optional project filter and free-text query.')]
#[IsReadOnly]
#[IsIdempotent]
class ListReminders extends Tool
{
    public function handle(Request $request): ResponseFactory
    {
        $user = $request->user();
        $status = $request->get('status', 'open');
        $limit = (int) ($request->get('limit', 20));
        $limit = max(1, min($limit, 100));
        $projectId = $request->get('project_id');
        $query = $request->get('query');

        $q = $user->reminders()->with('list:id,name')->orderByDesc('updated_at');

        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($projectId) {
            $q->where('list_id', (int) $projectId);
        }
        if ($query) {
            $needle = '%'.mb_strtolower((string) $query).'%';
            $q->where(function ($q2) use ($needle) {
                $q2->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(notes,\'\')) LIKE ?', [$needle]);
            });
        }

        $reminders = $q->limit($limit)->get()->map(fn ($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'status' => $r->status,
            'project' => ['id' => $r->list?->id, 'name' => $r->list?->name],
            'soft_due_date' => $r->soft_due_date?->toDateString(),
            'context' => $r->context,
            'completed_at' => $r->completed_at?->toIso8601String(),
        ])->all();

        return Response::structured(['reminders' => $reminders]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Restrict to a single project.'),
            'status' => $schema->string()->enum(['open', 'done', 'all'])->default('open')->description('Filter by status.'),
            'query' => $schema->string()->description('Case-insensitive search across title and notes.'),
            'limit' => $schema->integer()->default(20)->description('Max results (1-100, default 20).'),
        ];
    }
}
