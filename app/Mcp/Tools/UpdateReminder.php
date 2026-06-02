<?php

namespace App\Mcp\Tools;

use App\Actions\Reminders\UpdateReminder as Action;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-reminder')]
#[Description('Edit fields on an existing reminder. Any subset of fields is accepted.')]
class UpdateReminder extends Tool
{
    public function handle(Request $request, Action $action): ResponseFactory
    {
        $reminder = Reminder::query()
            ->where('id', (int) $request->get('reminder_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $reminder) {
            return Response::make(Response::error('Reminder not found.'));
        }

        $attrs = array_filter([
            'title' => $request->get('title'),
            'notes' => $request->get('notes'),
            'soft_due_date' => $request->get('soft_due_date'),
            'context' => $request->get('context'),
            'list_id' => $request->get('project_id'),
        ], fn ($v) => $v !== null);

        try {
            $action->run($reminder, $attrs);
        } catch (ValidationException $e) {
            return Response::make(Response::error('Invalid input: '.collect($e->errors())->flatten()->first()));
        }

        return Response::structured(['reminder' => [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'project_id' => $reminder->list_id,
            'status' => $reminder->status,
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reminder_id' => $schema->integer()->required(),
            'title' => $schema->string()->description('New title, max 200 chars.'),
            'notes' => $schema->string()->description('New markdown notes; pass empty string to clear.'),
            'soft_due_date' => $schema->string()->description('YYYY-MM-DD or empty to clear.'),
            'context' => $schema->object()->description('New context object; merged on the client, replaced on the server.'),
            'project_id' => $schema->integer()->description('Move to another project.'),
        ];
    }
}
