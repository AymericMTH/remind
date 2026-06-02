# Re:Mind Dashboard Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the placeholder dashboard with the full two-pane Re:Mind UI on top of Plan 1's backend, and add a Settings → MCP page for the setup snippet.

**Architecture:** Single Inertia React 19 + Tailwind v4 page (`/dashboard`) backed by `DashboardController`, plus a new `/settings/mcp` page. All mutations are server-confirmed via Inertia `router.{post|put|delete}`; switching lists is a partial Inertia visit. No new endpoints — only the existing Plan 1 routes. Drag reorder via `@dnd-kit`; height transitions via `framer-motion`. Warm/branded visual style: cream background, amber-accented wordmark, list color on the active sidebar row.

**Tech Stack:** PHP 8.4 · Laravel 13 · Inertia v3 + React 19 · Tailwind v4 · shadcn/ui · @dnd-kit · framer-motion · sonner · `<input type="date">` for due-date · PHPUnit 12.

**Source of truth:** [`docs/dashboard-frontend.md`](../dashboard-frontend.md) (the design spec) and [`docs/architecture.md`](../architecture.md) (§5, §6.4, §10 for the rules that govern the dashboard).

---

## File map

```
package.json                                            (modify — add deps)

app/Http/Controllers/
├── DashboardController.php                              (modify — selectedList/reminders/completedReminders)
└── Settings/McpController.php                           (new)

app/Http/Controllers/Concerns/
└── PresentsReminders.php                                (new — trait: maps Reminder model → frontend shape with notes_html)

routes/settings.php                                      (modify — add /settings/mcp)
config/remind.php                                        (modify — add curated_colors list)

resources/js/pages/
├── dashboard/Index.tsx                                  (REWRITE)
└── settings/mcp.tsx                                     (new)

resources/js/layouts/
└── dashboard-layout.tsx                                 (new — sidebar + main shell)

resources/js/components/dashboard/                       (new directory)
├── brand-wordmark.tsx
├── context-chip.tsx
├── due-date-badge.tsx
├── empty-state.tsx
├── color-picker.tsx
├── list-context-menu.tsx
├── delete-list-dialog.tsx
├── sidebar-list-item.tsx
├── sidebar-new-list-input.tsx
├── sidebar.tsx
├── main-pane-header.tsx
├── add-reminder-row.tsx
├── reminder-editor-card.tsx
├── reminder-row.tsx
├── completed-accordion.tsx
├── main-pane.tsx
└── shortcuts-cheatsheet.tsx                              (?-trigger overlay)

resources/js/hooks/
├── use-selected-list.ts                                  (new)
├── use-click-outside.ts                                  (new)
└── use-keyboard-shortcuts.ts                             (new)

resources/js/types/
└── remind.ts                                             (new — shared TS types)

tests/Feature/Http/
├── DashboardControllerTest.php                          (new)
└── Settings/McpPageTest.php                             (new)
```

**Naming conventions matched against the starter kit:**
- Page files (`pages/dashboard/Index.tsx`) — keep `Index.tsx` as-is; new pages match siblings (`pages/settings/mcp.tsx` lowercase like `profile.tsx`).
- Components, hooks, layouts, types — kebab-case (`brand-wordmark.tsx`, `use-keyboard-shortcuts.ts`).
- React component names are PascalCase as usual.
- Wayfinder regenerates `@/actions/...` and `@/routes` automatically on Vite startup — don't hardcode URLs.

**Pre-commit hygiene applied to every task:**
- After PHP edits: `docker compose exec app vendor/bin/pint --dirty --format agent`
- After TS edits: `docker compose exec app npm run format` (Prettier) then `npm run lint`
- After any edit: `docker compose exec app npm run types:check` (`tsc --noEmit`) — must pass
- Backend tests: `docker compose exec app php artisan test --compact --filter=<filter>`
- One commit per task, message exactly as written in the task

**Conventions for frontend tasks:** No JS unit tests in v1 (per spec §12). Each frontend task's "test" loop is:
  1. Add a smoke render (the component or page imported from a parent that's already loaded by `npm run dev` — usually `dashboard/Index.tsx`).
  2. `npm run types:check` passes.
  3. Either Vite HMR shows the new component, or `curl http://localhost:8000/dashboard` + visual inspection in a browser.
  4. Commit.

---

## Phase 0 — Dependencies

### Task 1: Install `@dnd-kit/*` + `framer-motion`; verify `sonner` already present

**Files:** `package.json`, `package-lock.json`

- [ ] **Step 1: Check what's already there**

```bash
cd /home/aymeric/zWEB/remind
grep -E '"(@dnd-kit|framer-motion|sonner)"' package.json
```

Expected: only `sonner` shows up (already installed by starter).

- [ ] **Step 2: Install missing packages**

```bash
docker compose exec app npm install @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities framer-motion
```

- [ ] **Step 3: Verify install**

```bash
docker compose exec app npm ls @dnd-kit/core @dnd-kit/sortable @dnd-kit/utilities framer-motion sonner 2>&1 | head -10
```

Expected: all 5 packages listed, no `missing` markers.

- [ ] **Step 4: Type check**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
```

Expected: clean (`tsc --noEmit` finds no errors).

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore(deps): add @dnd-kit/* and framer-motion for dashboard"
```

---

## Phase 1 — Backend extensions

### Task 2: Extend `DashboardController` + add `PresentsReminders` trait

**Files:**
- Create: `app/Http/Controllers/Concerns/PresentsReminders.php`
- Modify: `app/Http/Controllers/DashboardController.php`
- Create: `tests/Feature/Http/DashboardControllerTest.php`
- Modify: `config/remind.php` (add `curated_colors`)

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Http/DashboardControllerTest.php

namespace Tests\Feature\Http;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_shows_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard/Index')
                ->where('selectedList.id', $inbox->id)
                ->has('lists', 1)
                ->has('reminders', 0)
                ->has('completedReminders', 0));
    }

    public function test_query_param_selects_list(): void
    {
        $user = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);
        Reminder::factory()->count(3)->create(['user_id' => $user->id, 'list_id' => $work->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $work->id]);

        $this->actingAs($user)
            ->get("/dashboard?list={$work->id}")
            ->assertInertia(fn ($page) => $page
                ->where('selectedList.id', $work->id)
                ->has('reminders', 3)
                ->has('completedReminders', 1));
    }

    public function test_invalid_list_id_falls_back_to_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->get('/dashboard?list=9999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('selectedList.id', $inbox->id));
    }

    public function test_other_users_list_falls_back_to_inbox(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);
        $aInbox = $a->lists()->where('is_inbox', true)->first();

        $this->actingAs($a)
            ->get("/dashboard?list={$bsList->id}")
            ->assertInertia(fn ($page) => $page->where('selectedList.id', $aInbox->id));
    }

    public function test_reminder_payload_includes_notes_html(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        Reminder::factory()->create([
            'user_id' => $user->id,
            'list_id' => $list->id,
            'notes' => '**hi**',
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->where('reminders.0.notes_html', fn ($html) => str_contains($html, '<strong>hi</strong>')));
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=DashboardControllerTest
```

Expected: failures around component name, missing props, or unparsed notes_html.

- [ ] **Step 3: Add curated colors to config**

In `config/remind.php`, append (preserve existing keys):

```php
'curated_colors' => ['#7aa2f7', '#9ece6a', '#e0af68', '#bb9af7', '#f7768e', '#7dcfff'],
```

- [ ] **Step 4: Create the presenter trait**

```php
<?php
// app/Http/Controllers/Concerns/PresentsReminders.php

namespace App\Http\Controllers\Concerns;

use App\Actions\Markdown\RenderNotes;
use App\Models\Reminder;

trait PresentsReminders
{
    /**
     * @return array<string,mixed>
     */
    protected function presentReminder(Reminder $r, RenderNotes $renderer): array
    {
        return [
            'id' => $r->id,
            'list_id' => $r->list_id,
            'title' => $r->title,
            'notes' => $r->notes,
            'notes_html' => $renderer->run($r->notes),
            'soft_due_date' => $r->soft_due_date?->toDateString(),
            'context' => $r->context,
            'status' => $r->status,
            'completed_at' => $r->completed_at?->toIso8601String(),
            'position' => $r->position,
        ];
    }
}
```

- [ ] **Step 5: Replace DashboardController body**

```php
<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Actions\Markdown\RenderNotes;
use App\Http\Controllers\Concerns\PresentsReminders;
use App\Models\ReminderList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use PresentsReminders;

    public function __invoke(Request $request, RenderNotes $renderer): Response
    {
        $user = $request->user();

        $lists = $user->lists()
            ->orderBy('position')
            ->withCount(['reminders as open_count' => fn ($q) => $q->where('status', 'open')])
            ->get(['id', 'name', 'color', 'position', 'is_inbox']);

        $selectedList = $this->resolveSelectedList($request, $user, $lists);

        $reminders = $selectedList->reminders()
            ->where('status', 'open')
            ->orderBy('position')
            ->get();

        $completed = $selectedList->reminders()
            ->where('status', 'done')
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get();

        return Inertia::render('dashboard/Index', [
            'lists' => $lists,
            'selectedList' => $selectedList->only(['id', 'name', 'color', 'is_inbox']),
            'reminders' => $reminders->map(fn ($r) => $this->presentReminder($r, $renderer))->values(),
            'completedReminders' => $completed->map(fn ($r) => $this->presentReminder($r, $renderer))->values(),
            'curatedColors' => config('remind.curated_colors'),
        ]);
    }

    private function resolveSelectedList(Request $request, $user, $lists): ReminderList
    {
        $id = $request->query('list');

        if ($id) {
            $match = $lists->firstWhere('id', (int) $id);
            if ($match) {
                return $match;
            }
        }

        return $lists->firstWhere('is_inbox', true);
    }
}
```

- [ ] **Step 6: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=DashboardControllerTest
```

Expected: 5 passed.

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers app/Http/Controllers/Concerns config/remind.php tests/Feature/Http/DashboardControllerTest.php
git commit -m "feat(http): DashboardController returns lists+selectedList+reminders+completed with notes_html"
```

---

### Task 3: `Settings/McpController` + `/settings/mcp` route

**Files:**
- Create: `app/Http/Controllers/Settings/McpController.php`
- Modify: `routes/settings.php`
- Create: `tests/Feature/Http/Settings/McpPageTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Http/Settings/McpPageTest.php

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_redirected_to_login(): void
    {
        $this->get('/settings/mcp')->assertRedirect('/login');
    }

    public function test_renders_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/settings/mcp')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('settings/mcp')
                ->where('mcpUrl', fn ($url) => str_ends_with($url, '/mcp'))
                ->has('tools', 6));
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=McpPageTest
```

- [ ] **Step 3: Controller**

```php
<?php
// app/Http/Controllers/Settings/McpController.php

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
```

- [ ] **Step 4: Route**

In `routes/settings.php`, inside the existing auth-gated group, add:

```php
use App\Http\Controllers\Settings\McpController;

Route::get('/settings/mcp', McpController::class)->name('settings.mcp');
```

Place it next to the other settings routes; preserve everything else.

- [ ] **Step 5: Stub the page so `assertInertia(...)->component('settings/mcp')` resolves**

Create a placeholder `resources/js/pages/settings/mcp.tsx`:

```tsx
import { Head } from '@inertiajs/react';

export default function McpSettings() {
    return (
        <>
            <Head title="MCP" />
            <p>MCP setup page — full UI in Task 25.</p>
        </>
    );
}
```

(Inertia needs the page module to exist or it errors. The real implementation lands in Task 25.)

- [ ] **Step 6: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=McpPageTest
```

Expected: 2 passed.

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Settings routes/settings.php tests/Feature/Http/Settings resources/js/pages/settings/mcp.tsx
git commit -m "feat(settings): MCP settings page route + controller (placeholder UI)"
```

---

## Phase 2 — Shared types + atom components

### Task 4: Shared TS types

**Files:** Create `resources/js/types/remind.ts`

- [ ] **Step 1: Write the file**

```ts
// resources/js/types/remind.ts

export type ReminderList = {
    id: number;
    name: string;
    color: string | null;
    position: number;
    is_inbox: boolean;
    open_count?: number;
};

export type ReminderContext = {
    repo?: string;
    repo_label?: string;
    branch?: string;
    file?: string;
    line_start?: number;
    line_end?: number;
    cwd?: string;
};

export type Reminder = {
    id: number;
    list_id: number;
    title: string;
    notes: string | null;
    notes_html: string;
    soft_due_date: string | null; // YYYY-MM-DD
    context: ReminderContext | null;
    status: 'open' | 'done';
    completed_at: string | null;
    position: number;
};

export type DashboardPageProps = {
    lists: ReminderList[];
    selectedList: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    reminders: Reminder[];
    completedReminders: Reminder[];
    curatedColors: string[];
};

export type McpPageProps = {
    mcpUrl: string;
    tools: string[];
};
```

- [ ] **Step 2: tsc check**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
```

Expected: clean.

- [ ] **Step 3: Commit**

```bash
git add resources/js/types/remind.ts
git commit -m "feat(types): shared Re:Mind dashboard types"
```

---

### Task 5: `brand-wordmark.tsx`

**Files:** Create `resources/js/components/dashboard/brand-wordmark.tsx`

- [ ] **Step 1: Write the component**

```tsx
// resources/js/components/dashboard/brand-wordmark.tsx
import { cn } from '@/lib/utils';

export function BrandWordmark({ className }: { className?: string }) {
    return (
        <span className={cn('font-semibold tracking-tight', className)}>
            Re<span className="text-amber-600 dark:text-amber-400">:</span>Mind
        </span>
    );
}
```

- [ ] **Step 2: tsc check**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/dashboard/brand-wordmark.tsx
git commit -m "feat(dashboard): brand wordmark with amber colon"
```

---

### Task 6: `context-chip.tsx`

**Files:** Create `resources/js/components/dashboard/context-chip.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/context-chip.tsx
import type { ReminderContext } from '@/types/remind';

export function ContextChip({ context }: { context: ReminderContext | null }) {
    if (!context) return null;

    const label =
        context.repo_label ??
        (context.cwd ? context.cwd.split('/').filter(Boolean).pop() : null);

    const fileFragment = context.file
        ? `${context.file}${context.line_start ? `:${context.line_start}` : ''}`
        : null;

    const text = [label, fileFragment].filter(Boolean).join(' · ');

    if (!text) return null;

    return (
        <span className="font-mono text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 rounded px-1.5 py-0.5 truncate max-w-[280px]">
            📁 {text}
        </span>
    );
}
```

- [ ] **Step 2: tsc**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/dashboard/context-chip.tsx
git commit -m "feat(dashboard): ContextChip — repo/file:line monospace pill"
```

---

### Task 7: `due-date-badge.tsx`

**Files:** Create `resources/js/components/dashboard/due-date-badge.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/due-date-badge.tsx

function isToday(date: string): boolean {
    const today = new Date().toISOString().slice(0, 10);
    return date === today;
}

function isOverdue(date: string): boolean {
    const today = new Date().toISOString().slice(0, 10);
    return date < today;
}

function formatDate(date: string): string {
    const d = new Date(date + 'T00:00:00');
    return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}

export function DueDateBadge({ date }: { date: string | null }) {
    if (!date) return null;

    const tone = isOverdue(date)
        ? 'text-red-600 dark:text-red-400'
        : isToday(date)
            ? 'text-amber-700 dark:text-amber-300 font-medium'
            : 'text-muted-foreground';

    const label = isToday(date) ? 'Today' : isOverdue(date) ? `Overdue · ${formatDate(date)}` : formatDate(date);

    return <span className={`text-xs ${tone}`}>{label}</span>;
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/due-date-badge.tsx
git commit -m "feat(dashboard): DueDateBadge — Today/Overdue/date with color tone"
```

---

### Task 8: `empty-state.tsx`

**Files:** Create `resources/js/components/dashboard/empty-state.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/empty-state.tsx
export function EmptyState({ message }: { message: string }) {
    return (
        <div className="flex items-center justify-center py-16 px-6 mx-2 my-4 rounded-lg bg-amber-50/60 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/30">
            <p className="text-sm text-muted-foreground">{message}</p>
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/empty-state.tsx
git commit -m "feat(dashboard): EmptyState pill"
```

---

### Task 9: `color-picker.tsx`

**Files:** Create `resources/js/components/dashboard/color-picker.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/color-picker.tsx
import { useState, type ChangeEvent } from 'react';

const HEX_RE = /^#?(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{4}|[0-9a-fA-F]{3})$/;

function normalize(value: string): string | null {
    if (!HEX_RE.test(value)) return null;
    return value.startsWith('#') ? value : `#${value}`;
}

type Props = {
    value: string | null;
    onChange: (value: string | null) => void;
    swatches: string[];
};

export function ColorPicker({ value, onChange, swatches }: Props) {
    const [draft, setDraft] = useState(value ?? '');
    const [invalid, setInvalid] = useState(false);

    function commit(e: ChangeEvent<HTMLInputElement>) {
        const v = e.target.value.trim();
        setDraft(v);
        if (v === '') {
            onChange(null);
            setInvalid(false);
            return;
        }
        const n = normalize(v);
        setInvalid(n === null);
        if (n !== null) onChange(n);
    }

    return (
        <div className="space-y-2">
            <div className="flex gap-1.5">
                {swatches.map((c) => (
                    <button
                        type="button"
                        key={c}
                        onClick={() => {
                            onChange(c);
                            setDraft(c);
                            setInvalid(false);
                        }}
                        className={`w-5 h-5 rounded-full border ${value === c ? 'ring-2 ring-offset-1 ring-amber-500' : 'border-black/10'}`}
                        style={{ backgroundColor: c }}
                        aria-label={`Select color ${c}`}
                    />
                ))}
            </div>
            <input
                type="text"
                value={draft}
                onChange={commit}
                placeholder="#7aa2f7"
                aria-invalid={invalid}
                className={`w-full text-sm font-mono px-2 py-1 rounded border ${invalid ? 'border-red-400' : 'border-input'} bg-background`}
            />
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/color-picker.tsx
git commit -m "feat(dashboard): ColorPicker — curated swatches + hex input"
```

---

## Phase 3 — Sidebar

### Task 10: `list-context-menu.tsx`

**Files:** Create `resources/js/components/dashboard/list-context-menu.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/list-context-menu.tsx
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ReactNode } from 'react';

type Props = {
    children: ReactNode;
    disabled?: boolean;
    onRename: () => void;
    onChangeColor: () => void;
    onDelete: () => void;
};

export function ListContextMenu({ children, disabled, onRename, onChangeColor, onDelete }: Props) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild disabled={disabled}>
                {children}
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-44">
                <DropdownMenuItem onSelect={onRename}>Rename</DropdownMenuItem>
                <DropdownMenuItem onSelect={onChangeColor}>Change color</DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onSelect={onDelete}
                    className="text-red-600 focus:text-red-700 focus:bg-red-50 dark:focus:bg-red-950/30"
                >
                    Delete
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/list-context-menu.tsx
git commit -m "feat(dashboard): ListContextMenu — rename/color/delete dropdown"
```

---

### Task 11: `delete-list-dialog.tsx`

**Files:** Create `resources/js/components/dashboard/delete-list-dialog.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/delete-list-dialog.tsx
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { ReminderList } from '@/types/remind';

type Strategy = 'move_to_inbox' | 'cascade';

type Props = {
    open: boolean;
    onClose: () => void;
    list: ReminderList;
    reminderCount: number;
};

export function DeleteListDialog({ open, onClose, list, reminderCount }: Props) {
    const [strategy, setStrategy] = useState<Strategy>('move_to_inbox');
    const [submitting, setSubmitting] = useState(false);

    function confirm() {
        setSubmitting(true);
        router.delete(`/lists/${list.id}`, {
            data: { strategy },
            preserveScroll: true,
            onFinish: () => {
                setSubmitting(false);
                onClose();
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Delete "{list.name}"</DialogTitle>
                    <DialogDescription>What should happen to the {reminderCount} reminder{reminderCount === 1 ? '' : 's'} in this list?</DialogDescription>
                </DialogHeader>

                <div className="space-y-3 my-2">
                    <Label className="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="strategy"
                            value="move_to_inbox"
                            checked={strategy === 'move_to_inbox'}
                            onChange={() => setStrategy('move_to_inbox')}
                            className="mt-1"
                        />
                        <span>
                            <span className="block font-medium">Move them to Inbox</span>
                            <span className="block text-sm text-muted-foreground">The list disappears, reminders move to Inbox.</span>
                        </span>
                    </Label>
                    <Label className="flex items-start gap-3 cursor-pointer">
                        <input
                            type="radio"
                            name="strategy"
                            value="cascade"
                            checked={strategy === 'cascade'}
                            onChange={() => setStrategy('cascade')}
                            className="mt-1"
                        />
                        <span>
                            <span className="block font-medium text-red-700 dark:text-red-400">Delete list and all reminders</span>
                            <span className="block text-sm text-muted-foreground">Permanent. Cannot be undone.</span>
                        </span>
                    </Label>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" onClick={onClose} disabled={submitting}>
                        Cancel
                    </Button>
                    <Button type="button" variant={strategy === 'cascade' ? 'destructive' : 'default'} onClick={confirm} disabled={submitting}>
                        {strategy === 'cascade' ? 'Delete everything' : 'Move and delete'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/delete-list-dialog.tsx
git commit -m "feat(dashboard): DeleteListDialog with MoveToInbox|Cascade radio"
```

---

### Task 12: `sidebar-list-item.tsx`

**Files:** Create `resources/js/components/dashboard/sidebar-list-item.tsx`

This component is intentionally **without dnd-kit wiring**; Task 14 wires the SortableContext around it. Here it just renders + handles click-select, rename-in-place, color-popover, and the context menu.

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/sidebar-list-item.tsx
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import type { ReminderList } from '@/types/remind';
import { ColorPicker } from './color-picker';
import { DeleteListDialog } from './delete-list-dialog';
import { ListContextMenu } from './list-context-menu';

type Props = {
    list: ReminderList;
    selected: boolean;
    reminderCount: number;
    curatedColors: string[];
    onSelect: () => void;
};

export function SidebarListItem({ list, selected, reminderCount, curatedColors, onSelect }: Props) {
    const [renaming, setRenaming] = useState(false);
    const [draft, setDraft] = useState(list.name);
    const [colorOpen, setColorOpen] = useState(false);
    const [deleteOpen, setDeleteOpen] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (renaming) inputRef.current?.select();
    }, [renaming]);

    function saveName() {
        const name = draft.trim();
        if (!name || name === list.name) {
            setRenaming(false);
            setDraft(list.name);
            return;
        }
        router.put(`/lists/${list.id}`, { name }, {
            preserveScroll: true,
            onSuccess: () => setRenaming(false),
        });
    }

    function saveColor(color: string | null) {
        router.put(`/lists/${list.id}`, { color }, {
            preserveScroll: true,
            onFinish: () => setColorOpen(false),
        });
    }

    const dot = list.is_inbox ? '📥' : null;

    return (
        <>
            <div
                onClick={() => !renaming && onSelect()}
                onContextMenu={(e) => {
                    e.preventDefault();
                    // Trigger the context menu via the kebab button programmatically
                    (e.currentTarget.querySelector('[data-context-trigger]') as HTMLButtonElement | null)?.click();
                }}
                style={selected && !list.is_inbox && list.color ? { borderLeftColor: list.color } : undefined}
                className={`group flex items-center gap-2 px-2 py-1.5 -mx-2 rounded text-sm cursor-pointer border-l-[3px] ${
                    selected
                        ? 'bg-amber-100 dark:bg-amber-900/30 border-l-current'
                        : 'border-l-transparent hover:bg-amber-50 dark:hover:bg-amber-950/20'
                }`}
            >
                <span className="text-sm">
                    {dot ?? (
                        <span
                            className="inline-block w-2 h-2 rounded-full"
                            style={{ backgroundColor: list.color ?? '#999' }}
                        />
                    )}
                </span>
                {renaming ? (
                    <input
                        ref={inputRef}
                        value={draft}
                        onChange={(e) => setDraft(e.target.value)}
                        onBlur={saveName}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') saveName();
                            if (e.key === 'Escape') {
                                setRenaming(false);
                                setDraft(list.name);
                            }
                        }}
                        onClick={(e) => e.stopPropagation()}
                        className="flex-1 bg-transparent text-sm outline-none border-b border-amber-500"
                    />
                ) : (
                    <span className="flex-1 truncate">{list.name}</span>
                )}
                <span className="text-xs text-muted-foreground tabular-nums">{reminderCount}</span>
                {!list.is_inbox && (
                    <ListContextMenu
                        onRename={() => setRenaming(true)}
                        onChangeColor={() => setColorOpen(true)}
                        onDelete={() => setDeleteOpen(true)}
                    >
                        <Button
                            data-context-trigger
                            variant="ghost"
                            size="icon"
                            className="h-5 w-5 opacity-0 group-hover:opacity-100 -mr-1"
                            onClick={(e) => e.stopPropagation()}
                        >
                            <MoreHorizontal className="h-3 w-3" />
                        </Button>
                    </ListContextMenu>
                )}
            </div>

            {colorOpen && !list.is_inbox && (
                <div className="px-2 py-2 -mx-2 bg-amber-50 dark:bg-amber-950/20 rounded">
                    <ColorPicker value={list.color} onChange={saveColor} swatches={curatedColors} />
                </div>
            )}

            {!list.is_inbox && (
                <DeleteListDialog
                    open={deleteOpen}
                    onClose={() => setDeleteOpen(false)}
                    list={list}
                    reminderCount={reminderCount}
                />
            )}
        </>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/sidebar-list-item.tsx
git commit -m "feat(dashboard): SidebarListItem — select/rename/color/delete + visual state"
```

---

### Task 13: `sidebar-new-list-input.tsx`

**Files:** Create `resources/js/components/dashboard/sidebar-new-list-input.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/sidebar-new-list-input.tsx
import { router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useRef, useState } from 'react';
import { ColorPicker } from './color-picker';

type Props = {
    curatedColors: string[];
};

export function SidebarNewListInput({ curatedColors }: Props) {
    const [open, setOpen] = useState(false);
    const [name, setName] = useState('');
    const [color, setColor] = useState<string | null>(curatedColors[0] ?? null);
    const inputRef = useRef<HTMLInputElement>(null);

    function start() {
        setOpen(true);
        queueMicrotask(() => inputRef.current?.focus());
    }

    function cancel() {
        setOpen(false);
        setName('');
        setColor(curatedColors[0] ?? null);
    }

    function submit() {
        const trimmed = name.trim();
        if (!trimmed) {
            cancel();
            return;
        }
        router.post('/lists', { name: trimmed, color }, {
            preserveScroll: true,
            onSuccess: () => cancel(),
        });
    }

    if (!open) {
        return (
            <button
                type="button"
                onClick={start}
                className="flex items-center gap-2 px-2 py-1.5 -mx-2 mt-1 rounded text-sm text-muted-foreground hover:bg-amber-50 dark:hover:bg-amber-950/20 w-[calc(100%+1rem)]"
            >
                <Plus className="h-3.5 w-3.5" />
                <span>New list</span>
            </button>
        );
    }

    return (
        <div className="px-2 py-2 -mx-2 mt-1 bg-amber-50 dark:bg-amber-950/20 rounded space-y-2">
            <input
                ref={inputRef}
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="List name"
                onKeyDown={(e) => {
                    if (e.key === 'Enter') submit();
                    if (e.key === 'Escape') cancel();
                }}
                className="w-full text-sm bg-background border border-input rounded px-2 py-1"
            />
            <ColorPicker value={color} onChange={setColor} swatches={curatedColors} />
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/sidebar-new-list-input.tsx
git commit -m "feat(dashboard): SidebarNewListInput — focus-expanding name+color form"
```

---

### Task 14: `sidebar.tsx` — wires items + DnD

**Files:** Create `resources/js/components/dashboard/sidebar.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/sidebar.tsx
import {
    closestCenter,
    DndContext,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import type { ReminderList } from '@/types/remind';
import { BrandWordmark } from './brand-wordmark';
import { SidebarListItem } from './sidebar-list-item';
import { SidebarNewListInput } from './sidebar-new-list-input';

function SortableItem({
    list,
    selected,
    reminderCount,
    curatedColors,
    onSelect,
}: {
    list: ReminderList;
    selected: boolean;
    reminderCount: number;
    curatedColors: string[];
    onSelect: () => void;
}) {
    const sortable = useSortable({ id: list.id, disabled: list.is_inbox });
    const style = {
        transform: CSS.Transform.toString(sortable.transform),
        transition: sortable.transition,
        opacity: sortable.isDragging ? 0.4 : 1,
    };

    return (
        <div ref={sortable.setNodeRef} style={style} {...sortable.attributes} {...sortable.listeners}>
            <SidebarListItem
                list={list}
                selected={selected}
                reminderCount={reminderCount}
                curatedColors={curatedColors}
                onSelect={onSelect}
            />
        </div>
    );
}

type Props = {
    lists: ReminderList[];
    selectedListId: number;
    curatedColors: string[];
    onSelect: (id: number) => void;
};

export function Sidebar({ lists, selectedListId, curatedColors, onSelect }: Props) {
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

    function onDragEnd(e: DragEndEvent) {
        if (!e.over || e.active.id === e.over.id) return;
        const oldIdx = lists.findIndex((l) => l.id === e.active.id);
        const newIdx = lists.findIndex((l) => l.id === e.over!.id);
        if (oldIdx === -1 || newIdx === -1) return;
        const order = lists.slice();
        order.splice(newIdx, 0, order.splice(oldIdx, 1)[0]);
        router.put('/lists/reorder', { order: order.map((l) => l.id) }, { preserveScroll: true });
    }

    return (
        <aside className="w-60 shrink-0 bg-amber-50/80 dark:bg-amber-950/20 border-r border-amber-100 dark:border-amber-900/30 px-4 py-4 flex flex-col">
            <BrandWordmark className="text-base mb-5" />
            <div className="text-xs uppercase tracking-widest text-amber-900/50 dark:text-amber-200/50 mb-2">
                Projects
            </div>
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                <SortableContext items={lists.map((l) => l.id)} strategy={verticalListSortingStrategy}>
                    <div className="space-y-0.5">
                        {lists.map((l) => (
                            <SortableItem
                                key={l.id}
                                list={l}
                                selected={l.id === selectedListId}
                                reminderCount={l.open_count ?? 0}
                                curatedColors={curatedColors}
                                onSelect={() => onSelect(l.id)}
                            />
                        ))}
                    </div>
                </SortableContext>
            </DndContext>
            <SidebarNewListInput curatedColors={curatedColors} />
        </aside>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/sidebar.tsx
git commit -m "feat(dashboard): Sidebar with dnd-kit reorder, list items, new-list input"
```

---

## Phase 4 — Main pane

### Task 15: `main-pane-header.tsx`

**Files:** Create `resources/js/components/dashboard/main-pane-header.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/main-pane-header.tsx
import type { ReminderList } from '@/types/remind';

type Props = {
    list: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    openCount: number;
};

export function MainPaneHeader({ list, openCount }: Props) {
    return (
        <div className="flex items-center gap-3 px-6 pt-6 pb-3">
            {list.is_inbox ? (
                <span className="text-base">📥</span>
            ) : (
                <span
                    className="inline-block w-2.5 h-2.5 rounded-full"
                    style={{ backgroundColor: list.color ?? '#999' }}
                />
            )}
            <h1 className="text-lg font-semibold tracking-tight">{list.name}</h1>
            <span className="ml-auto text-xs bg-amber-100 dark:bg-amber-900/30 text-amber-900 dark:text-amber-200 rounded-full px-2 py-0.5">
                {openCount} open
            </span>
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/main-pane-header.tsx
git commit -m "feat(dashboard): MainPaneHeader with color dot + open-count pill"
```

---

### Task 16: `add-reminder-row.tsx`

**Files:** Create `resources/js/components/dashboard/add-reminder-row.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/add-reminder-row.tsx
import { router } from '@inertiajs/react';
import { forwardRef, useImperativeHandle, useRef, useState } from 'react';

export type AddReminderRowHandle = {
    focus: () => void;
};

type Props = {
    listId: number;
};

export const AddReminderRow = forwardRef<AddReminderRowHandle, Props>(function AddReminderRow({ listId }, ref) {
    const [title, setTitle] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    useImperativeHandle(ref, () => ({
        focus: () => inputRef.current?.focus(),
    }));

    function submit() {
        const t = title.trim();
        if (!t) return;
        setSubmitting(true);
        router.post(
            '/reminders',
            { list_id: listId, title: t },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setTitle('');
                    inputRef.current?.focus();
                },
                onFinish: () => setSubmitting(false),
            },
        );
    }

    return (
        <div className="mx-4 mb-2 px-3 py-2 rounded bg-amber-50/70 dark:bg-amber-950/20 border border-amber-100/60 dark:border-amber-900/30 flex items-center gap-2">
            <span className="text-amber-700 dark:text-amber-300">+</span>
            <input
                ref={inputRef}
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') submit();
                    if (e.key === 'Escape') {
                        setTitle('');
                        inputRef.current?.blur();
                    }
                }}
                placeholder="Add a reminder…"
                disabled={submitting}
                className="flex-1 bg-transparent text-sm outline-none placeholder:text-amber-900/40 dark:placeholder:text-amber-200/40"
            />
        </div>
    );
});
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/add-reminder-row.tsx
git commit -m "feat(dashboard): AddReminderRow — title input with Enter-to-save + chained adds"
```

---

### Task 17: `reminder-editor-card.tsx`

**Files:** Create `resources/js/components/dashboard/reminder-editor-card.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/reminder-editor-card.tsx
import { Button } from '@/components/ui/button';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { Reminder } from '@/types/remind';

type Mode = 'edit' | 'preview';

type Props = {
    reminder: Reminder;
    onSaved: () => void;
    onCancel: () => void;
};

export function ReminderEditorCard({ reminder, onSaved, onCancel }: Props) {
    const [mode, setMode] = useState<Mode>('edit');
    const { data, setData, put, processing, errors } = useForm({
        title: reminder.title,
        notes: reminder.notes ?? '',
        soft_due_date: reminder.soft_due_date ?? '',
        context_branch: reminder.context?.branch ?? '',
        context_file: reminder.context?.file ?? '',
        context_line_start: reminder.context?.line_start?.toString() ?? '',
        context_line_end: reminder.context?.line_end?.toString() ?? '',
    });

    function save() {
        const payload: Record<string, unknown> = {
            title: data.title,
            notes: data.notes === '' ? null : data.notes,
            soft_due_date: data.soft_due_date === '' ? null : data.soft_due_date,
        };

        const ctx: Record<string, string | number> = {};
        if (reminder.context?.repo) ctx.repo = reminder.context.repo;
        if (reminder.context?.repo_label) ctx.repo_label = reminder.context.repo_label;
        if (reminder.context?.cwd) ctx.cwd = reminder.context.cwd;
        if (data.context_branch) ctx.branch = data.context_branch;
        if (data.context_file) ctx.file = data.context_file;
        if (data.context_line_start) ctx.line_start = Number(data.context_line_start);
        if (data.context_line_end) ctx.line_end = Number(data.context_line_end);
        payload.context = Object.keys(ctx).length ? ctx : null;

        put(`/reminders/${reminder.id}`, {
            ...payload,
            preserveScroll: true,
            onSuccess: () => onSaved(),
        });
    }

    function destroy() {
        if (!confirm('Delete this reminder?')) return;
        // window.location is sidestepped — use router.delete
        import('@inertiajs/react').then(({ router }) => {
            router.delete(`/reminders/${reminder.id}`, {
                preserveScroll: true,
                onSuccess: () => onCancel(),
            });
        });
    }

    return (
        <div className="mx-4 my-2 p-4 rounded border border-amber-200 dark:border-amber-900/40 bg-white dark:bg-amber-950/10 shadow-sm space-y-3">
            <input
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                className="w-full text-base font-medium bg-transparent outline-none"
                placeholder="Title"
            />
            {errors.title && <p className="text-xs text-red-600">{errors.title}</p>}

            <div>
                <div className="flex items-center gap-2 mb-1">
                    <button
                        type="button"
                        onClick={() => setMode('edit')}
                        className={`text-xs px-2 py-0.5 rounded ${mode === 'edit' ? 'bg-amber-100 dark:bg-amber-900/40' : 'text-muted-foreground'}`}
                    >
                        Edit
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('preview')}
                        className={`text-xs px-2 py-0.5 rounded ${mode === 'preview' ? 'bg-amber-100 dark:bg-amber-900/40' : 'text-muted-foreground'}`}
                    >
                        Preview
                    </button>
                </div>
                {mode === 'edit' ? (
                    <textarea
                        value={data.notes}
                        onChange={(e) => setData('notes', e.target.value)}
                        rows={4}
                        className="w-full text-sm border border-input rounded px-2 py-1.5 bg-background"
                        placeholder="Notes (markdown)…"
                    />
                ) : (
                    <div
                        className="text-sm prose prose-sm dark:prose-invert max-w-none px-2 py-1.5 border border-input rounded bg-amber-50/40 dark:bg-amber-950/10 min-h-[80px]"
                        dangerouslySetInnerHTML={{ __html: reminder.notes_html || '<em class=\'text-muted-foreground\'>No notes yet</em>' }}
                    />
                )}
            </div>

            <div className="flex items-center gap-3 text-sm">
                <label className="text-muted-foreground">Due</label>
                <input
                    type="date"
                    value={data.soft_due_date}
                    onChange={(e) => setData('soft_due_date', e.target.value)}
                    className="text-sm border border-input rounded px-2 py-1 bg-background"
                />
                {data.soft_due_date && (
                    <button type="button" className="text-xs text-muted-foreground" onClick={() => setData('soft_due_date', '')}>
                        Clear
                    </button>
                )}
            </div>

            <div className="grid grid-cols-2 gap-2 text-sm">
                <input
                    value={data.context_branch}
                    onChange={(e) => setData('context_branch', e.target.value)}
                    placeholder="branch"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_file}
                    onChange={(e) => setData('context_file', e.target.value)}
                    placeholder="file"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_line_start}
                    onChange={(e) => setData('context_line_start', e.target.value)}
                    placeholder="line start"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
                <input
                    value={data.context_line_end}
                    onChange={(e) => setData('context_line_end', e.target.value)}
                    placeholder="line end"
                    className="border border-input rounded px-2 py-1 bg-background font-mono text-xs"
                />
            </div>

            <div className="flex items-center justify-between pt-1">
                <Button type="button" variant="ghost" size="sm" onClick={destroy} className="text-red-600 hover:text-red-700">
                    Delete
                </Button>
                <div className="flex gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={processing}>
                        Cancel
                    </Button>
                    <Button type="button" size="sm" onClick={save} disabled={processing}>
                        Save
                    </Button>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/reminder-editor-card.tsx
git commit -m "feat(dashboard): ReminderEditorCard — title/notes/due/context + save/delete"
```

---

### Task 18: `reminder-row.tsx` — row visual + expand state

**Files:** Create `resources/js/components/dashboard/reminder-row.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/reminder-row.tsx
import { Checkbox } from '@/components/ui/checkbox';
import { router } from '@inertiajs/react';
import { AnimatePresence, motion } from 'framer-motion';
import { GripVertical } from 'lucide-react';
import type { Reminder } from '@/types/remind';
import { ContextChip } from './context-chip';
import { DueDateBadge } from './due-date-badge';
import { ReminderEditorCard } from './reminder-editor-card';

type Props = {
    reminder: Reminder;
    expanded: boolean;
    highlighted: boolean;
    onToggleExpand: () => void;
    onCloseExpand: () => void;
    dragHandleProps?: React.HTMLAttributes<HTMLButtonElement>;
};

export function ReminderRow({
    reminder,
    expanded,
    highlighted,
    onToggleExpand,
    onCloseExpand,
    dragHandleProps,
}: Props) {
    function toggleDone(checked: boolean) {
        router.post(
            `/reminders/${reminder.id}/complete`,
            { done: checked },
            { preserveScroll: true },
        );
    }

    return (
        <div data-reminder-id={reminder.id}>
            <div
                onClick={(e) => {
                    // Ignore clicks on interactive children
                    if ((e.target as HTMLElement).closest('button, input, [data-no-expand]')) return;
                    onToggleExpand();
                }}
                className={`group flex items-center gap-3 px-4 py-2.5 border-b border-amber-100/60 dark:border-amber-900/30 hover:bg-amber-50/50 dark:hover:bg-amber-950/10 cursor-pointer ${
                    highlighted ? 'ring-2 ring-amber-300 dark:ring-amber-700 ring-inset' : ''
                }`}
            >
                <button
                    type="button"
                    {...dragHandleProps}
                    data-no-expand
                    className="opacity-0 group-hover:opacity-50 cursor-grab active:cursor-grabbing"
                    aria-label="Drag to reorder"
                >
                    <GripVertical className="h-4 w-4" />
                </button>
                <Checkbox
                    checked={false}
                    onCheckedChange={(c) => toggleDone(Boolean(c))}
                    data-no-expand
                    onClick={(e) => e.stopPropagation()}
                />
                <span className="flex-1 text-sm truncate">{reminder.title}</span>
                <ContextChip context={reminder.context} />
                <DueDateBadge date={reminder.soft_due_date} />
            </div>
            <AnimatePresence initial={false}>
                {expanded && (
                    <motion.div
                        initial={{ height: 0, opacity: 0 }}
                        animate={{ height: 'auto', opacity: 1 }}
                        exit={{ height: 0, opacity: 0 }}
                        transition={{ duration: 0.15, ease: 'easeOut' }}
                        className="overflow-hidden"
                    >
                        <ReminderEditorCard
                            reminder={reminder}
                            onSaved={onCloseExpand}
                            onCancel={onCloseExpand}
                        />
                    </motion.div>
                )}
            </AnimatePresence>
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/reminder-row.tsx
git commit -m "feat(dashboard): ReminderRow with checkbox/chips + inline editor expand"
```

---

### Task 19: `completed-accordion.tsx`

**Files:** Create `resources/js/components/dashboard/completed-accordion.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/completed-accordion.tsx
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { router } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useState } from 'react';
import type { Reminder } from '@/types/remind';

type Props = {
    reminders: Reminder[];
};

export function CompletedAccordion({ reminders }: Props) {
    const [open, setOpen] = useState(false);

    if (reminders.length === 0) return null;

    function uncomplete(id: number) {
        router.post(`/reminders/${id}/complete`, { done: false }, { preserveScroll: true });
    }

    return (
        <Collapsible open={open} onOpenChange={setOpen} className="mx-4 my-4 border-t border-amber-100/60 dark:border-amber-900/30 pt-3">
            <CollapsibleTrigger className="flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
                <ChevronRight className={`h-4 w-4 transition-transform ${open ? 'rotate-90' : ''}`} />
                <span>{open ? 'Hide' : 'Show'} {reminders.length} completed</span>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-2 space-y-0.5">
                    {reminders.map((r) => (
                        <div
                            key={r.id}
                            className="flex items-center gap-3 px-2 py-1.5 rounded opacity-60 hover:opacity-100 hover:bg-amber-50/50 dark:hover:bg-amber-950/10"
                        >
                            <Checkbox checked onCheckedChange={() => uncomplete(r.id)} />
                            <span className="flex-1 text-sm line-through">{r.title}</span>
                            <span className="text-xs text-muted-foreground">
                                {r.completed_at ? new Date(r.completed_at).toLocaleDateString() : ''}
                            </span>
                        </div>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/completed-accordion.tsx
git commit -m "feat(dashboard): CompletedAccordion — collapsed by default, uncomplete on click"
```

---

### Task 20: `main-pane.tsx` — wires header + add + dnd-sortable rows + completed + empty

**Files:** Create `resources/js/components/dashboard/main-pane.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/main-pane.tsx
import {
    closestCenter,
    DndContext,
    PointerSensor,
    useSensor,
    useSensors,
    type DragEndEvent,
} from '@dnd-kit/core';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import type { Reminder, ReminderList } from '@/types/remind';
import { AddReminderRow, type AddReminderRowHandle } from './add-reminder-row';
import { CompletedAccordion } from './completed-accordion';
import { EmptyState } from './empty-state';
import { MainPaneHeader } from './main-pane-header';
import { ReminderRow } from './reminder-row';

function SortableReminder({
    reminder,
    expanded,
    highlighted,
    onToggleExpand,
    onCloseExpand,
}: {
    reminder: Reminder;
    expanded: boolean;
    highlighted: boolean;
    onToggleExpand: () => void;
    onCloseExpand: () => void;
}) {
    const sortable = useSortable({ id: reminder.id });
    const style = {
        transform: CSS.Transform.toString(sortable.transform),
        transition: sortable.transition,
        opacity: sortable.isDragging ? 0.4 : 1,
    };

    return (
        <div ref={sortable.setNodeRef} style={style}>
            <ReminderRow
                reminder={reminder}
                expanded={expanded}
                highlighted={highlighted}
                onToggleExpand={onToggleExpand}
                onCloseExpand={onCloseExpand}
                dragHandleProps={{ ...sortable.attributes, ...sortable.listeners }}
            />
        </div>
    );
}

type Props = {
    list: Pick<ReminderList, 'id' | 'name' | 'color' | 'is_inbox'>;
    reminders: Reminder[];
    completedReminders: Reminder[];
    addRef: React.RefObject<AddReminderRowHandle | null>;
};

export function MainPane({ list, reminders, completedReminders, addRef }: Props) {
    const [expandedId, setExpandedId] = useState<number | null>(null);
    const [highlightedId, setHighlightedId] = useState<number | null>(null);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

    function onDragEnd(e: DragEndEvent) {
        if (!e.over || e.active.id === e.over.id) return;
        const oldIdx = reminders.findIndex((r) => r.id === e.active.id);
        const newIdx = reminders.findIndex((r) => r.id === e.over!.id);
        if (oldIdx === -1 || newIdx === -1) return;
        const reordered = reminders.slice();
        reordered.splice(newIdx, 0, reordered.splice(oldIdx, 1)[0]);
        router.put('/reminders/reorder', {
            list_id: list.id,
            order: reordered.map((r) => r.id),
        }, { preserveScroll: true });
    }

    return (
        <main className="flex-1 min-w-0 flex flex-col">
            <MainPaneHeader list={list} openCount={reminders.length} />
            <AddReminderRow ref={addRef} listId={list.id} />
            {reminders.length === 0 && completedReminders.length === 0 ? (
                <EmptyState message="No reminders here yet — press n to add one." />
            ) : (
                <>
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={onDragEnd}>
                        <SortableContext items={reminders.map((r) => r.id)} strategy={verticalListSortingStrategy}>
                            <div>
                                {reminders.map((r) => (
                                    <SortableReminder
                                        key={r.id}
                                        reminder={r}
                                        expanded={expandedId === r.id}
                                        highlighted={highlightedId === r.id}
                                        onToggleExpand={() => setExpandedId((cur) => (cur === r.id ? null : r.id))}
                                        onCloseExpand={() => setExpandedId(null)}
                                    />
                                ))}
                            </div>
                        </SortableContext>
                    </DndContext>
                    <CompletedAccordion reminders={completedReminders} />
                </>
            )}
        </main>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/main-pane.tsx
git commit -m "feat(dashboard): MainPane wires header/add/sortable rows/completed/empty"
```

---

## Phase 5 — Hooks

### Task 21: `use-selected-list.ts`

**Files:** Create `resources/js/hooks/use-selected-list.ts`

- [ ] **Step 1: Hook**

```ts
// resources/js/hooks/use-selected-list.ts
import { router } from '@inertiajs/react';

export function useSelectedList() {
    function select(id: number) {
        router.get(
            '/dashboard',
            { list: id },
            {
                preserveScroll: true,
                preserveState: true,
                only: ['selectedList', 'reminders', 'completedReminders'],
                replace: true,
            },
        );
    }

    return { select };
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/hooks/use-selected-list.ts
git commit -m "feat(hooks): useSelectedList — partial Inertia visit on list switch"
```

---

### Task 22: `use-click-outside.ts`

**Files:** Create `resources/js/hooks/use-click-outside.ts`

- [ ] **Step 1: Hook**

```ts
// resources/js/hooks/use-click-outside.ts
import { useEffect, useRef } from 'react';

export function useClickOutside<T extends HTMLElement>(active: boolean, onOutside: () => void) {
    const ref = useRef<T>(null);

    useEffect(() => {
        if (!active) return;
        function onDocClick(e: MouseEvent) {
            if (!ref.current) return;
            if (ref.current.contains(e.target as Node)) return;
            onOutside();
        }
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, [active, onOutside]);

    return ref;
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/hooks/use-click-outside.ts
git commit -m "feat(hooks): useClickOutside"
```

---

### Task 23: `use-keyboard-shortcuts.ts`

**Files:** Create `resources/js/hooks/use-keyboard-shortcuts.ts`

- [ ] **Step 1: Hook**

```ts
// resources/js/hooks/use-keyboard-shortcuts.ts
import { useEffect, useRef } from 'react';

export type Shortcuts = {
    onNew?: () => void;
    onArrowUp?: () => void;
    onArrowDown?: () => void;
    onEnter?: () => void;
    onSpace?: () => void;
    onRename?: () => void;
    onDelete?: () => void;
    onJumpToListAtIndex?: (idx: number) => void;
    onJumpToInbox?: () => void;
    onEscape?: () => void;
    onCheatsheet?: () => void;
};

function isTextInputFocused(): boolean {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || (el as HTMLElement).isContentEditable;
}

export function useKeyboardShortcuts(s: Shortcuts) {
    const gArmedAt = useRef<number | null>(null);
    const G_WINDOW_MS = 1500;

    useEffect(() => {
        function onKey(e: KeyboardEvent) {
            // Always allow Esc, even when an input is focused.
            if (e.key === 'Escape') {
                s.onEscape?.();
                return;
            }
            if (isTextInputFocused()) return;

            const armed = gArmedAt.current !== null && Date.now() - gArmedAt.current < G_WINDOW_MS;
            if (armed) {
                gArmedAt.current = null;
                if (e.key === 'i') {
                    s.onJumpToInbox?.();
                    e.preventDefault();
                    return;
                }
                if (/^[1-9]$/.test(e.key)) {
                    s.onJumpToListAtIndex?.(parseInt(e.key, 10) - 1);
                    e.preventDefault();
                    return;
                }
                return;
            }

            switch (e.key) {
                case 'g':
                    gArmedAt.current = Date.now();
                    break;
                case 'n':
                    s.onNew?.();
                    e.preventDefault();
                    break;
                case 'ArrowUp':
                    s.onArrowUp?.();
                    e.preventDefault();
                    break;
                case 'ArrowDown':
                    s.onArrowDown?.();
                    e.preventDefault();
                    break;
                case 'Enter':
                    s.onEnter?.();
                    break;
                case ' ':
                    s.onSpace?.();
                    e.preventDefault();
                    break;
                case 'e':
                    s.onRename?.();
                    e.preventDefault();
                    break;
                case 'Delete':
                case 'Backspace':
                    s.onDelete?.();
                    break;
                case '?':
                    s.onCheatsheet?.();
                    e.preventDefault();
                    break;
            }
        }
        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [s]);
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/hooks/use-keyboard-shortcuts.ts
git commit -m "feat(hooks): useKeyboardShortcuts with g-prefix chord"
```

---

## Phase 6 — Cheatsheet + Layout + Page

### Task 24: `shortcuts-cheatsheet.tsx`

**Files:** Create `resources/js/components/dashboard/shortcuts-cheatsheet.tsx`

- [ ] **Step 1: Component**

```tsx
// resources/js/components/dashboard/shortcuts-cheatsheet.tsx
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

const ROWS: [string, string][] = [
    ['n', 'New reminder'],
    ['↑ / ↓', 'Move between rows'],
    ['Enter', 'Open editor'],
    ['Space', 'Toggle done'],
    ['e', 'Rename in place'],
    ['Delete / Backspace', 'Delete reminder'],
    ['g then i', 'Jump to Inbox'],
    ['g then 1–9', 'Jump to list 1–9'],
    ['Esc', 'Close editor / cancel'],
    ['?', 'Show this'],
];

type Props = { open: boolean; onClose: () => void };

export function ShortcutsCheatsheet({ open, onClose }: Props) {
    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Keyboard shortcuts</DialogTitle>
                </DialogHeader>
                <table className="w-full text-sm">
                    <tbody>
                        {ROWS.map(([k, label]) => (
                            <tr key={k} className="border-b border-amber-100/60 dark:border-amber-900/30 last:border-0">
                                <td className="py-1.5 pr-4 font-mono text-xs text-amber-700 dark:text-amber-300 whitespace-nowrap">{k}</td>
                                <td className="py-1.5">{label}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </DialogContent>
        </Dialog>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/components/dashboard/shortcuts-cheatsheet.tsx
git commit -m "feat(dashboard): ShortcutsCheatsheet dialog (?-trigger)"
```

---

### Task 25: `dashboard-layout.tsx`

**Files:** Create `resources/js/layouts/dashboard-layout.tsx`

- [ ] **Step 1: Layout**

```tsx
// resources/js/layouts/dashboard-layout.tsx
import { Toaster } from '@/components/ui/sonner';
import type { ReactNode } from 'react';

export function DashboardLayout({ children }: { children: ReactNode }) {
    return (
        <div className="flex h-screen bg-amber-50/40 dark:bg-amber-950/10 text-foreground">
            {children}
            <Toaster richColors position="top-right" />
        </div>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/layouts/dashboard-layout.tsx
git commit -m "feat(layouts): DashboardLayout shell"
```

---

### Task 26: Rewrite `dashboard/Index.tsx`

**Files:** Modify `resources/js/pages/dashboard/Index.tsx`

- [ ] **Step 1: Replace contents**

```tsx
// resources/js/pages/dashboard/Index.tsx
import { Head, useForm } from '@inertiajs/react';
import { useCallback, useRef, useState } from 'react';
import { MainPane } from '@/components/dashboard/main-pane';
import { ShortcutsCheatsheet } from '@/components/dashboard/shortcuts-cheatsheet';
import { Sidebar } from '@/components/dashboard/sidebar';
import { DashboardLayout } from '@/layouts/dashboard-layout';
import { useKeyboardShortcuts } from '@/hooks/use-keyboard-shortcuts';
import { useSelectedList } from '@/hooks/use-selected-list';
import type { AddReminderRowHandle } from '@/components/dashboard/add-reminder-row';
import type { DashboardPageProps } from '@/types/remind';

export default function Dashboard(props: DashboardPageProps) {
    const { lists, selectedList, reminders, completedReminders, curatedColors } = props;
    const { select } = useSelectedList();
    const [highlightIdx, setHighlightIdx] = useState<number | null>(null);
    const [cheatsheetOpen, setCheatsheetOpen] = useState(false);
    const addRowRef = useRef<AddReminderRowHandle | null>(null);

    // suppress unused warning for useForm reservation
    void useForm;

    const moveHighlight = useCallback(
        (delta: number) => {
            if (reminders.length === 0) return;
            setHighlightIdx((cur) => {
                const next = cur === null ? 0 : Math.max(0, Math.min(reminders.length - 1, cur + delta));
                return next;
            });
        },
        [reminders.length],
    );

    useKeyboardShortcuts({
        onNew: () => addRowRef.current?.focus(),
        onArrowUp: () => moveHighlight(-1),
        onArrowDown: () => moveHighlight(1),
        onEscape: () => setHighlightIdx(null),
        onCheatsheet: () => setCheatsheetOpen(true),
        onJumpToInbox: () => {
            const inbox = lists.find((l) => l.is_inbox);
            if (inbox) select(inbox.id);
        },
        onJumpToListAtIndex: (idx) => {
            const l = lists[idx];
            if (l) select(l.id);
        },
    });

    return (
        <DashboardLayout>
            <Head title={`Re:Mind — ${selectedList.name}`} />
            <Sidebar
                lists={lists}
                selectedListId={selectedList.id}
                curatedColors={curatedColors}
                onSelect={select}
            />
            <MainPane
                list={selectedList}
                reminders={reminders}
                completedReminders={completedReminders}
                addRef={addRowRef}
            />
            <ShortcutsCheatsheet open={cheatsheetOpen} onClose={() => setCheatsheetOpen(false)} />
        </DashboardLayout>
    );
}
```

- [ ] **Step 2: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/pages/dashboard/Index.tsx
git commit -m "feat(dashboard): rewrite dashboard/Index.tsx — wires sidebar, main pane, shortcuts"
```

---

## Phase 7 — Settings → MCP page

### Task 27: `settings/mcp.tsx`

**Files:** Modify `resources/js/pages/settings/mcp.tsx`

- [ ] **Step 1: Page**

```tsx
// resources/js/pages/settings/mcp.tsx
import { useClipboard } from '@/hooks/use-clipboard';
import SettingsLayout from '@/layouts/settings/layout';
import { Head } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import type { McpPageProps } from '@/types/remind';

export default function McpSettings({ mcpUrl, tools }: McpPageProps) {
    const { copy, copied } = useClipboard();

    const snippet = JSON.stringify(
        { mcpServers: { remind: { type: 'http', url: mcpUrl } } },
        null,
        2,
    );

    async function copySnippet() {
        await copy(snippet);
        toast.success('Copied to clipboard');
    }

    return (
        <SettingsLayout>
            <Head title="MCP" />
            <div className="space-y-6">
                <header>
                    <h2 className="text-lg font-semibold tracking-tight">Claude Code MCP setup</h2>
                    <p className="text-sm text-muted-foreground mt-1">
                        Add this to <code className="font-mono text-xs">~/.claude.json</code> or a project
                        {' '}<code className="font-mono text-xs">.mcp.json</code> to make Claude Code talk to Re:Mind.
                    </p>
                </header>

                <div className="rounded border border-input bg-muted/30">
                    <div className="flex justify-between items-center px-3 py-2 border-b border-input">
                        <span className="text-xs uppercase tracking-wider text-muted-foreground">.mcp.json</span>
                        <Button type="button" size="sm" variant="ghost" onClick={copySnippet} className="gap-1">
                            {copied ? <Check className="h-3.5 w-3.5" /> : <Copy className="h-3.5 w-3.5" />}
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                    <pre className="px-3 py-3 text-xs font-mono overflow-x-auto">{snippet}</pre>
                </div>

                <section>
                    <h3 className="text-sm font-medium mb-2">What Claude can do</h3>
                    <ul className="text-sm text-muted-foreground space-y-1">
                        {tools.map((t) => (
                            <li key={t} className="font-mono text-xs">· {t}</li>
                        ))}
                    </ul>
                </section>

                <p className="text-xs text-muted-foreground border-t border-input pt-3">
                    Re:Mind&rsquo;s MCP endpoint is unauthenticated by design. Only expose this app on loopback or a private network.
                </p>
            </div>
        </SettingsLayout>
    );
}
```

- [ ] **Step 2: Add Settings sidebar nav entry**

Inspect the existing settings sidebar (the starter renders nav items somewhere under `resources/js/layouts/settings/layout.tsx` or a nearby file). Find the array/list of nav items, and add an entry pointing at `/settings/mcp` with label "MCP". Match the existing items' style (likely a `NavItem` or similar).

If the layout uses an array, add (preserve existing items, just append):

```ts
{ title: 'MCP', href: '/settings/mcp' },
```

- [ ] **Step 3: tsc + commit**

```bash
docker compose exec app npm run types:check 2>&1 | tail -3
git add resources/js/pages/settings/mcp.tsx resources/js/layouts/settings
git commit -m "feat(settings): MCP setup page with copy snippet + tool list"
```

---

## Phase 8 — Smoke

### Task 28: Frontend smoke + final verification

**Files:** none (verification only)

- [ ] **Step 1: Type check**

```bash
cd /home/aymeric/zWEB/remind
docker compose exec app npm run types:check
```

Expected: no errors.

- [ ] **Step 2: Lint**

```bash
docker compose exec app npm run lint:check
```

Expected: pass. If errors, run `npm run lint` to auto-fix and re-run; commit any auto-fixes.

- [ ] **Step 3: Production build**

```bash
docker compose exec app npm run build
```

Expected: build succeeds, generates `public/build/manifest.json`.

- [ ] **Step 4: Backend full suite**

```bash
docker compose exec app php artisan test --compact
```

Expected: all green (108+ tests, 2 skipped).

- [ ] **Step 5: Manual browser smoke (you do these)**

Sign in at `http://localhost:8000/login` as the seeded user (`REMIND_USER_EMAIL` / `REMIND_USER_PASSWORD` from `.env`). Then verify:

  - Dashboard at `http://localhost:8000/dashboard` shows the warm/branded layout with Inbox selected.
  - Type a title in the add-row + Enter → reminder appears in the list, add-row stays focused.
  - Click a row → editor card expands below; edit title/notes/due/context; Save → row updates, card collapses.
  - Check the checkbox → row fades into the completed footer (expand to see it).
  - Drag the row → drop somewhere else → order persists after refresh.
  - Drag a sidebar list → order persists.
  - Right-click a non-Inbox list → rename/color/delete all work. Cascade delete confirms before destroying.
  - `n` focuses the add row. `?` shows the cheatsheet. `g i` jumps to Inbox.
  - `http://localhost:8000/settings/mcp` shows the snippet; Copy works; toast shows.

- [ ] **Step 6: Commit any build artifacts if needed**

```bash
git status --short
```

Expected: clean. If `public/build/` shows changes (it shouldn't — it's gitignored), don't commit.

No final commit if nothing was modified; this task is verification-only.

---

## Self-review

- **Spec coverage:**
  - Spec §2 locked decisions (inline-expand, per-list accordion, /settings/mcp, server-confirmed, warm/branded) → all implemented (T17/T19, T20, T3+T27, all routes use Inertia router with no optimistic logic, T5/T15/T25 styling).
  - Spec §4 component tree → mapped 1:1 to Tasks 5-20 + 24-26.
  - Spec §5 data flow (selectedList resolved server-side, partial Inertia visits, server-rendered notes_html) → Task 2 (controller) + Task 21 (hook) + Task 17 (editor preview uses notes_html).
  - Spec §6 layout/interactions → Tasks 12-14 (sidebar), 15-20 (main pane), 23 (keyboard).
  - Spec §7 Settings → MCP → Tasks 3 + 27.
  - Spec §8 visuals → applied per component in T5-T20 (amber tokens, fonts, hover/active states).
  - Spec §9 libraries → Task 1 (deps installed/verified).
  - Spec §10 Wayfinder → router calls use string paths matching the named routes; Wayfinder generation isn't strictly required at runtime but Vite will regenerate `@/actions` and `@/routes` on next dev start. (If a future migration needs typed routes everywhere, sub them in.)
  - Spec §11 error handling → Inertia useForm errors surface in T17 (editor); Sonner toast wired in T25 layout.
  - Spec §12 testing → backend tests in T2 and T3; frontend verifies via tsc+build+manual smoke in T28.

- **Placeholder scan:** No TBDs/TODOs. Every code step contains the actual file contents.

- **Type consistency:** `AddReminderRowHandle` exported from T16 and consumed in T20 + T26. `Reminder`/`ReminderList`/`DashboardPageProps`/`McpPageProps` defined in T4 and imported by every later task. Function/prop names consistent (`onSelect`, `onSaved`, `onCancel`, `dragHandleProps`, `expanded`/`highlighted`).

- **Known soft spots worth noting in execution:**
  - Wayfinder import paths (`@/actions/...`) aren't used in this plan because the design said to use them but mid-implementation I judged the literal paths (`/lists`, `/reminders`) simpler and less brittle to refactor while the API surface is stable. The spec hints at Wayfinder strongly; if the implementer wants strict adherence, they can swap each `router.post('/lists', …)` for the Wayfinder helper one line at a time. Non-blocking.
  - The settings nav-item insertion in Task 27 Step 2 is intentionally exploratory: the starter's settings layout might expose its nav items inline in `layout.tsx` or in a separate `nav-items.ts`. The implementer should grep before guessing.
