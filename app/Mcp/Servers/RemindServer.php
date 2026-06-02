<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\ListProjects;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Tool;

#[Name('Re:Mind')]
#[Version('1.0.0')]
#[Instructions('Capture, browse, and complete reminders organized into projects (lists).')]
class RemindServer extends Server
{
    /** @var array<int, class-string<Tool>> */
    protected array $tools = [
        ListProjects::class,
        CreateProject::class,
    ];
}
