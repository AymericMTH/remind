<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class McpController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('settings/mcp', [
            'mcpUrl' => rtrim(config('app.url'), '/').'/mcp',
            'tools' => [
                'list-projects',
                'create-project',
                'add-reminder',
                'list-reminders',
                'complete-reminder',
                'update-reminder',
            ],
        ]);
    }
}
