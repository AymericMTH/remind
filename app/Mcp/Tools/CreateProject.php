<?php

namespace App\Mcp\Tools;

use App\Actions\Lists\CreateList;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-project')]
#[Description('Create a new Re:Mind project (list) for filing reminders. Call this before add-reminder when no existing project fits.')]
class CreateProject extends Tool
{
    public function handle(Request $request, CreateList $action): ResponseFactory
    {
        try {
            $list = $action->run($request->user(), [
                'name' => $request->get('name'),
                'color' => $request->get('color'),
            ]);
        } catch (ValidationException $e) {
            return Response::make(Response::error('Cannot create project: '.collect($e->errors())->flatten()->first()));
        }

        return Response::structured(['project' => [
            'id' => $list->id,
            'name' => $list->name,
            'color' => $list->color,
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required()->description('Display name, max 80 chars.'),
            'color' => $schema->string()->description('Hex color like #7aa2f7.'),
        ];
    }
}
