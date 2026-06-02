# Re:Mind — Architecture & Specification

> Status: design v1 (brainstorm, 2026-06-01). Source of truth for the initial build. Update this file whenever a top-level decision changes.

## 1. What Re:Mind is

A personal todo manager that holds **both** general life reminders **and** coding-session follow-ups in one place, organized by project. Two entry points:

- **Dashboard** — a fast two-pane web UI for triage and review.
- **Claude Code MCP** — a local HTTP MCP server so reminders can be captured mid-coding-session without leaving the editor, with optional structured coding context (repo, branch, file, line) attached.

The branded name is **Re:Mind** (the `:` is a typographic accent).

## 2. Constraints and explicit non-goals

**Constraints**

- Single-user instance for now. The data model is user-scoped from day one so the app can become multi-user later without a rewrite, but registration is disabled.
- Runs locally in **Docker on WSL2**. No external services.
- **The MCP endpoint is unauthenticated by deliberate choice**, even if the host is exposed beyond localhost. The MCP simply operates as the single registered user. **⚠ Security implication:** if the Re:Mind container is ever reachable from any network other than your trusted machine, anyone who can reach `/mcp` can read and write every reminder. Bind to loopback or put the container behind a private network. This constraint is owner-acknowledged; revisit it before moving toward multi-user or hosted deployments.

**Non-goals (YAGNI — explicitly deferred)**

- No notifications, no scheduling, no jobs/queues, no recurring tasks.
- No priorities. No sharing/collaboration. No mobile app.
- No multi-user UI today. No remote hosting / Laravel Cloud today.
- No backwards-compat shims for hypothetical future features.

## 3. High-level architecture

```
┌───────────────────────────────────────────────────────────────┐
│  Docker container: remind                                     │
│                                                               │
│   Laravel 13 app                                              │
│   ├── Inertia React dashboard       (/*  HTTP)                │
│   ├── REST/Inertia controllers      (/lists, /reminders, …)   │
│   ├── HTTP MCP endpoint             (/mcp via laravel/mcp)    │
│   └── SQLite database               (storage/database.sqlite) │
└───────────────────────────────────────────────────────────────┘
         ▲                                  ▲
         │ browser                          │ Claude Code (HTTP MCP)
         │ (http://localhost:8000)          │ (http://localhost:8000/mcp)
```

Single codebase, single container. Dashboard and MCP share models, policies, validation, and the SQLite database. Nothing in the design relies on SQLite specifically; switching to Postgres later is a config change.

## 4. Data model

### 4.1 Tables

```
users                    (from Fortify scaffolding, unchanged)
  id, name, email, password, …

lists
  id              bigint PK
  user_id         FK → users.id, cascadeOnDelete, indexed
  name            string (required, ≤ 80)
  color           string (hex like '#7aa2f7', nullable; UI uses a default if null)
  position        integer (sort order in the sidebar; default = max(position)+1)
  is_inbox        boolean (default false; exactly one row per user has true)
  created_at, updated_at
  UNIQUE (user_id, lower(name))          // uniqueness enforced case-insensitively

reminders
  id              bigint PK
  user_id         FK → users.id, cascadeOnDelete, indexed
  list_id         FK → lists.id, restrictOnDelete                 // application layer handles cascade; see §7
  title           string (required, ≤ 200)
  notes           text (nullable, markdown source)
  soft_due_date   date (nullable, no time, no TZ)
  context         json (nullable, see §4.3)
  status          string (enum-cast: 'open' | 'done', default 'open', indexed)
  completed_at    timestamp (nullable, set when status flips to 'done')
  position        integer (sort order within list)
  created_at, updated_at, deleted_at         // soft delete
```

> The PHP model class is named **`ReminderList`** because `List` is a PHP reserved word. The public MCP surface and the UI both call it a **project** / **list** depending on audience (see §6 and §5).

### 4.2 Invariants

- A user always has exactly one `is_inbox = true` list, auto-created on user creation and re-created in a Fortify hook if missing.
- A reminder's `list.user_id` must equal its own `user_id`. Enforced in the `StoreReminder` form request and a `Reminder` model boot hook.
- Every controller and every MCP tool scopes its queries by `auth()->id()` (or the single resolved user for MCP). No cross-user access path exists.
- Inbox cannot be renamed or deleted.

### 4.3 `context` JSON shape

All keys are optional and untyped beyond what's listed. The MCP fills as many as it can from the calling shell's environment; the dashboard lets you edit them by hand.

```json
{
  "repo":       "git@github.com:foo/bar.git" | "/abs/local/path",
  "repo_label": "foo/bar",
  "branch":     "feature/x",
  "file":       "app/Models/ReminderList.php",
  "line_start": 24,
  "line_end":   31,
  "cwd":        "/home/aymeric/zWEB/remind"
}
```

`repo_label` is computed server-side from `repo` so the dashboard chip stays short. Unknown repo formats fall back to the basename of `cwd`.

## 5. Dashboard UX — two-pane

```
┌────────────────┬──────────────────────────────────────────────┐
│ SIDEBAR        │ MAIN PANE                                    │
│ (240px)        │                                              │
│                │  📋 Re:Mind dev                              │
│  Re:Mind       │  ┌─ + Add a reminder… ─────────────────┐    │
│                │  └──────────────────────────────────────┘    │
│  📥 Inbox  12  │                                              │
│  🟢 Re:Mind 4◀ │  ☐  Refactor MCP tool registration           │
│  🔵 Work    7  │     📁 app/Mcp/Tools.php:42 · due Thu        │
│  🟣 Personal 3 │  ────────────────────────────────────────── │
│                │  ☐  Add list color picker                    │
│  + New list    │  ────────────────────────────────────────── │
│                │  ☐  Write MCP auth docs                      │
│  ─────────     │  ────────────────────────────────────────── │
│  Completed →   │                                              │
│  Settings      │  ▸ Show 3 completed                          │
└────────────────┴──────────────────────────────────────────────┘
```

### 5.1 Sidebar

- Ordered list of the user's lists with an open-count badge.
- Inbox is pinned first and always visible.
- Click selects a list (Inertia partial visit, no full reload).
- Drag to reorder (`position`).
- Right-click → rename, change color, delete.
- Below the list section: a special **Completed** view that shows all `status = 'done'` reminders across lists, newest first.
- Below that: **Settings** (auth + MCP setup snippets).

### 5.2 Main pane

- Header: list name, color dot, open-count.
- Always-visible **add row** at top: `+ Add a reminder…` input. `n` focuses it; `Enter` saves and keeps the cursor in the row for chained adds; `Esc` exits.
- Reminder rows: checkbox · title · muted chip line for coding-context and due-date · caret.
- Click a row → it expands **inline below** into an editor card:
  - Title (inline editable).
  - Notes — markdown textarea with a preview toggle; rendered server-side via `league/commonmark` in safe mode (see §10).
  - Soft due date picker (clearable).
  - Coding-context fields (repo / branch / file / line_start / line_end).
  - Delete button (soft delete).
- Completing an item: checkbox click sets `status = 'done'`, `completed_at = now()`. Row fades into the **Show N completed** collapsible footer at the bottom of the list. Click checkbox again to undo.
- Drag rows to reorder within a list (writes `position`).

### 5.3 Keyboard

- `n` — new reminder (focus the add row).
- `↑/↓` — move cursor between rows.
- `Enter` — open the focused row's editor.
- `Space` — toggle done on the focused row.
- `e` — rename in place.
- `Delete` — delete with confirm.
- `g i` / `g 1` / `g 2` — Things-style chord to jump to Inbox / list 1 / list 2 (positions in the sidebar).

### 5.4 Empty / zero states

- Empty list: "No open reminders here yet — press `n` to add one."
- Empty Completed: "Nothing done yet. Reminders you complete will appear here."
- Trash view (§7): "Deleted reminders show here for 30 days, then are purged."

## 6. MCP server

### 6.1 Transport and authentication

- HTTP MCP served from the same Laravel app at `/mcp`, implemented with the **`laravel/mcp`** package.
- **The MCP endpoint has no authentication** — no tokens, no headers, no session. Every MCP request implicitly acts as the single registered user, resolved via `User::sole()` (which fails loud if the app somehow ends up with 0 or 2+ users — surfaces account misconfiguration immediately rather than silently picking one).
- The route is mounted in `routes/mcp.php` with a middleware that resolves the user via `User::sole()` and binds it into the request for tool handlers to use.
- **The dashboard side is still authenticated** via Fortify (login session). Only `/mcp` is open. Registration is disabled, so practically only the one bootstrapped user can sign in to the dashboard.

### 6.2 Tool surface (v1)

Kept deliberately small. The MCP exposes what Claude needs to capture and triage, not the full CRUD.

| Tool | Purpose | Required args | Optional args |
|---|---|---|---|
| `list_projects` | Return all of my lists with open counts so Claude can pick where to file new items. | — | — |
| `create_project` | Create a new list (when no existing list fits). | `name` | `color` |
| `add_reminder` | File a new reminder. | `title`, **either** `project_id` **or** `project_name` (case-insensitive lookup; **errors if name doesn't match** — Claude must call `create_project` first if no list fits) | `notes`, `soft_due_date`, `context` (repo / branch / file / line_start / line_end / cwd) |
| `list_reminders` | Search or browse. | — | `project_id`, `status` (default `open`), `query`, `limit` (default 20) |
| `complete_reminder` | Mark a reminder done. | `reminder_id` | — |
| `update_reminder` | Edit a reminder's fields. | `reminder_id` | `title`, `notes`, `soft_due_date`, `context`, `project_id` |

Public-API naming: tools call it **`project`** (since lists are projects in this product). The model class stays `ReminderList`.

### 6.3 Capture flow (worked example)

1. In Claude Code: "remind me to clean up the MCP route binding before merging."
2. Claude calls `list_projects` and sees `Re:Mind dev` with its open count. Picks it (or asks the user if multiple plausibly fit).
3. Claude calls `add_reminder(project_id, title, context = { repo, branch, file, cwd })` filled from the current shell environment.
4. Server validates, runs `Actions/Reminders/NormalizeContext` to derive `repo_label`, stores the row, returns a small summary including the new reminder's id and a URL to it on the dashboard.

### 6.4 Discovery

Settings → MCP prints the exact JSON snippet for `~/.claude.json` or a project `.mcp.json`, e.g.:

```json
{
  "mcpServers": {
    "remind": { "type": "http", "url": "http://localhost:8000/mcp" }
  }
}
```

## 7. Mutations and edge cases

- **Delete a reminder** — soft-delete (`deleted_at`). It appears in a hidden trash view. Auto-purged after 30 days by a daily artisan command (`reminders:purge-trash`).
- **Delete a list** — blocked if the list contains reminders. The UI offers two paths: "Move N reminders to Inbox" (re-parent), or "Delete list and all reminders" (the action class hard-deletes the reminders first, then the list — bypassing soft-delete because the user explicitly confirmed cascade). The DB-level FK is `restrictOnDelete` as defense-in-depth so an accidental list-delete without going through the action class fails loudly. Inbox cannot be deleted or renamed.
- **Rename a list** — free, but must remain unique per user (case-insensitive).
- **Reorder** — both lists (sidebar) and reminders (within a list) are reorderable via drag. The server-side write is a single update setting `position` on the moved row using fractional indexing or `position = (prev + next) / 2`; periodic resequence not required at this scale.
- **No clock-dependent business logic** in v1 — `soft_due_date` is purely a sort key and a visual badge ("Today", "Overdue"). Nothing fires.

## 8. Tech stack and Laravel-specific decisions

- **Laravel 13**, PHP 8.4, **Inertia v3 + React 19**, Tailwind v4. (All present from the React starter kit.)
- **Auth** — Fortify, already wired. Registration disabled. Login screen retained. Passkeys retained but not advertised in v1.
- **Routing on the frontend** — **Wayfinder**: every frontend call uses generated typed helpers from `@/actions` and `@/routes`. No hardcoded URLs in TSX.
- **Database** — SQLite at `database/database.sqlite`. Migrations only — no raw SQL. The composer `setup` script runs `php artisan migrate --force`.
- **MCP** — `laravel/mcp` (HTTP transport). Tool classes are thin wrappers around Action classes in `app/Actions/` so the MCP package can move without touching business logic.
- **Markdown** — server-rendered with `league/commonmark` in safe mode (no inline HTML, no raw URLs from disallowed schemes), output further sanitized before being shipped to the client. The client never parses markdown.
- **Docker** — a single `docker-compose.yml` with one **FrankenPHP** `app` service. Bind-mounts the project. No DB container — SQLite lives in the mounted volume. Ports: `8000:8000`. (Falls back to `nginx + php-fpm` if FrankenPHP causes issues; both are documented options.)

## 9. Code organization

```
app/
├── Actions/                            // single-purpose action classes
│   ├── Lists/CreateList.php
│   ├── Lists/DeleteList.php            // takes MoveToInbox|Cascade strategy
│   ├── Reminders/CreateReminder.php
│   ├── Reminders/UpdateReminder.php
│   ├── Reminders/CompleteReminder.php
│   └── Reminders/NormalizeContext.php
├── Http/
│   ├── Controllers/
│   │   ├── ListController.php          // index/show/store/update/destroy (Inertia)
│   │   └── ReminderController.php
│   └── Requests/                       // FormRequest per write action
├── Models/
│   ├── User.php
│   ├── ReminderList.php
│   └── Reminder.php
├── Mcp/
│   ├── McpServiceProvider.php
│   └── Tools/
│       ├── ListProjects.php
│       ├── CreateProject.php
│       ├── AddReminder.php
│       ├── ListReminders.php
│       ├── CompleteReminder.php
│       └── UpdateReminder.php
├── Policies/
│   ├── ReminderListPolicy.php
│   └── ReminderPolicy.php
└── Console/
    └── Commands/PurgeTrash.php         // daily artisan: purge soft-deleted > 30d

resources/js/pages/
├── dashboard/Index.tsx                 // sidebar + main pane (the whole app)
├── dashboard/Trash.tsx                 // optional in v1
├── settings/Mcp.tsx                    // MCP setup snippet
└── auth/…                              // existing

routes/
├── web.php       — dashboard + settings + auth
└── mcp.php       — laravel/mcp routes, prefixed /mcp, no auth middleware
```

## 10. Security notes

- Notes rendered via `league/commonmark` **safe mode** + an explicit allowlist on output. No `dangerouslySetInnerHTML` from user-supplied markdown.
- All write endpoints validated via `FormRequest`. All read endpoints policy-checked.
- All Eloquent queries scope by `user_id`. Tests assert cross-user isolation explicitly (see §11).
- **MCP has no auth by design.** The container must be bound to loopback. This is the single biggest operational risk; see §2.

## 11. Testing strategy

- **PHPUnit** (per Boost rules); feature tests are the default.
- Per controller action: happy path, validation failure, auth/policy failure, cross-user isolation (user B cannot see user A's data via any path).
- Per MCP tool: happy path, validation failure, the `User::sole()` resolution (asserts behavior when 0 users or 2+ users exist).
- Integration test for the daily soft-delete purge.
- Frontend: `tsc --noEmit`, ESLint, Prettier. No JS unit tests in v1 — logic lives in Laravel.

## 12. Risks and revisit triggers

- **`laravel/mcp` is v0** — surface may change. We isolate tool logic in Action classes so a package rewrite doesn't touch business logic.
- **Soft-delete on reminders, hard-delete on lists** — asymmetric on purpose: reminders are content, lists are containers.
- **No auth on MCP** — revisit if/when the host stops being a single-user trusted machine. Search for "no authentication" in this file to find the load-bearing decision.
- **SQLite** — fine at the scale of "one user, thousands of reminders". Revisit if the app grows to multiple users with concurrent writes.

---

_Last reviewed: 2026-06-01._
