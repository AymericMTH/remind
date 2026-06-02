<?php

namespace App\Mcp\Tools;

use App\Actions\Reminders\CreateReminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add-reminder')]
#[Description('File a new reminder into a Re:Mind project. Provide EITHER project_id OR project_name (case-insensitive). If no project fits, call create-project first.')]
class AddReminder extends Tool
{
    public function handle(Request $request, CreateReminder $action): ResponseFactory
    {
        $user = $request->user();

        $projectId = $request->get('project_id');
        $projectName = $request->get('project_name');

        if (! $projectId && ! $projectName) {
            return Response::make(Response::error(
                'You must supply either project_id or project_name. Call list-projects first to see what exists.'
            ));
        }

        if (! $projectId) {
            $list = $user->lists()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $projectName)])
                ->first();
            if (! $list) {
                return Response::make(Response::error(
                    "No project named \"{$projectName}\". Call list-projects to see existing names or create-project to add a new one."
                ));
            }
            $projectId = $list->id;
        }

        try {
            $reminder = $action->run($user, [
                'list_id' => $projectId,
                'title' => (string) $request->get('title'),
                'notes' => $request->get('notes'),
                'soft_due_date' => $request->get('soft_due_date'),
                'context' => $request->get('context'),
            ]);
        } catch (ValidationException $e) {
            return Response::make(Response::error('Invalid input: '.collect($e->errors())->flatten()->first()));
        }

        return Response::structured(['reminder' => [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'project_id' => $reminder->list_id,
            'status' => $reminder->status,
            'created_at' => $reminder->created_at?->toIso8601String(),
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Short title of the reminder, max 200 chars.'),
            'project_id' => $schema->integer()->description('Target project id. Get it from list-projects.'),
            'project_name' => $schema->string()->description('Case-insensitive project name (alternative to project_id).'),
            'notes' => $schema->string()->description('Optional markdown notes.'),
            'soft_due_date' => $schema->string()->description('Optional date, YYYY-MM-DD. Sort key only, no notifications.'),
            'context' => $schema->object()->description('Optional coding context: {repo, branch, file, line_start, line_end, cwd}.'),
        ];
    }
}
