# Re:Mind — Dashboard Frontend Design (Plan 2)

> Status: design v1 (brainstorm, 2026-06-02). Sub-spec to [`architecture.md`](architecture.md) §5. Source of truth for the dashboard implementation work.

## 1. What this covers

The Inertia + React 19 + Tailwind v4 + shadcn/ui dashboard that sits on top of the Plan 1 backend (HTTP controllers, policies, actions, MCP server, soft-delete + purge, markdown rendering). No new backend endpoints — only UI work, a Settings → MCP page, and the libraries the UI needs.

## 2. Locked decisions

- **Edit interaction**: inline expand below the row (Things/Todoist style).
- **Completed reminders**: per-list collapsible accordion at the bottom of the list. No global "Completed" sidebar item.
- **MCP setup snippet**: lives on a new `/settings/mcp` page inside the starter's existing Settings area.
- **Mutation model**: server-confirmed only (Inertia visits + page re-fetch). No optimistic UI.
- **Visual direction**: warm / branded — cream background, amber-accented "Re:Mind" wordmark with an amber colon, soft borders, list color rendered as a dot and a 3 px left border on the active sidebar row.

## 3. Non-goals (deferred)

- Mobile / responsive < 768 px (desktop-only v1).
- Optimistic UI, offline mode, drag-between-lists.
- Search / filter UI; bulk select / multi-edit.
- Tags, priorities, sharing.
- In-app dark-mode toggle (auto-detect via `prefers-color-scheme` only).
- Custom favicon / icon.

## 4. Component tree

```
resources/js/
├── layouts/
│   └── DashboardLayout.tsx              // sidebar + main pane shell
├── pages/
│   ├── dashboard/Index.tsx              // rewrites the placeholder
│   └── settings/Mcp.tsx                 // new
├── components/dashboard/
│   ├── BrandWordmark.tsx
│   ├── Sidebar.tsx
│   ├── SidebarListItem.tsx              // dnd-sortable, right-click menu
│   ├── SidebarNewListInput.tsx
│   ├── ListContextMenu.tsx              // rename / change color / delete
│   ├── DeleteListDialog.tsx             // MoveToInbox|Cascade choice
│   ├── ColorPicker.tsx                  // curated swatches + hex input
│   ├── MainPane.tsx
│   ├── MainPaneHeader.tsx
│   ├── AddReminderRow.tsx
│   ├── ReminderRow.tsx                  // dnd-sortable
│   ├── ReminderEditorCard.tsx
│   ├── ContextChip.tsx
│   ├── DueDateBadge.tsx
│   ├── CompletedAccordion.tsx
│   └── EmptyState.tsx
└── hooks/
    ├── useKeyboardShortcuts.ts          // n/↑↓/Enter/Space/e/Delete, g-prefix chord
    ├── useClickOutside.ts
    └── useSelectedList.ts               // reads/writes ?list=ID
```

## 5. Data flow

### 5.1 `DashboardController::__invoke()` (extended)

Resolves the selected list from `?list=<id>` (default: Inbox). Returns:

```php
Inertia::render('dashboard/Index', [
    'lists' => $user->lists()
        ->orderBy('position')
        ->withCount(['reminders as open_count' => fn ($q) => $q->where('status', 'open')])
        ->get(),
    'selectedList' => $selectedList,
    'reminders' => $selectedList->reminders()
        ->where('status', 'open')
        ->orderBy('position')
        ->get()
        ->map($this->presentReminder(...)),
    'completedReminders' => $selectedList->reminders()
        ->where('status', 'done')
        ->orderByDesc('completed_at')
        ->limit(50)
        ->get()
        ->map($this->presentReminder(...)),
]);
```

Each reminder payload carries `notes_html` rendered server-side via `Actions/Markdown/RenderNotes` (already built in Plan 1 Task 24). The client never parses markdown.

### 5.2 Settings → MCP

`SettingsMcpController::__invoke()` returns `settings/Mcp`:

```php
Inertia::render('settings/Mcp', [
    'mcpUrl' => rtrim(config('app.url'), '/').'/mcp',
    'tools' => ['list-projects', 'create-project', 'add-reminder', 'list-reminders', 'complete-reminder', 'update-reminder'],
]);
```

### 5.3 Client mutation pattern (server-confirmed)

Every write uses Inertia's `router.{post|put|delete}` via Wayfinder-generated URLs:

```tsx
import { store as storeReminder } from '@/actions/App/Http/Controllers/ReminderController';
import { router } from '@inertiajs/react';

router.post(storeReminder.url(), data, {
    preserveScroll: true,
    onSuccess: () => closeEditor(),
    onError: (errors) => toast.error(firstMessage(errors)),
});
```

Inertia re-fetches page props on success → the UI reflects the server state. Slight wire-time lag on localhost (~10–40 ms) is acceptable.

### 5.4 Switching lists

Sidebar click does a **partial Inertia visit**:

```tsx
router.get('/', { list: id }, {
    preserveScroll: true,
    only: ['selectedList', 'reminders', 'completedReminders'],
});
```

Only those three props refetch — `lists` stays in the page state so the sidebar doesn't flicker.

## 6. Layout & interactions

### 6.1 Sidebar (240 px fixed)

- **`<BrandWordmark>`** at the top: `Re<span class="text-amber-600">:</span>Mind`, semibold, tight tracking.
- **PROJECTS** section label — uppercase, `text-xs text-amber-900/50`.
- **`<SidebarListItem>`** per list:
  - Emoji indicator (📥 for Inbox, colored dot for others).
  - List name, then a muted open-count badge.
  - Active list: `bg-amber-100` + 3 px solid left border in the list's color.
  - Hover: shows a kebab button → opens `<ListContextMenu>`.
  - Right-click: also opens `<ListContextMenu>`.
- **Drag-to-reorder**: `@dnd-kit/sortable` with all items in a `<SortableContext>`. The Inbox item is sortable-disabled (always position 0). Drop posts `PUT /lists/reorder { order: [...] }`.
- **`<ListContextMenu>`** (Radix `DropdownMenu`): Rename · Change color · Delete.
- **Rename in-place**: double-click or context menu → swap label for `<input>`, blur/Enter saves, Esc cancels.
- **Color picker** popover: 6 curated swatches (`#7aa2f7 #9ece6a #e0af68 #bb9af7 #f7768e #7dcfff`) + a hex `<input>` (validated against `#?[0-9a-fA-F]{3,4,6,8}`).
- **`<DeleteListDialog>`** (shadcn `Dialog`): radio between "Move N reminders to Inbox" and "Delete list and all N reminders". Posts `DELETE /lists/{id}` with `strategy=move_to_inbox|cascade`.
- **`<SidebarNewListInput>`** at the bottom: `+ New list` row → click swaps for an input + the same 6-swatch row. Enter posts `POST /lists { name, color }`.

### 6.2 Main pane

- **`<MainPaneHeader>`**: small color dot · list name (`text-lg font-semibold tracking-tight`) · open-count pill (`bg-amber-100 text-amber-900 text-xs rounded-full px-2`).
- **`<AddReminderRow>`** — always visible, sits above the list. Idle: `+ Add a reminder…` cream pill. Focused: expands to show optional fields (notes collapsed, due-date + context buttons inline). Enter saves + resets and keeps focus for chained adds. Esc clears.
- **`<ReminderRow>`** per open reminder:
  - Layout: checkbox · title · context chip + due badge (right-aligned, muted).
  - Hover: drag handle (left edge — also the dnd-kit grab handle), kebab menu (right edge).
  - **Click anywhere on the row except the checkbox or the drag handle** → expands `<ReminderEditorCard>` below. Clicking the checkbox toggles done. Clicking the kebab opens a small menu (Delete, Duplicate-deferred).
- **`<ReminderEditorCard>`** (Framer Motion height transition, ~150 ms):
  - Title input (same line as the row's title).
  - Notes textarea + **Edit | Preview** toggle (Preview uses `notes_html` from server — only shown for saved content; while typing, plain textarea content).
  - Due date row: shadcn `Popover` + `Calendar`. "Clear" button when set.
  - Context: 4 small inputs (repo read-only "auto" label · branch · file · line range `start–end`).
  - Footer: Delete (left, destructive) · Save (right, primary; disabled until dirty).
- **Only one editor open at a time.** Opening a second row's editor closes the first.
- **`<CompletedAccordion>`** at the bottom of the main pane: `Show N completed ▸`, collapsed by default. Click expands; rows render dimmed with strikethrough. Checkbox toggles un-complete.
- **`<EmptyState>`** when `reminders.length === 0` AND `completedReminders.length === 0`: "No reminders here yet — press `n` to add one." Centered, muted, cream tinted.
- **Drag-reorder reminders**: only the open list is sortable. Drop posts `PUT /reminders/reorder { list_id, order }`.

### 6.3 Keyboard (`useKeyboardShortcuts`)

Listens at document level when no input/textarea is focused.

| Key | Action |
|---|---|
| `n` | Focus add-reminder row |
| `↑` / `↓` | Move highlight; scroll into view |
| `Enter` | Open editor for highlighted row |
| `Space` | Toggle done on highlighted row |
| `e` | Rename highlighted row inline |
| `Delete` / `Backspace` | Delete with confirm dialog |
| `g` then `i` | Jump to Inbox |
| `g` then `1`–`9` | Jump to list at that sidebar position |
| `Esc` | Close editor, blur input, dismiss dialog |
| `?` | Show keyboard cheatsheet overlay |

Chord handling: `g` arms a 1.5 s window; a small floating `g…` pill near the cursor signals it. Any non-chord key cancels and routes normally.

## 7. Settings → MCP page

`/settings/mcp` (auth-gated, inside the starter's `SettingsLayout`):

- Heading "Claude Code MCP setup".
- Short paragraph: what the snippet is and where to paste it (`~/.claude.json` or project `.mcp.json`).
- Code block with the snippet (plain `<pre>` is fine; shiki optional):
  ```json
  {
    "mcpServers": {
      "remind": { "type": "http", "url": "<APP_URL>/mcp" }
    }
  }
  ```
  `APP_URL` is templated server-side from `config('app.url')`.
- **Copy** button: `navigator.clipboard.writeText`, shows a "Copied" Sonner toast.
- 6-row list of the registered tool names (purely informational; not links).
- Security note footer: "Re:Mind's MCP endpoint is unauthenticated by design. Only expose this app on loopback or a private network."

The Settings sidebar gets a new "MCP" entry below the existing Account/Password/Two-factor links.

## 8. Visual specifics (warm/branded)

| Surface | Style |
|---|---|
| App shell background | `bg-amber-50/40` |
| Sidebar background | `bg-amber-50`, divider `border-amber-100` |
| Card surfaces | `bg-white` |
| Brand wordmark | semibold, tight tracking, the `:` wrapped in `<span class="text-amber-600">` |
| Active sidebar row | `bg-amber-100`, 3 px solid left border in the list's color |
| Context chip | `font-mono text-xs bg-amber-100 text-amber-900 rounded px-1.5 py-0.5` |
| Due "Today" / "Overdue" | amber-700 / red-600 text |
| Other dates | muted (`text-muted-foreground`) |
| Dark mode | auto-detect (`prefers-color-scheme`); amber accents shift to `amber-300/40` etc. No in-app toggle. |
| Type | inherit starter `font-sans` (Inter via Vite) |

## 9. Libraries to add

- `@dnd-kit/core`, `@dnd-kit/sortable`, `@dnd-kit/utilities` — drag reorder.
- `framer-motion` — height transition on the editor card + completed accordion.
- `sonner` — toast notifications. (Install only if not already shipped by the starter; check first.)

Already present from the React starter: Radix primitives (Dialog, Popover, DropdownMenu, Checkbox, Tooltip), Inertia v3, Tailwind v4, the Wayfinder vite plugin.

## 10. Wayfinder usage

All router calls use Wayfinder-generated helpers; no hardcoded URLs in TSX. Examples:

```tsx
import { store as createReminder } from '@/actions/App/Http/Controllers/ReminderController';
import { reorder as reorderLists } from '@/actions/App/Http/Controllers/ReminderListController';
import { dashboard } from '@/routes';
```

The plugin regenerates these on Vite startup; if a route is renamed or removed during this work, fix the importing site rather than hand-coding the URL.

## 11. Error handling

- **Validation errors** from server → Inertia's `useForm` exposes them; editor card renders inline errors below the relevant input. Sonner toast surfaces non-field errors ("List not found", etc.).
- **Network errors** → Sonner toast "Something went wrong. Try again." UI doesn't move (server-confirmed model means we never showed the optimistic state).
- **Stale data** — every mutation re-fetches the page props. No manual cache invalidation.

## 12. Testing strategy

- **Frontend**: `tsc --noEmit`, ESLint, Prettier — same as Plan 1. No JS unit tests in v1.
- **Backend additions** (one new controller for `/settings/mcp` + extending `DashboardController` to include `selectedList`, `reminders`, `completedReminders`): each gets a PHPUnit feature test covering happy path + auth gate + selected-list-out-of-scope (a user can't see another user's list via `?list=ID`).

## 13. Code organization principles

- One responsibility per component file. Target < ~150 LOC; flag and split if a file grows past that.
- Server-driven state (lists, reminders) lives on the page; children take props.
- Local UI state (editor open, highlight, drag-over) lives in the component that uses it. No global state library.
- Hooks for cross-cutting concerns (keyboard, click-outside, URL state).

## 14. Risks / things worth flagging

- **Wayfinder v0**: the package is at v0; if its API moves during this work, the import paths in §10 may need adjusting. We keep all router calls behind a single import per controller so a refactor is localized.
- **`@dnd-kit` + React 19**: confirmed compatible at the time of this design. If a peer-deps warning shows, prefer the latest tagged release.
- **Notes preview parity**: client never re-renders markdown live; users see the rendered HTML only after Save. Acceptable tradeoff for the "client never parses markdown" rule in `architecture.md` §10. Reconsider if users complain.
- **No in-app dark-mode toggle**: if you decide to add one later, it's a small follow-up (one button + localStorage persistence).

---

_Last reviewed: 2026-06-02._
