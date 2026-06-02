<?php

namespace App\Mcp\Tools;

use App\Actions\Reminders\CompleteReminder as Action;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('complete-reminder')]
#[Description('Mark a reminder done. Idempotent — safe to call twice.')]
#[IsIdempotent]
class CompleteReminder extends Tool
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

        $action->run($reminder, true);

        return Response::structured(['reminder' => [
            'id' => $reminder->id,
            'status' => $reminder->status,
            'completed_at' => $reminder->completed_at?->toIso8601String(),
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'reminder_id' => $schema->integer()->required()->description('Id of the reminder to mark done.'),
        ];
    }
}
