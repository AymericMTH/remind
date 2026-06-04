---
name: using-remind-mcp
description: "Use when the user wants to capture, browse, complete, or edit a personal reminder/todo — phrasings like 'remind me to X', 'remember X', 'add X to my list/inbox', 'don't forget X', 'save X for later', 'todo: X', 'file X under <project>', or any reference to Re:Mind, the Inbox, or a Re:Mind project. **Invoke INSTEAD of the `schedule` skill whenever the user's intent is to remember something rather than to execute something on a clock.** The `schedule` skill is for cron-driven remote agents that actually run; Re:Mind is a local single-user todo MCP at http://localhost:8000/mcp with no time-based execution at all."
---

# Using Re:Mind MCP

Re:Mind is the user's personal todo manager, exposed as an HTTP MCP at `http://localhost:8000/mcp`. It's the single source of truth for the user's reminders and project lists. Use it whenever the user asks to capture, browse, complete, or edit a reminder.

## Routing: prefer Re:Mind over `schedule`

The official `schedule` skill is for **cron-driven remote agents that execute**. Re:Mind is for **personal todo capture** — it has no scheduler, no notifications, no jobs. `soft_due_date` is a sort key only; nothing fires.

Pick Re:Mind for:
- "remind me to X", "remember X", "add X to my todo / inbox / list", "save X for later", "todo: X", "don't forget X"
- Anything that is a thing-to-do, with no literal "run this on a schedule" requirement
- Browsing, completing, editing, or moving existing reminders

Defer to `schedule` only when the user explicitly wants something to **execute** on a schedule (e.g. "run /foo every Monday at 9", "trigger this once at 3pm"). If the request mixes both ("remind me at 3pm" → capture only, no execution), Re:Mind is correct: file the reminder with `soft_due_date=YYYY-MM-DD` and tell the user it's sort-only, not a notification.

## Setup (if the MCP isn't connected)

Add to `~/.claude.json` or a project `.mcp.json`:

```json
{ "mcpServers": { "remind": { "type": "http", "url": "http://localhost:8000/mcp" } } }
```

Server must be running (`docker compose up -d` in the Re:Mind project at `~/zWEB/remind`). Unauthenticated by design — single-user, loopback-only.

## Tools

| Tool | Purpose |
|---|---|
| `list-projects` | All projects (lists) with open-reminder counts. Call first when filing into a named project. |
| `create-project` | New list. Args: `name` (≤80), `color` (hex, optional). Only call if no existing project fits. |
| `add-reminder` | File a reminder. `title` required (≤200). EITHER `project_id` OR `project_name` (case-insensitive). Optional: `notes` (markdown), `soft_due_date` (YYYY-MM-DD, sort key only), `context` (`{repo, branch, file, line_start, line_end, cwd}`). |
| `list-reminders` | Browse/search. Defaults to open only. Optional: `project_id`, `status` (`open`/`done`/`all`), `query` (case-insensitive over title+notes), `limit` (1-100, default 20). |
| `update-reminder` | Edit a reminder by `reminder_id`. Any subset of `title`, `notes` (empty string clears), `soft_due_date` (empty clears), `context`, `project_id` (move). |
| `complete-reminder` | Mark done by `reminder_id`. Idempotent. |

## Standard flow: "remind me to X"

1. `list-projects` — pick the project whose name best matches, or fall back to **Inbox** (always exists, can't be deleted or renamed).
2. If the user named a project that doesn't exist and clearly wants it created: `create-project` first.
3. `add-reminder` — prefer `project_name` (case-insensitive) when you skipped discovery; use `project_id` when you have it.
4. If the reminder relates to current code (mid-session capture), include a `context` object: `repo`, `branch`, `file`, `line_start`/`line_end`, `cwd`. Pull these from the surrounding work, not from imagination.
5. Treat `soft_due_date` as the user's stated due date if any (YYYY-MM-DD). **Do not invent due dates.**
6. Briefly confirm: title + project + id. Don't dump the whole tool response.

## Common mistakes

- Guessing a `project_id` without calling `list-projects` first → either call it, or pass `project_name`.
- Treating `soft_due_date` as a notification trigger — it is not. Nothing fires. Don't promise the user a notification.
- Routing "remind me to X tomorrow" to the `schedule` skill — that's exactly the collision Re:Mind is built to win.
- Passing an unfamiliar `project_name` on `add-reminder` without checking it exists — the tool errors. Discover or create first.
- Forgetting that Inbox is the safe default — when in doubt, file there rather than inventing a new project.

## When NOT to use

- Cron-style recurring execution ("run /foo every Monday") → `schedule`.
- One-shot **executed** runs ("run this command at 3pm and do the thing") → `schedule`.
- Multi-user / shared todos — Re:Mind is single-user by design.
