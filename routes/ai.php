<?php

use App\Mcp\Middleware\SingleUserMiddleware;
use App\Mcp\Servers\RemindServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', RemindServer::class)->middleware(SingleUserMiddleware::class);

// Override laravel/mcp's stock GET handler (405 Allow: POST) with a human-readable quick guide
// so anyone hitting http://localhost:8000/mcp in a browser sees how to wire it up instead of an error.
Route::get('/mcp', function () {
    $body = <<<'MD'
# Re:Mind MCP — Quick guide

This endpoint speaks **JSON-RPC 2.0 over HTTP `POST`**. There is no browser UI here — the dashboard lives at <http://localhost:8000/>.

## Connect

From a terminal, register Re:Mind with the Claude Code CLI:

```sh
claude mcp add --scope user --transport http remind http://localhost:8000/mcp
```

Or add it by hand to `~/.claude.json` (or a project `.mcp.json`), then restart your client:

```json
{ "mcpServers": { "remind": { "type": "http", "url": "http://localhost:8000/mcp" } } }
```

Unauthenticated by design — single-user, loopback-only. **Do not expose this port to a network.**

## Tools

| Tool | Purpose |
|---|---|
| `list-projects` | All lists with open-reminder counts. |
| `create-project` | New list. Args: `name`, optional `color` (hex). |
| `add-reminder` | File a reminder. Args: `title`, `project_id` OR `project_name`, optional `notes` / `soft_due_date` (YYYY-MM-DD, sort-only) / `context` (`{repo, branch, file, line_start, line_end, cwd}`). |
| `list-reminders` | Browse/search. Optional `project_id`, `status` (`open`/`done`/`all`), `query`, `limit`. |
| `update-reminder` | Edit by `reminder_id`. Any subset of `title`/`notes`/`soft_due_date`/`context`/`project_id`. |
| `complete-reminder` | Mark done. Idempotent. |

## Notes

- `soft_due_date` is a sort key. **Nothing fires** — no notifications, no jobs.
- `Inbox` always exists; safe default for unfiled reminders.
- Dashboard: <http://localhost:8000/dashboard>.
MD;

    return response($body, 200, ['Content-Type' => 'text/markdown; charset=UTF-8']);
});
