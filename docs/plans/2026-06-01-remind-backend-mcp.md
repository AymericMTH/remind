# Re:Mind Backend + MCP — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Re:Mind backend — data model, business logic actions, HTTP controllers, and the unauthenticated HTTP MCP server — such that a Claude Code MCP client can list/create/complete/update reminders end-to-end and feature tests prove every path. The Inertia/React dashboard ships in a follow-up plan; this plan delivers Inertia controllers but only a placeholder dashboard view.

**Architecture:** Single Laravel 13 app in Docker (FrankenPHP) with SQLite. Data is `User → ReminderList (hasMany) → Reminder (hasMany)`, all user-scoped. Business logic lives in single-purpose Action classes under `app/Actions/`. The HTTP MCP at `/mcp` (via `laravel/mcp`) and the Inertia controllers both call the same Actions — no logic duplication. MCP authentication is intentionally absent: a `SingleUserMiddleware` resolves `User::sole()` and binds it to the request.

**Tech Stack:** PHP 8.4 · Laravel 13 · Inertia v3 + React 19 · Tailwind v4 · `laravel/mcp` v0 · `laravel/fortify` v1 · `league/commonmark` · PHPUnit 12 · Pint · SQLite · FrankenPHP in Docker.

**Source of truth:** `docs/architecture.md` in this repo. Re-read sections referenced in each task before implementing.

---

## File map (what we'll create or touch)

```
docker-compose.yml                                 (new — FrankenPHP service)
Dockerfile                                         (new — pdo_sqlite + composer + npm)
.dockerignore                                      (new)

config/fortify.php                                 (modify — disable Registration feature)

database/migrations/
├── 0000_00_00_create_reminder_lists_table.php     (new)
└── 0000_00_00_create_reminders_table.php          (new)

database/factories/
├── ReminderListFactory.php                         (new)
└── ReminderFactory.php                             (new)

database/seeders/
├── DatabaseSeeder.php                              (modify — call RemindUserSeeder)
└── RemindUserSeeder.php                            (new — single user from env)

app/Models/
├── User.php                                        (modify — observe boot, add lists() relation)
├── ReminderList.php                                (new)
└── Reminder.php                                    (new)

app/Observers/UserObserver.php                     (new — auto-create Inbox)

app/Policies/
├── ReminderListPolicy.php                         (new)
└── ReminderPolicy.php                             (new)

app/Actions/
├── Lists/CreateList.php                            (new)
├── Lists/DeleteList.php                            (new — MoveToInbox|Cascade strategy)
├── Lists/ReorderLists.php                          (new)
├── Reminders/CreateReminder.php                    (new)
├── Reminders/UpdateReminder.php                    (new)
├── Reminders/CompleteReminder.php                  (new)
├── Reminders/ReorderReminders.php                  (new)
├── Reminders/NormalizeContext.php                  (new — pure)
└── Markdown/RenderNotes.php                        (new — CommonMark safe mode)

app/Http/Controllers/
├── DashboardController.php                         (new — Inertia placeholder)
├── ReminderListController.php                      (new)
└── ReminderController.php                          (new)

app/Http/Requests/
├── StoreReminderList.php                           (new)
├── UpdateReminderList.php                          (new)
├── StoreReminder.php                               (new)
└── UpdateReminder.php                              (new)

app/Mcp/
├── Servers/RemindServer.php                        (new — `make:mcp-server`)
├── Middleware/SingleUserMiddleware.php             (new — binds User::sole())
└── Tools/
    ├── ListProjects.php                            (new)
    ├── CreateProject.php                           (new)
    ├── AddReminder.php                             (new)
    ├── ListReminders.php                           (new)
    ├── CompleteReminder.php                        (new)
    └── UpdateReminder.php                          (new)

app/Console/Commands/PurgeTrash.php                 (new)
routes/console.php                                  (modify — schedule purge:trash daily)

routes/web.php                                      (modify — add dashboard/lists/reminders)
routes/ai.php                                       (new — published by laravel/mcp + Mcp::web)

resources/js/pages/dashboard/Index.tsx              (new — placeholder; full UI in Plan 2)

tests/Feature/
├── Models/InboxAutoCreatedTest.php
├── Models/CrossUserIsolationTest.php
├── Actions/Lists/CreateListTest.php
├── Actions/Lists/DeleteListTest.php
├── Actions/Reminders/NormalizeContextTest.php
├── Actions/Reminders/CreateReminderTest.php
├── Actions/Reminders/UpdateReminderTest.php
├── Actions/Reminders/CompleteReminderTest.php
├── Actions/Markdown/RenderNotesTest.php
├── Http/ReminderListControllerTest.php
├── Http/ReminderControllerTest.php
├── Mcp/ListProjectsToolTest.php
├── Mcp/CreateProjectToolTest.php
├── Mcp/AddReminderToolTest.php
├── Mcp/ListRemindersToolTest.php
├── Mcp/CompleteReminderToolTest.php
├── Mcp/UpdateReminderToolTest.php
├── Mcp/SingleUserMiddlewareTest.php
├── Mcp/EndToEndCaptureFlowTest.php
└── Console/PurgeTrashTest.php
```

**Pre-commit hygiene applied to every code task:**

- `vendor/bin/pint --dirty --format agent` after PHP edits.
- `php artisan test --compact --filter=<TestClass>` after each change; run the whole feature suite at the end of each phase.
- Frequent commits, one per task (≈ one passing test + the code that makes it pass).

---

## Phase 0 — Bootstrap & dependencies

### Task 1: Install `laravel/mcp` and `league/commonmark`; publish MCP routes

**Files:**
- Modify: `composer.json` (added requires)
- Create: `routes/ai.php` (published by laravel/mcp)

- [ ] **Step 1: Verify packages aren't already installed**

```bash
cd /home/aymeric/zWEB/remind
composer show laravel/mcp league/commonmark 2>&1 | head -20
```

Expected: either listed (skip install) or "package not found".

- [ ] **Step 2: Install both packages**

```bash
composer require laravel/mcp league/commonmark
```

- [ ] **Step 3: Publish the MCP routes file**

```bash
php artisan vendor:publish --tag=ai-routes
```

Expected: `routes/ai.php` created.

- [ ] **Step 4: Confirm the file exists and contains the boilerplate**

```bash
ls -la routes/ai.php
```

Expected: file present (a few lines, mostly the `use Laravel\Mcp\Facades\Mcp;` import).

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock routes/ai.php
git commit -m "chore: install laravel/mcp and league/commonmark, publish ai routes"
```

---

### Task 2: Docker setup (FrankenPHP + pdo_sqlite)

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `.dockerignore`
- Modify: `.env` (DB path, APP_URL)

- [ ] **Step 1: Create `Dockerfile`**

```dockerfile
# Dockerfile
FROM dunglas/frankenphp:1-php8.4

# pdo_sqlite + Composer + Node + npm
RUN install-php-extensions pdo_sqlite \
 && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
 && apt-get update && apt-get install -y --no-install-recommends nodejs npm git \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . /app

# Caddy needs to bind low ports; FrankenPHP's frankenphp user handles this.
ENV SERVER_NAME=:8000
EXPOSE 8000

# Composer + npm install + build run from compose up the first time.
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile"]
```

- [ ] **Step 2: Create `docker-compose.yml`**

```yaml
# docker-compose.yml
services:
  app:
    build: .
    container_name: remind
    ports:
      - "8000:8000"
    volumes:
      - .:/app
      - ./storage:/app/storage
    environment:
      APP_URL: http://localhost:8000
      DB_CONNECTION: sqlite
      DB_DATABASE: /app/database/database.sqlite
    working_dir: /app
```

- [ ] **Step 3: Create `.dockerignore`**

```
.git
.idea
.vscode
node_modules
vendor
public/build
public/hot
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
.superpowers
```

- [ ] **Step 4: Edit `.env` to ensure absolute DB path inside container**

Open `.env` and confirm:

```env
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
DB_DATABASE=/app/database/database.sqlite
```

- [ ] **Step 5: Build and start the container**

```bash
docker compose build
docker compose up -d
docker compose exec app php -v
docker compose exec app php -m | grep -i pdo_sqlite
```

Expected: PHP 8.4 prints; `pdo_sqlite` listed.

- [ ] **Step 6: Run composer install + migrate inside the container**

```bash
docker compose exec app composer install --no-interaction
docker compose exec app php artisan key:generate
docker compose exec app touch database/database.sqlite
docker compose exec app php artisan migrate --force
```

Expected: migration table created plus the default Laravel migrations applied.

- [ ] **Step 7: Smoke-test HTTP**

```bash
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8000/
```

Expected: `200`.

- [ ] **Step 8: Commit**

```bash
git add Dockerfile docker-compose.yml .dockerignore .env.example
git commit -m "chore: dockerize app with FrankenPHP + sqlite"
```

(We commit `.env.example`, not `.env`. Update `.env.example` to match the new keys.)

---

### Task 3: Disable Fortify registration; seed the single user

**Files:**
- Modify: `config/fortify.php`
- Create: `database/seeders/RemindUserSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Edit `config/fortify.php` — remove `Features::registration()`**

Find the `'features' => [` block. Remove the line `Features::registration(),` (keep other features intact).

- [ ] **Step 2: Write the seeder**

```php
<?php
// database/seeders/RemindUserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RemindUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('remind.bootstrap_email');
        $name = config('remind.bootstrap_name');
        $password = config('remind.bootstrap_password');

        if (! $email || ! $password) {
            $this->command->warn('Skipping RemindUserSeeder: set REMIND_USER_EMAIL and REMIND_USER_PASSWORD in .env');
            return;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name ?? 'Re:Mind user', 'password' => Hash::make($password)],
        );
    }
}
```

- [ ] **Step 3: Register a tiny `config/remind.php`**

```php
<?php
// config/remind.php

return [
    'bootstrap_email' => env('REMIND_USER_EMAIL'),
    'bootstrap_name' => env('REMIND_USER_NAME', 'Re:Mind user'),
    'bootstrap_password' => env('REMIND_USER_PASSWORD'),
];
```

- [ ] **Step 4: Wire seeder into `DatabaseSeeder`**

```php
// database/seeders/DatabaseSeeder.php
public function run(): void
{
    $this->call(RemindUserSeeder::class);
}
```

- [ ] **Step 5: Add env keys to `.env.example`**

```env
REMIND_USER_NAME="Aymeric"
REMIND_USER_EMAIL="you@example.com"
REMIND_USER_PASSWORD="change-me"
```

- [ ] **Step 6: Run the seeder against your dev env**

```bash
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan tinker --execute 'echo \App\Models\User::count();'
```

Expected: `1`.

- [ ] **Step 7: Commit**

```bash
git add config/fortify.php config/remind.php database/seeders/ .env.example
git commit -m "feat: disable registration, seed bootstrap user from env"
```

---

## Phase 1 — Data model & isolation

### Task 4: `reminder_lists` migration + `ReminderList` model + factory

**Files:**
- Create: `database/migrations/0000_00_00_create_reminder_lists_table.php`
- Create: `app/Models/ReminderList.php`
- Create: `database/factories/ReminderListFactory.php`
- Create: `tests/Feature/Models/CrossUserIsolationTest.php`

- [ ] **Step 1: Generate the migration file**

```bash
docker compose exec app php artisan make:migration create_reminder_lists_table --create=reminder_lists
```

- [ ] **Step 2: Write the migration**

```php
<?php
// database/migrations/...create_reminder_lists_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reminder_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('color', 9)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_inbox')->default(false);
            $table->timestamps();
            $table->index('user_id');
            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_lists');
    }
};
```

- [ ] **Step 3: Generate model + factory**

```bash
docker compose exec app php artisan make:model ReminderList --factory
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/ReminderList.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderList extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'color', 'position', 'is_inbox'];

    protected $casts = [
        'is_inbox' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'list_id');
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php
// database/factories/ReminderListFactory.php

namespace Database\Factories;

use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderListFactory extends Factory
{
    protected $model = ReminderList::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(2, true),
            'color' => fake()->hexColor(),
            'position' => 0,
            'is_inbox' => false,
        ];
    }

    public function inbox(): static
    {
        return $this->state(fn () => ['name' => 'Inbox', 'is_inbox' => true]);
    }
}
```

- [ ] **Step 6: Add `lists()` relation to `User`**

In `app/Models/User.php`, add:

```php
use Illuminate\Database\Eloquent\Relations\HasMany;

public function lists(): HasMany
{
    return $this->hasMany(ReminderList::class);
}
```

- [ ] **Step 7: Migrate and verify**

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tinker --execute 'echo \Schema::hasTable("reminder_lists") ? "ok" : "missing";'
```

Expected: `ok`.

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models database/factories
git commit -m "feat(model): ReminderList — migration, model, factory"
```

---

### Task 5: `reminders` migration + `Reminder` model + factory

**Files:**
- Create: `database/migrations/0000_00_00_create_reminders_table.php`
- Create: `app/Models/Reminder.php`
- Create: `database/factories/ReminderFactory.php`

- [ ] **Step 1: Generate the migration**

```bash
docker compose exec app php artisan make:migration create_reminders_table --create=reminders
```

- [ ] **Step 2: Write the migration**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('reminder_lists')->restrictOnDelete();
            $table->string('title', 200);
            $table->text('notes')->nullable();
            $table->date('soft_due_date')->nullable();
            $table->json('context')->nullable();
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['user_id', 'list_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
```

- [ ] **Step 3: Generate model**

```bash
docker compose exec app php artisan make:model Reminder --factory
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/Reminder.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reminder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_OPEN = 'open';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'user_id', 'list_id', 'title', 'notes',
        'soft_due_date', 'context', 'status', 'completed_at', 'position',
    ];

    protected $casts = [
        'soft_due_date' => 'date',
        'context' => 'array',
        'completed_at' => 'datetime',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function list(): BelongsTo
    {
        return $this->belongsTo(ReminderList::class, 'list_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }
}
```

- [ ] **Step 5: Write the factory**

```php
<?php
// database/factories/ReminderFactory.php

namespace Database\Factories;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReminderFactory extends Factory
{
    protected $model = Reminder::class;

    public function definition(): array
    {
        $user = User::factory();
        return [
            'user_id' => $user,
            'list_id' => ReminderList::factory()->state(fn (array $attrs, $parent) => ['user_id' => $parent?->user_id]),
            'title' => fake()->sentence(5),
            'notes' => null,
            'soft_due_date' => null,
            'context' => null,
            'status' => Reminder::STATUS_OPEN,
            'position' => 0,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => Reminder::STATUS_DONE,
            'completed_at' => now(),
        ]);
    }
}
```

- [ ] **Step 6: Migrate and verify**

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan tinker --execute 'echo \Schema::hasTable("reminders") ? "ok" : "missing";'
```

Expected: `ok`.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/Reminder.php database/factories
git commit -m "feat(model): Reminder — migration, model, factory"
```

---

### Task 6: Auto-create Inbox via UserObserver

**Files:**
- Create: `app/Observers/UserObserver.php`
- Modify: `app/Providers/AppServiceProvider.php` (register observer)
- Create: `tests/Feature/Models/InboxAutoCreatedTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Models/InboxAutoCreatedTest.php

namespace Tests\Feature\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxAutoCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_user_creates_an_inbox_list(): void
    {
        $user = User::factory()->create();

        $this->assertSame(1, $user->lists()->count());
        $inbox = $user->lists()->first();
        $this->assertTrue($inbox->is_inbox);
        $this->assertSame('Inbox', $inbox->name);
    }

    public function test_inbox_is_created_at_position_zero(): void
    {
        $user = User::factory()->create();
        $this->assertSame(0, $user->lists()->where('is_inbox', true)->first()->position);
    }

    public function test_each_user_gets_their_own_inbox(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->assertNotSame(
            $a->lists()->where('is_inbox', true)->first()->id,
            $b->lists()->where('is_inbox', true)->first()->id,
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
docker compose exec app php artisan test --compact --filter=InboxAutoCreatedTest
```

Expected: FAIL — no lists created.

- [ ] **Step 3: Write the observer**

```php
<?php
// app/Observers/UserObserver.php

namespace App\Observers;

use App\Models\ReminderList;
use App\Models\User;

class UserObserver
{
    public function created(User $user): void
    {
        ReminderList::query()->create([
            'user_id' => $user->id,
            'name' => 'Inbox',
            'is_inbox' => true,
            'position' => 0,
        ]);
    }
}
```

- [ ] **Step 4: Register the observer**

In `app/Providers/AppServiceProvider.php`, inside `boot()`:

```php
use App\Models\User;
use App\Observers\UserObserver;

User::observe(UserObserver::class);
```

- [ ] **Step 5: Run the test to verify it passes**

```bash
docker compose exec app php artisan test --compact --filter=InboxAutoCreatedTest
```

Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Observers app/Providers tests/Feature/Models/InboxAutoCreatedTest.php
git commit -m "feat(model): auto-create Inbox list when a User is created"
```

---

### Task 7: Policies + cross-user isolation feature test

**Files:**
- Create: `app/Policies/ReminderListPolicy.php`
- Create: `app/Policies/ReminderPolicy.php`
- Create: `tests/Feature/Models/CrossUserIsolationTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Models/CrossUserIsolationTest.php

namespace Tests\Feature\Models;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossUserIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_a_cannot_view_user_bs_list(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('view', $list));
        $this->assertTrue($b->can('view', $list));
    }

    public function test_user_a_cannot_update_user_bs_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('update', $reminder));
        $this->assertTrue($b->can('update', $reminder));
    }

    public function test_user_a_cannot_delete_user_bs_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $b->id]);

        $this->assertFalse($a->can('delete', $reminder));
        $this->assertTrue($b->can('delete', $reminder));
    }
}
```

- [ ] **Step 2: Run it to verify failure**

```bash
docker compose exec app php artisan test --compact --filter=CrossUserIsolationTest
```

Expected: FAIL — policies missing.

- [ ] **Step 3: Write the policies**

```php
<?php
// app/Policies/ReminderListPolicy.php

namespace App\Policies;

use App\Models\ReminderList;
use App\Models\User;

class ReminderListPolicy
{
    public function view(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id;
    }

    public function update(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id && ! $list->is_inbox;
    }

    public function delete(User $user, ReminderList $list): bool
    {
        return $list->user_id === $user->id && ! $list->is_inbox;
    }
}
```

```php
<?php
// app/Policies/ReminderPolicy.php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy
{
    public function view(User $user, Reminder $reminder): bool
    {
        return $reminder->user_id === $user->id;
    }

    public function update(User $user, Reminder $reminder): bool
    {
        return $reminder->user_id === $user->id;
    }

    public function delete(User $user, Reminder $reminder): bool
    {
        return $reminder->user_id === $user->id;
    }
}
```

Laravel auto-resolves `ReminderListPolicy` to `ReminderList` and `ReminderPolicy` to `Reminder` by convention; no manual registration needed.

- [ ] **Step 4: Run the test to verify pass**

```bash
docker compose exec app php artisan test --compact --filter=CrossUserIsolationTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Policies tests/Feature/Models/CrossUserIsolationTest.php
git commit -m "feat(policy): scope lists and reminders to their owning user"
```

---

## Phase 2 — Action classes (business logic)

### Task 8: `NormalizeContext` — pure action

**Files:**
- Create: `app/Actions/Reminders/NormalizeContext.php`
- Create: `tests/Feature/Actions/Reminders/NormalizeContextTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Actions/Reminders/NormalizeContextTest.php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\NormalizeContext;
use Tests\TestCase;

class NormalizeContextTest extends TestCase
{
    private NormalizeContext $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new NormalizeContext();
    }

    public function test_returns_null_when_input_is_null(): void
    {
        $this->assertNull($this->action->run(null));
    }

    public function test_derives_repo_label_from_github_ssh_url(): void
    {
        $out = $this->action->run(['repo' => 'git@github.com:foo/bar.git']);
        $this->assertSame('foo/bar', $out['repo_label']);
    }

    public function test_derives_repo_label_from_https_github_url(): void
    {
        $out = $this->action->run(['repo' => 'https://github.com/foo/bar.git']);
        $this->assertSame('foo/bar', $out['repo_label']);
    }

    public function test_falls_back_to_cwd_basename_when_repo_unparseable(): void
    {
        $out = $this->action->run(['repo' => '/abs/local/path', 'cwd' => '/home/me/projects/widget']);
        $this->assertSame('widget', $out['repo_label']);
    }

    public function test_drops_unknown_keys(): void
    {
        $out = $this->action->run(['repo' => 'x', 'cwd' => '/a/b', 'evil' => 'no']);
        $this->assertArrayNotHasKey('evil', $out);
    }

    public function test_preserves_known_keys(): void
    {
        $out = $this->action->run([
            'repo' => 'git@github.com:foo/bar.git',
            'branch' => 'main',
            'file' => 'app/X.php',
            'line_start' => 10,
            'line_end' => 20,
            'cwd' => '/x',
        ]);
        $this->assertSame('main', $out['branch']);
        $this->assertSame('app/X.php', $out['file']);
        $this->assertSame(10, $out['line_start']);
        $this->assertSame(20, $out['line_end']);
    }
}
```

- [ ] **Step 2: Run it to verify failure**

```bash
docker compose exec app php artisan test --compact --filter=NormalizeContextTest
```

Expected: FAIL — class missing.

- [ ] **Step 3: Implement the action**

```php
<?php
// app/Actions/Reminders/NormalizeContext.php

namespace App\Actions\Reminders;

class NormalizeContext
{
    private const ALLOWED_KEYS = ['repo', 'repo_label', 'branch', 'file', 'line_start', 'line_end', 'cwd'];

    /**
     * @param array<string,mixed>|null $context
     * @return array<string,mixed>|null
     */
    public function run(?array $context): ?array
    {
        if ($context === null) {
            return null;
        }

        $out = array_intersect_key($context, array_flip(self::ALLOWED_KEYS));

        if (! isset($out['repo_label'])) {
            $out['repo_label'] = $this->deriveLabel($out['repo'] ?? null, $out['cwd'] ?? null);
        }

        return $out;
    }

    private function deriveLabel(?string $repo, ?string $cwd): ?string
    {
        if ($repo) {
            if (preg_match('#[:/]([^/:]+)/([^/]+?)(?:\.git)?$#', $repo, $m)) {
                return $m[1].'/'.$m[2];
            }
        }
        return $cwd ? basename($cwd) : null;
    }
}
```

- [ ] **Step 4: Run the test to verify pass**

```bash
docker compose exec app php artisan test --compact --filter=NormalizeContextTest
```

Expected: PASS (6 tests).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Reminders/NormalizeContext.php tests/Feature/Actions/Reminders/NormalizeContextTest.php
git commit -m "feat(actions): NormalizeContext — derive repo_label, drop unknown keys"
```

---

### Task 9: `CreateList` action

**Files:**
- Create: `app/Actions/Lists/CreateList.php`
- Create: `tests/Feature/Actions/Lists/CreateListTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Actions/Lists/CreateListTest.php

namespace Tests\Feature\Actions\Lists;

use App\Actions\Lists\CreateList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateListTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_list_at_next_position(): void
    {
        $user = User::factory()->create(); // observer creates Inbox at position 0
        $list = (new CreateList())->run($user, ['name' => 'Work', 'color' => '#7aa2f7']);

        $this->assertSame('Work', $list->name);
        $this->assertSame('#7aa2f7', $list->color);
        $this->assertSame(1, $list->position);
        $this->assertFalse($list->is_inbox);
    }

    public function test_rejects_duplicate_name_per_user_case_insensitive(): void
    {
        $user = User::factory()->create();
        (new CreateList())->run($user, ['name' => 'Work']);

        $this->expectException(ValidationException::class);
        (new CreateList())->run($user, ['name' => 'work']);
    }

    public function test_two_users_can_each_have_a_list_named_work(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        (new CreateList())->run($a, ['name' => 'Work']);
        $list = (new CreateList())->run($b, ['name' => 'Work']);
        $this->assertSame($b->id, $list->user_id);
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose exec app php artisan test --compact --filter=CreateListTest
```

Expected: FAIL — class missing.

- [ ] **Step 3: Implement the action**

```php
<?php
// app/Actions/Lists/CreateList.php

namespace App\Actions\Lists;

use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateList
{
    /**
     * @param array{name:string,color?:?string} $attrs
     */
    public function run(User $user, array $attrs): ReminderList
    {
        $data = Validator::make($attrs, [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:9', 'regex:/^#?[0-9a-fA-F]{3,8}$/'],
        ])->validate();

        $existing = $user->lists()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->exists();

        if ($existing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'A list with this name already exists.',
            ]);
        }

        $nextPosition = (int) $user->lists()->max('position') + 1;

        return $user->lists()->create([
            'name' => $data['name'],
            'color' => $data['color'] ?? null,
            'position' => $nextPosition,
            'is_inbox' => false,
        ]);
    }
}
```

- [ ] **Step 4: Run to verify pass**

```bash
docker compose exec app php artisan test --compact --filter=CreateListTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Lists/CreateList.php tests/Feature/Actions/Lists/CreateListTest.php
git commit -m "feat(actions): CreateList — unique-per-user, auto position"
```

---

### Task 10: `DeleteList` with `MoveToInbox` / `Cascade` strategy

**Files:**
- Create: `app/Actions/Lists/DeleteList.php`
- Create: `app/Actions/Lists/DeleteStrategy.php` (enum)
- Create: `tests/Feature/Actions/Lists/DeleteListTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Actions/Lists/DeleteListTest.php

namespace Tests\Feature\Actions\Lists;

use App\Actions\Lists\DeleteList;
use App\Actions\Lists\DeleteStrategy;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteListTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_to_inbox_moves_children_then_deletes_list(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(3)->create(['user_id' => $user->id, 'list_id' => $list->id]);

        (new DeleteList())->run($list, DeleteStrategy::MoveToInbox);

        $this->assertNull(ReminderList::find($list->id));
        $inbox = $user->lists()->where('is_inbox', true)->first();
        $this->assertSame(3, $inbox->reminders()->count());
    }

    public function test_cascade_hard_deletes_children_and_list(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $list->id]);

        (new DeleteList())->run($list, DeleteStrategy::Cascade);

        $this->assertNull(ReminderList::find($list->id));
        $this->assertSame(0, Reminder::withTrashed()->where('list_id', $list->id)->count());
    }

    public function test_inbox_cannot_be_deleted(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();
        $this->expectException(\DomainException::class);
        (new DeleteList())->run($inbox, DeleteStrategy::Cascade);
    }
}
```

- [ ] **Step 2: Run to verify failure**

```bash
docker compose exec app php artisan test --compact --filter=DeleteListTest
```

Expected: FAIL.

- [ ] **Step 3: Implement**

```php
<?php
// app/Actions/Lists/DeleteStrategy.php

namespace App\Actions\Lists;

enum DeleteStrategy: string
{
    case MoveToInbox = 'move_to_inbox';
    case Cascade = 'cascade';
}
```

```php
<?php
// app/Actions/Lists/DeleteList.php

namespace App\Actions\Lists;

use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Support\Facades\DB;

class DeleteList
{
    public function run(ReminderList $list, DeleteStrategy $strategy): void
    {
        if ($list->is_inbox) {
            throw new \DomainException('Inbox cannot be deleted.');
        }

        DB::transaction(function () use ($list, $strategy) {
            if ($strategy === DeleteStrategy::MoveToInbox) {
                $inboxId = $list->user->lists()->where('is_inbox', true)->value('id');
                $list->reminders()->update(['list_id' => $inboxId]);
            } else {
                // Hard-delete children (force, bypassing soft delete).
                Reminder::withTrashed()->where('list_id', $list->id)->forceDelete();
            }

            $list->delete();
        });
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

```bash
docker compose exec app php artisan test --compact --filter=DeleteListTest
```

Expected: PASS (3 tests).

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Lists tests/Feature/Actions/Lists/DeleteListTest.php
git commit -m "feat(actions): DeleteList with MoveToInbox|Cascade strategies"
```

---

### Task 11: `CreateReminder` action

**Files:**
- Create: `app/Actions/Reminders/CreateReminder.php`
- Create: `tests/Feature/Actions/Reminders/CreateReminderTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Actions/Reminders/CreateReminderTest.php

namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\CreateReminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreateReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_reminder_with_normalized_context(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first(); // Inbox

        $reminder = (new CreateReminder())->run($user, [
            'list_id' => $list->id,
            'title' => 'Refactor MCP tool',
            'notes' => 'Use Action classes.',
            'context' => ['repo' => 'git@github.com:foo/bar.git', 'file' => 'app/X.php'],
        ]);

        $this->assertSame('Refactor MCP tool', $reminder->title);
        $this->assertSame('open', $reminder->status);
        $this->assertSame('foo/bar', $reminder->context['repo_label']);
    }

    public function test_assigns_next_position_within_list(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $a = (new CreateReminder())->run($user, ['list_id' => $list->id, 'title' => 'A']);
        $b = (new CreateReminder())->run($user, ['list_id' => $list->id, 'title' => 'B']);
        $this->assertGreaterThan($a->position, $b->position);
    }

    public function test_rejects_list_owned_by_another_user(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->expectException(ValidationException::class);
        (new CreateReminder())->run($a, ['list_id' => $bsList->id, 'title' => 'X']);
    }
}
```

- [ ] **Step 2: Run — fails**

```bash
docker compose exec app php artisan test --compact --filter=CreateReminderTest
```

- [ ] **Step 3: Implement**

```php
<?php
// app/Actions/Reminders/CreateReminder.php

namespace App\Actions\Reminders;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CreateReminder
{
    public function __construct(private NormalizeContext $normalizeContext = new NormalizeContext()) {}

    /**
     * @param array{
     *     list_id:int,
     *     title:string,
     *     notes?:?string,
     *     soft_due_date?:?string,
     *     context?:?array
     * } $attrs
     */
    public function run(User $user, array $attrs): Reminder
    {
        $data = Validator::make($attrs, [
            'list_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'soft_due_date' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
        ])->validate();

        $list = ReminderList::query()
            ->where('id', $data['list_id'])
            ->where('user_id', $user->id)
            ->first();

        if (! $list) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'list_id' => 'List not found.',
            ]);
        }

        $position = (int) $list->reminders()->max('position') + 1;

        return $user->reminders()->create([
            'list_id' => $list->id,
            'title' => $data['title'],
            'notes' => $data['notes'] ?? null,
            'soft_due_date' => $data['soft_due_date'] ?? null,
            'context' => $this->normalizeContext->run($data['context'] ?? null),
            'status' => Reminder::STATUS_OPEN,
            'position' => $position,
        ]);
    }
}
```

- [ ] **Step 4: Add `reminders()` to User**

In `app/Models/User.php`:

```php
public function reminders(): \Illuminate\Database\Eloquent\Relations\HasMany
{
    return $this->hasMany(\App\Models\Reminder::class);
}
```

- [ ] **Step 5: Run tests — pass**

```bash
docker compose exec app php artisan test --compact --filter=CreateReminderTest
```

Expected: PASS (3 tests).

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Reminders/CreateReminder.php app/Models/User.php tests/Feature/Actions/Reminders/CreateReminderTest.php
git commit -m "feat(actions): CreateReminder — list scoping, normalized context, auto position"
```

---

### Task 12: `UpdateReminder` action

**Files:**
- Create: `app/Actions/Reminders/UpdateReminder.php`
- Create: `tests/Feature/Actions/Reminders/UpdateReminderTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\UpdateReminder;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdateReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_notes_and_due_date(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $updated = (new UpdateReminder())->run($reminder, [
            'title' => 'Edited',
            'notes' => 'New notes',
            'soft_due_date' => '2026-07-04',
        ]);

        $this->assertSame('Edited', $updated->title);
        $this->assertSame('New notes', $updated->notes);
        $this->assertSame('2026-07-04', $updated->soft_due_date->toDateString());
    }

    public function test_can_move_to_another_list_owned_by_same_user(): void
    {
        $user = User::factory()->create();
        $a = $user->lists()->first();
        $b = ReminderList::factory()->create(['user_id' => $user->id]);
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $a->id]);

        (new UpdateReminder())->run($reminder, ['list_id' => $b->id]);

        $this->assertSame($b->id, $reminder->fresh()->list_id);
    }

    public function test_rejects_move_to_list_owned_by_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);
        $otherList = ReminderList::factory()->create(['user_id' => $other->id]);

        $this->expectException(ValidationException::class);
        (new UpdateReminder())->run($reminder, ['list_id' => $otherList->id]);
    }

    public function test_normalizes_context_on_update(): void
    {
        $user = User::factory()->create();
        $reminder = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new UpdateReminder())->run($reminder, [
            'context' => ['repo' => 'git@github.com:foo/bar.git'],
        ]);

        $this->assertSame('foo/bar', $reminder->fresh()->context['repo_label']);
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=UpdateReminderTest
```

- [ ] **Step 3: Implement**

```php
<?php
// app/Actions/Reminders/UpdateReminder.php

namespace App\Actions\Reminders;

use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Support\Facades\Validator;

class UpdateReminder
{
    public function __construct(private NormalizeContext $normalizeContext = new NormalizeContext()) {}

    public function run(Reminder $reminder, array $attrs): Reminder
    {
        $data = Validator::make($attrs, [
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'soft_due_date' => ['sometimes', 'nullable', 'date'],
            'context' => ['sometimes', 'nullable', 'array'],
            'list_id' => ['sometimes', 'integer'],
        ])->validate();

        if (isset($data['list_id'])) {
            $list = ReminderList::query()
                ->where('id', $data['list_id'])
                ->where('user_id', $reminder->user_id)
                ->first();
            if (! $list) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'list_id' => 'List not found.',
                ]);
            }
            $reminder->list_id = $list->id;
        }

        if (array_key_exists('title', $data)) $reminder->title = $data['title'];
        if (array_key_exists('notes', $data)) $reminder->notes = $data['notes'];
        if (array_key_exists('soft_due_date', $data)) $reminder->soft_due_date = $data['soft_due_date'];
        if (array_key_exists('context', $data)) $reminder->context = $this->normalizeContext->run($data['context']);

        $reminder->save();
        return $reminder;
    }
}
```

- [ ] **Step 4: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=UpdateReminderTest
```

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Reminders/UpdateReminder.php tests/Feature/Actions/Reminders/UpdateReminderTest.php
git commit -m "feat(actions): UpdateReminder — partial updates, list-move scoping, context normalize"
```

---

### Task 13: `CompleteReminder` action

**Files:**
- Create: `app/Actions/Reminders/CompleteReminder.php`
- Create: `tests/Feature/Actions/Reminders/CompleteReminderTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
namespace Tests\Feature\Actions\Reminders;

use App\Actions\Reminders\CompleteReminder;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_sets_status_and_completed_at(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new CompleteReminder())->run($r, true);

        $this->assertSame('done', $r->fresh()->status);
        $this->assertNotNull($r->fresh()->completed_at);
    }

    public function test_uncomplete_clears_completed_at(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->done()->create([
            'user_id' => $user->id, 'list_id' => $user->lists()->first()->id,
        ]);

        (new CompleteReminder())->run($r, false);

        $this->assertSame('open', $r->fresh()->status);
        $this->assertNull($r->fresh()->completed_at);
    }

    public function test_complete_is_idempotent(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        (new CompleteReminder())->run($r, true);
        $firstAt = $r->fresh()->completed_at;
        (new CompleteReminder())->run($r, true);
        $this->assertTrue($firstAt->equalTo($r->fresh()->completed_at));
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=CompleteReminderTest
```

- [ ] **Step 3: Implement**

```php
<?php
// app/Actions/Reminders/CompleteReminder.php

namespace App\Actions\Reminders;

use App\Models\Reminder;

class CompleteReminder
{
    public function run(Reminder $reminder, bool $done): Reminder
    {
        if ($done && $reminder->status === Reminder::STATUS_DONE) {
            return $reminder; // idempotent
        }

        $reminder->status = $done ? Reminder::STATUS_DONE : Reminder::STATUS_OPEN;
        $reminder->completed_at = $done ? now() : null;
        $reminder->save();
        return $reminder;
    }
}
```

- [ ] **Step 4: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=CompleteReminderTest
```

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Reminders/CompleteReminder.php tests/Feature/Actions/Reminders/CompleteReminderTest.php
git commit -m "feat(actions): CompleteReminder — set/clear completed_at idempotently"
```

---

## Phase 3 — Inertia/HTTP controllers

> These render JSON-ish Inertia responses now even though the React UI is in Plan 2. Feature tests assert behavior and policies.

### Task 14: `ReminderListController` + form requests

**Files:**
- Create: `app/Http/Controllers/ReminderListController.php`
- Create: `app/Http/Requests/StoreReminderList.php`
- Create: `app/Http/Requests/UpdateReminderList.php`
- Create: `app/Actions/Lists/ReorderLists.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Http/ReminderListControllerTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Http/ReminderListControllerTest.php

namespace Tests\Feature\Http;

use App\Actions\Lists\DeleteStrategy;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderListControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->post('/lists', ['name' => 'X'])->assertRedirect('/login');
    }

    public function test_authenticated_user_can_create_a_list(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/lists', ['name' => 'Work'])
            ->assertRedirect();
        $this->assertSame(2, $user->lists()->count()); // Inbox + Work
    }

    public function test_user_cannot_delete_inbox(): void
    {
        $user = User::factory()->create();
        $inbox = $user->lists()->where('is_inbox', true)->first();

        $this->actingAs($user)
            ->delete("/lists/{$inbox->id}", ['strategy' => 'cascade'])
            ->assertForbidden();
    }

    public function test_user_cannot_delete_another_users_list(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $bsList = ReminderList::factory()->create(['user_id' => $b->id]);

        $this->actingAs($a)
            ->delete("/lists/{$bsList->id}", ['strategy' => 'cascade'])
            ->assertForbidden();
    }

    public function test_user_can_reorder_lists(): void
    {
        $user = User::factory()->create();
        $a = ReminderList::factory()->create(['user_id' => $user->id, 'position' => 1]);
        $b = ReminderList::factory()->create(['user_id' => $user->id, 'position' => 2]);

        $this->actingAs($user)
            ->put('/lists/reorder', ['order' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertLessThan($a->fresh()->position, $b->fresh()->position);
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=ReminderListControllerTest
```

- [ ] **Step 3: Form requests**

```php
<?php
// app/Http/Requests/StoreReminderList.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReminderList extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:9'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/UpdateReminderList.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReminderList extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('update', $this->route('list')); }
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:80'],
            'color' => ['sometimes', 'nullable', 'string', 'max:9'],
        ];
    }
}
```

- [ ] **Step 4: `ReorderLists` action**

```php
<?php
// app/Actions/Lists/ReorderLists.php

namespace App\Actions\Lists;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReorderLists
{
    /** @param array<int,int> $orderedIds */
    public function run(User $user, array $orderedIds): void
    {
        $owned = $user->lists()->whereIn('id', $orderedIds)->pluck('id')->all();
        $ids = array_values(array_intersect($orderedIds, $owned));
        DB::transaction(function () use ($ids, $user) {
            foreach ($ids as $i => $id) {
                $user->lists()->whereKey($id)->update(['position' => $i]);
            }
        });
    }
}
```

- [ ] **Step 5: Controller**

```php
<?php
// app/Http/Controllers/ReminderListController.php

namespace App\Http\Controllers;

use App\Actions\Lists\CreateList;
use App\Actions\Lists\DeleteList;
use App\Actions\Lists\DeleteStrategy;
use App\Actions\Lists\ReorderLists;
use App\Http\Requests\StoreReminderList;
use App\Http\Requests\UpdateReminderList;
use App\Models\ReminderList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderListController extends Controller
{
    public function store(StoreReminderList $request, CreateList $action): RedirectResponse
    {
        $action->run($request->user(), $request->validated());
        return redirect()->back();
    }

    public function update(UpdateReminderList $request, ReminderList $list): RedirectResponse
    {
        $list->update($request->validated());
        return redirect()->back();
    }

    public function destroy(Request $request, ReminderList $list, DeleteList $action): RedirectResponse
    {
        $this->authorize('delete', $list);
        $strategy = DeleteStrategy::from($request->input('strategy', 'move_to_inbox'));
        $action->run($list, $strategy);
        return redirect()->route('dashboard');
    }

    public function reorder(Request $request, ReorderLists $action): RedirectResponse
    {
        $action->run(
            $request->user(),
            collect($request->input('order', []))->map(fn ($x) => (int) $x)->all(),
        );
        return redirect()->back();
    }
}
```

- [ ] **Step 6: Routes**

In `routes/web.php`:

```php
use App\Http\Controllers\ReminderListController;

Route::middleware('auth')->group(function () {
    Route::get('/', \App\Http\Controllers\DashboardController::class)->name('dashboard');

    Route::post('/lists', [ReminderListController::class, 'store'])->name('lists.store');
    Route::put('/lists/reorder', [ReminderListController::class, 'reorder'])->name('lists.reorder');
    Route::put('/lists/{list}', [ReminderListController::class, 'update'])->name('lists.update');
    Route::delete('/lists/{list}', [ReminderListController::class, 'destroy'])->name('lists.destroy');
});
```

- [ ] **Step 7: Placeholder dashboard controller + Inertia page**

```php
<?php
// app/Http/Controllers/DashboardController.php
namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard/Index', [
            'lists' => auth()->user()->lists()->orderBy('position')->get(),
        ]);
    }
}
```

```tsx
// resources/js/pages/dashboard/Index.tsx
import { Head } from '@inertiajs/react';

export default function Dashboard({ lists }: { lists: Array<{ id: number; name: string }> }) {
    return (
        <>
            <Head title="Re:Mind" />
            <div className="p-6">
                <h1 className="text-xl font-semibold">Re:Mind</h1>
                <ul className="mt-4 space-y-1">
                    {lists.map((l) => (
                        <li key={l.id}>📋 {l.name}</li>
                    ))}
                </ul>
                <p className="mt-6 text-sm text-gray-500">Full dashboard ships in Plan 2.</p>
            </div>
        </>
    );
}
```

- [ ] **Step 8: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=ReminderListControllerTest
```

Expected: PASS (5 tests).

- [ ] **Step 9: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http app/Actions/Lists/ReorderLists.php routes/web.php resources/js/pages/dashboard tests/Feature/Http
git commit -m "feat(http): ReminderListController + reorder; placeholder dashboard page"
```

---

### Task 15: `ReminderController` + form requests

**Files:**
- Create: `app/Http/Controllers/ReminderController.php`
- Create: `app/Http/Requests/StoreReminder.php`
- Create: `app/Http/Requests/UpdateReminder.php`
- Create: `app/Actions/Reminders/ReorderReminders.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Http/ReminderControllerTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Http/ReminderControllerTest.php

namespace Tests\Feature\Http;

use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReminderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_create(): void
    {
        $this->post('/reminders', ['title' => 'X', 'list_id' => 1])->assertRedirect('/login');
    }

    public function test_create_persists_reminder(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $this->actingAs($user)
            ->post('/reminders', ['list_id' => $list->id, 'title' => 'A'])
            ->assertRedirect();
        $this->assertSame(1, $user->reminders()->count());
    }

    public function test_cannot_update_another_users_reminder(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $b->id, 'list_id' => $b->lists()->first()->id]);

        $this->actingAs($a)
            ->put("/reminders/{$r->id}", ['title' => 'hax'])
            ->assertForbidden();
    }

    public function test_complete_toggles_status(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $this->actingAs($user)->post("/reminders/{$r->id}/complete", ['done' => true])->assertRedirect();
        $this->assertSame('done', $r->fresh()->status);

        $this->actingAs($user)->post("/reminders/{$r->id}/complete", ['done' => false])->assertRedirect();
        $this->assertSame('open', $r->fresh()->status);
    }

    public function test_destroy_soft_deletes(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $this->actingAs($user)->delete("/reminders/{$r->id}")->assertRedirect();
        $this->assertNotNull(Reminder::withTrashed()->find($r->id)->deleted_at);
    }

    public function test_reorder_updates_positions(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        $a = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'position' => 1]);
        $b = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'position' => 2]);

        $this->actingAs($user)
            ->put('/reminders/reorder', ['list_id' => $list->id, 'order' => [$b->id, $a->id]])
            ->assertRedirect();

        $this->assertLessThan($a->fresh()->position, $b->fresh()->position);
    }
}
```

- [ ] **Step 2: Run — fail**

```bash
docker compose exec app php artisan test --compact --filter=ReminderControllerTest
```

- [ ] **Step 3: Form requests**

```php
<?php
// app/Http/Requests/StoreReminder.php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class StoreReminder extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'list_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:200'],
            'notes' => ['nullable', 'string'],
            'soft_due_date' => ['nullable', 'date'],
            'context' => ['nullable', 'array'],
        ];
    }
}
```

```php
<?php
// app/Http/Requests/UpdateReminder.php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReminder extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('update', $this->route('reminder')); }
    public function rules(): array
    {
        return [
            'list_id' => ['sometimes', 'integer'],
            'title' => ['sometimes', 'string', 'max:200'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'soft_due_date' => ['sometimes', 'nullable', 'date'],
            'context' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
```

- [ ] **Step 4: `ReorderReminders` action**

```php
<?php
// app/Actions/Reminders/ReorderReminders.php
namespace App\Actions\Reminders;

use App\Models\ReminderList;
use Illuminate\Support\Facades\DB;

class ReorderReminders
{
    /** @param array<int,int> $orderedIds */
    public function run(ReminderList $list, array $orderedIds): void
    {
        $owned = $list->reminders()->whereIn('id', $orderedIds)->pluck('id')->all();
        $ids = array_values(array_intersect($orderedIds, $owned));
        DB::transaction(function () use ($ids, $list) {
            foreach ($ids as $i => $id) {
                $list->reminders()->whereKey($id)->update(['position' => $i]);
            }
        });
    }
}
```

- [ ] **Step 5: Controller**

```php
<?php
// app/Http/Controllers/ReminderController.php

namespace App\Http\Controllers;

use App\Actions\Reminders\CompleteReminder;
use App\Actions\Reminders\CreateReminder;
use App\Actions\Reminders\ReorderReminders;
use App\Actions\Reminders\UpdateReminder as UpdateAction;
use App\Http\Requests\StoreReminder;
use App\Http\Requests\UpdateReminder as UpdateRequest;
use App\Models\Reminder;
use App\Models\ReminderList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReminderController extends Controller
{
    public function store(StoreReminder $request, CreateReminder $action): RedirectResponse
    {
        $action->run($request->user(), $request->validated());
        return redirect()->back();
    }

    public function update(UpdateRequest $request, Reminder $reminder, UpdateAction $action): RedirectResponse
    {
        $action->run($reminder, $request->validated());
        return redirect()->back();
    }

    public function destroy(Reminder $reminder): RedirectResponse
    {
        $this->authorize('delete', $reminder);
        $reminder->delete(); // soft delete
        return redirect()->back();
    }

    public function complete(Request $request, Reminder $reminder, CompleteReminder $action): RedirectResponse
    {
        $this->authorize('update', $reminder);
        $action->run($reminder, (bool) $request->input('done', true));
        return redirect()->back();
    }

    public function reorder(Request $request, ReorderReminders $action): RedirectResponse
    {
        $list = ReminderList::query()
            ->where('id', (int) $request->input('list_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        $action->run($list, collect($request->input('order', []))->map(fn ($x) => (int) $x)->all());
        return redirect()->back();
    }
}
```

- [ ] **Step 6: Routes**

Append to the `auth` middleware group in `routes/web.php`:

```php
use App\Http\Controllers\ReminderController;

Route::post('/reminders', [ReminderController::class, 'store'])->name('reminders.store');
Route::put('/reminders/reorder', [ReminderController::class, 'reorder'])->name('reminders.reorder');
Route::put('/reminders/{reminder}', [ReminderController::class, 'update'])->name('reminders.update');
Route::delete('/reminders/{reminder}', [ReminderController::class, 'destroy'])->name('reminders.destroy');
Route::post('/reminders/{reminder}/complete', [ReminderController::class, 'complete'])->name('reminders.complete');
```

- [ ] **Step 7: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=ReminderControllerTest
```

Expected: PASS (6 tests).

- [ ] **Step 8: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Http app/Actions/Reminders/ReorderReminders.php routes/web.php tests/Feature/Http/ReminderControllerTest.php
git commit -m "feat(http): ReminderController with complete/reorder + form requests"
```

---

## Phase 4 — MCP server

### Task 16: Generate `RemindServer`, `SingleUserMiddleware`, wire `/mcp` route

**Files:**
- Create: `app/Mcp/Servers/RemindServer.php` (via artisan)
- Create: `app/Mcp/Middleware/SingleUserMiddleware.php`
- Modify: `routes/ai.php`
- Modify: `bootstrap/app.php` (register the middleware alias)
- Create: `tests/Feature/Mcp/SingleUserMiddlewareTest.php`

- [ ] **Step 1: Generate the server class**

```bash
docker compose exec app php artisan make:mcp-server RemindServer
```

Open `app/Mcp/Servers/RemindServer.php` and confirm it extends `Laravel\Mcp\Server`.

- [ ] **Step 2: Set name/description and an empty tools array (tools added in subsequent tasks)**

```php
<?php
// app/Mcp/Servers/RemindServer.php

namespace App\Mcp\Servers;

use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Re:Mind')]
#[Version('1.0.0')]
#[Instructions('Capture, browse, and complete reminders organized into projects (lists).')]
class RemindServer extends Server
{
    /** @var array<int, class-string<\Laravel\Mcp\Server\Tool>> */
    protected array $tools = [];
}
```

- [ ] **Step 3: Failing test**

```php
<?php
// tests/Feature/Mcp/SingleUserMiddlewareTest.php

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SingleUserMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_throws_when_zero_users_exist(): void
    {
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertStatus(500);
    }

    public function test_throws_when_multiple_users_exist(): void
    {
        User::factory()->count(2)->create();
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertStatus(500);
    }

    public function test_succeeds_with_exactly_one_user(): void
    {
        User::factory()->create();
        $response = $this->postJson('/mcp', $this->mcpHandshake());
        $response->assertOk();
    }

    /** @return array{jsonrpc:string,id:int,method:string,params:array} */
    private function mcpHandshake(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2025-06-18', 'capabilities' => new \stdClass()],
        ];
    }
}
```

- [ ] **Step 4: Run — fail (route doesn't exist yet)**

```bash
docker compose exec app php artisan test --compact --filter=SingleUserMiddlewareTest
```

- [ ] **Step 5: Implement the middleware**

```php
<?php
// app/Mcp/Middleware/SingleUserMiddleware.php

namespace App\Mcp\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthManager;
use Illuminate\Http\Request;

class SingleUserMiddleware
{
    public function __construct(private AuthManager $auth) {}

    public function handle(Request $request, Closure $next)
    {
        $user = User::sole(); // throws if 0 or 2+ users
        $this->auth->guard('web')->setUser($user);
        $request->setUserResolver(fn () => $user);
        return $next($request);
    }
}
```

- [ ] **Step 6: Register `routes/ai.php`**

```php
<?php
// routes/ai.php
use App\Mcp\Middleware\SingleUserMiddleware;
use App\Mcp\Servers\RemindServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', RemindServer::class)->middleware(SingleUserMiddleware::class);
```

- [ ] **Step 7: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=SingleUserMiddlewareTest
```

Expected: PASS (3 tests).

- [ ] **Step 8: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Mcp routes/ai.php tests/Feature/Mcp/SingleUserMiddlewareTest.php
git commit -m "feat(mcp): RemindServer + SingleUserMiddleware + /mcp route"
```

---

### Task 17: `ListProjects` tool

**Files:**
- Create: `app/Mcp/Tools/ListProjects.php`
- Modify: `app/Mcp/Servers/RemindServer.php`
- Create: `tests/Feature/Mcp/ListProjectsToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/ListProjectsToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\ListProjects;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListProjectsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_lists_with_open_counts(): void
    {
        $user = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $work->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $work->id]);

        $response = RemindServer::tool(ListProjects::class, []);

        $response->assertOk();
        $payload = $response->json('structuredContent');
        $names = collect($payload['projects'])->pluck('name')->all();
        $this->assertContains('Work', $names);
        $work = collect($payload['projects'])->firstWhere('name', 'Work');
        $this->assertSame(2, $work['open_count']);
    }
}
```

- [ ] **Step 2: Run — fail (tool not registered)**

```bash
docker compose exec app php artisan test --compact --filter=ListProjectsToolTest
```

- [ ] **Step 3: Implement tool**

```php
<?php
// app/Mcp/Tools/ListProjects.php

namespace App\Mcp\Tools;

use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-projects')]
#[Description('Return all my Re:Mind projects (lists) with their open-reminder counts. Use before adding a reminder so you know where to file it.')]
#[IsReadOnly]
#[IsIdempotent]
class ListProjects extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        $projects = $user->lists()
            ->orderBy('position')
            ->withCount(['reminders as open_count' => fn ($q) => $q->where('status', 'open')])
            ->get(['id', 'name', 'color', 'is_inbox', 'position'])
            ->map(fn ($l) => [
                'id' => $l->id,
                'name' => $l->name,
                'color' => $l->color,
                'is_inbox' => (bool) $l->is_inbox,
                'open_count' => (int) $l->open_count,
            ])
            ->all();

        return Response::structured(['projects' => $projects]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
```

- [ ] **Step 4: Register the tool**

```php
// app/Mcp/Servers/RemindServer.php
use App\Mcp\Tools\ListProjects;

protected array $tools = [
    ListProjects::class,
];
```

- [ ] **Step 5: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=ListProjectsToolTest
```

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Mcp tests/Feature/Mcp/ListProjectsToolTest.php
git commit -m "feat(mcp): list-projects tool"
```

---

### Task 18: `CreateProject` tool

**Files:**
- Create: `app/Mcp/Tools/CreateProject.php`
- Modify: `app/Mcp/Servers/RemindServer.php`
- Create: `tests/Feature/Mcp/CreateProjectToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/CreateProjectToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\CreateProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateProjectToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_project_and_returns_it(): void
    {
        User::factory()->create();
        $response = RemindServer::tool(CreateProject::class, ['name' => 'Side hustle', 'color' => '#bf616a']);
        $response->assertOk();
        $data = $response->json('structuredContent');
        $this->assertSame('Side hustle', $data['project']['name']);
        $this->assertSame('#bf616a', $data['project']['color']);
    }

    public function test_duplicate_name_returns_error(): void
    {
        $user = User::factory()->create();
        RemindServer::tool(CreateProject::class, ['name' => 'Work']);
        $response = RemindServer::tool(CreateProject::class, ['name' => 'work']);
        $response->assertSee('already exists');
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Mcp/Tools/CreateProject.php

namespace App\Mcp\Tools;

use App\Actions\Lists\CreateList;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('create-project')]
#[Description('Create a new Re:Mind project (list) for filing reminders. Call this before add-reminder when no existing project fits.')]
class CreateProject extends Tool
{
    public function handle(Request $request, CreateList $action): Response
    {
        try {
            $list = $action->run($request->user(), [
                'name' => $request->get('name'),
                'color' => $request->get('color'),
            ]);
        } catch (ValidationException $e) {
            return Response::error('Cannot create project: '.collect($e->errors())->flatten()->first());
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
```

- [ ] **Step 4: Register**

```php
// RemindServer.php
use App\Mcp\Tools\CreateProject;

protected array $tools = [
    ListProjects::class,
    CreateProject::class,
];
```

- [ ] **Step 5: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=CreateProjectToolTest
```

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Mcp tests/Feature/Mcp/CreateProjectToolTest.php
git commit -m "feat(mcp): create-project tool"
```

---

### Task 19: `AddReminder` tool (resolves `project_id` OR `project_name`)

**Files:**
- Create: `app/Mcp/Tools/AddReminder.php`
- Modify: `RemindServer.php`
- Create: `tests/Feature/Mcp/AddReminderToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/AddReminderToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\AddReminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_by_project_id(): void
    {
        $user = User::factory()->create();
        $work = ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);

        $response = RemindServer::tool(AddReminder::class, [
            'project_id' => $work->id,
            'title' => 'Refactor MCP tool',
            'context' => ['repo' => 'git@github.com:foo/bar.git', 'file' => 'app/X.php'],
        ]);

        $response->assertOk();
        $r = $user->reminders()->first();
        $this->assertSame('Refactor MCP tool', $r->title);
        $this->assertSame($work->id, $r->list_id);
        $this->assertSame('foo/bar', $r->context['repo_label']);
    }

    public function test_adds_by_project_name_case_insensitive(): void
    {
        $user = User::factory()->create();
        ReminderList::factory()->create(['user_id' => $user->id, 'name' => 'Work']);

        $response = RemindServer::tool(AddReminder::class, [
            'project_name' => 'work',
            'title' => 'A',
        ]);

        $response->assertOk();
        $this->assertSame(1, $user->reminders()->count());
    }

    public function test_unknown_project_name_returns_error(): void
    {
        User::factory()->create();
        $response = RemindServer::tool(AddReminder::class, [
            'project_name' => 'no-such-list',
            'title' => 'A',
        ]);
        $response->assertSee('No project named');
    }

    public function test_either_project_id_or_project_name_is_required(): void
    {
        User::factory()->create();
        $response = RemindServer::tool(AddReminder::class, ['title' => 'A']);
        $response->assertSee('project');
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Mcp/Tools/AddReminder.php

namespace App\Mcp\Tools;

use App\Actions\Reminders\CreateReminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('add-reminder')]
#[Description('File a new reminder into a Re:Mind project. Provide EITHER project_id OR project_name (case-insensitive). If no project fits, call create-project first.')]
class AddReminder extends Tool
{
    public function handle(Request $request, CreateReminder $action): Response
    {
        $user = $request->user();

        $projectId = $request->get('project_id');
        $projectName = $request->get('project_name');

        if (! $projectId && ! $projectName) {
            return Response::error('You must supply either project_id or project_name. Call list-projects first to see what exists.');
        }

        if (! $projectId) {
            $list = $user->lists()
                ->whereRaw('LOWER(name) = ?', [mb_strtolower((string) $projectName)])
                ->first();
            if (! $list) {
                return Response::error("No project named \"{$projectName}\". Call list-projects to see existing names or create-project to add a new one.");
            }
            $projectId = $list->id;
        }

        try {
            $reminder = $action->run($user, [
                'list_id' => $projectId,
                'title' => (string) $request->get('title'),
                'notes' => $request->get('notes'),
                'soft_due_date' => $request->get('soft_due_date'),
                'context' => $request->get('context'),
            ]);
        } catch (ValidationException $e) {
            return Response::error('Invalid input: '.collect($e->errors())->flatten()->first());
        }

        return Response::structured(['reminder' => [
            'id' => $reminder->id,
            'title' => $reminder->title,
            'project_id' => $reminder->list_id,
            'status' => $reminder->status,
            'created_at' => $reminder->created_at?->toIso8601String(),
        ]]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required()->description('Short title of the reminder, max 200 chars.'),
            'project_id' => $schema->integer()->description('Target project id. Get it from list-projects.'),
            'project_name' => $schema->string()->description('Case-insensitive project name (alternative to project_id).'),
            'notes' => $schema->string()->description('Optional markdown notes.'),
            'soft_due_date' => $schema->string()->description('Optional date, YYYY-MM-DD. Sort key only, no notifications.'),
            'context' => $schema->object()->description('Optional coding context: {repo, branch, file, line_start, line_end, cwd}.'),
        ];
    }
}
```

- [ ] **Step 4: Register the tool in `RemindServer`**

- [ ] **Step 5: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=AddReminderToolTest
```

- [ ] **Step 6: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Mcp tests/Feature/Mcp/AddReminderToolTest.php
git commit -m "feat(mcp): add-reminder tool with project_id|project_name resolution"
```

---

### Task 20: `ListReminders` tool (filter by project/status/query)

**Files:**
- Create: `app/Mcp/Tools/ListReminders.php`
- Modify: `RemindServer.php`
- Create: `tests/Feature/Mcp/ListRemindersToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/ListRemindersToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\ListReminders;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListRemindersToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_open_reminders_by_default(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->count(2)->create(['user_id' => $user->id, 'list_id' => $list->id]);
        Reminder::factory()->done()->create(['user_id' => $user->id, 'list_id' => $list->id]);

        $response = RemindServer::tool(ListReminders::class, []);
        $response->assertOk();
        $this->assertCount(2, $response->json('structuredContent')['reminders']);
    }

    public function test_filters_by_project_id(): void
    {
        $user = User::factory()->create();
        $a = ReminderList::factory()->create(['user_id' => $user->id]);
        $b = ReminderList::factory()->create(['user_id' => $user->id]);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $a->id]);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $b->id]);

        $response = RemindServer::tool(ListReminders::class, ['project_id' => $a->id]);
        $this->assertCount(1, $response->json('structuredContent')['reminders']);
    }

    public function test_filters_by_query_against_title_and_notes(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'title' => 'Refactor MCP tool']);
        Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id, 'title' => 'Walk the dog']);

        $response = RemindServer::tool(ListReminders::class, ['query' => 'mcp']);
        $this->assertCount(1, $response->json('structuredContent')['reminders']);
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Mcp/Tools/ListReminders.php

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-reminders')]
#[Description('Search or browse my reminders. Defaults to open reminders only; pass status to include done. Optional project filter and free-text query.')]
#[IsReadOnly]
#[IsIdempotent]
class ListReminders extends Tool
{
    public function handle(Request $request): Response
    {
        $user = $request->user();
        $status = $request->get('status', 'open');
        $limit = (int) ($request->get('limit', 20));
        $limit = max(1, min($limit, 100));
        $projectId = $request->get('project_id');
        $query = $request->get('query');

        $q = $user->reminders()->with('list:id,name')->orderByDesc('updated_at');

        if ($status !== 'all') {
            $q->where('status', $status);
        }
        if ($projectId) {
            $q->where('list_id', (int) $projectId);
        }
        if ($query) {
            $needle = '%'.mb_strtolower((string) $query).'%';
            $q->where(function ($q2) use ($needle) {
                $q2->whereRaw('LOWER(title) LIKE ?', [$needle])
                   ->orWhereRaw('LOWER(COALESCE(notes,\'\')) LIKE ?', [$needle]);
            });
        }

        $reminders = $q->limit($limit)->get()->map(fn ($r) => [
            'id' => $r->id,
            'title' => $r->title,
            'status' => $r->status,
            'project' => ['id' => $r->list?->id, 'name' => $r->list?->name],
            'soft_due_date' => $r->soft_due_date?->toDateString(),
            'context' => $r->context,
            'completed_at' => $r->completed_at?->toIso8601String(),
        ])->all();

        return Response::structured(['reminders' => $reminders]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Restrict to a single project.'),
            'status' => $schema->string()->enum(['open', 'done', 'all'])->default('open')->description('Filter by status.'),
            'query' => $schema->string()->description('Case-insensitive search across title and notes.'),
            'limit' => $schema->integer()->default(20)->description('Max results (1-100, default 20).'),
        ];
    }
}
```

- [ ] **Step 4: Register**, **Step 5: run — pass**, **Step 6: commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app php artisan test --compact --filter=ListRemindersToolTest
git add app/Mcp tests/Feature/Mcp/ListRemindersToolTest.php
git commit -m "feat(mcp): list-reminders tool with status/project/query filters"
```

---

### Task 21: `CompleteReminder` MCP tool

**Files:**
- Create: `app/Mcp/Tools/CompleteReminder.php`
- Modify: `RemindServer.php`
- Create: `tests/Feature/Mcp/CompleteReminderToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/CompleteReminderToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\CompleteReminder as Tool;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompleteReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_reminder_done(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        $response = RemindServer::tool(Tool::class, ['reminder_id' => $r->id]);
        $response->assertOk();
        $this->assertSame('done', $r->fresh()->status);
    }

    public function test_unknown_reminder_returns_error(): void
    {
        User::factory()->create();
        $response = RemindServer::tool(Tool::class, ['reminder_id' => 9999]);
        $response->assertSee('not found');
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Mcp/Tools/CompleteReminder.php

namespace App\Mcp\Tools;

use App\Actions\Reminders\CompleteReminder as Action;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[Name('complete-reminder')]
#[Description('Mark a reminder done. Idempotent — safe to call twice.')]
#[IsIdempotent]
class CompleteReminder extends Tool
{
    public function handle(Request $request, Action $action): Response
    {
        $reminder = Reminder::query()
            ->where('id', (int) $request->get('reminder_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $reminder) {
            return Response::error('Reminder not found.');
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
```

- [ ] **Step 4: Register, Step 5: run pass, Step 6: commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app php artisan test --compact --filter=CompleteReminderToolTest
git add app/Mcp tests/Feature/Mcp/CompleteReminderToolTest.php
git commit -m "feat(mcp): complete-reminder tool (idempotent)"
```

---

### Task 22: `UpdateReminder` MCP tool

**Files:**
- Create: `app/Mcp/Tools/UpdateReminder.php`
- Modify: `RemindServer.php`
- Create: `tests/Feature/Mcp/UpdateReminderToolTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Mcp/UpdateReminderToolTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\UpdateReminder as Tool;
use App\Models\Reminder;
use App\Models\ReminderList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateReminderToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_and_notes(): void
    {
        $user = User::factory()->create();
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id, 'title' => 'Old']);

        $response = RemindServer::tool(Tool::class, [
            'reminder_id' => $r->id,
            'title' => 'New',
            'notes' => 'Update',
        ]);

        $response->assertOk();
        $this->assertSame('New', $r->fresh()->title);
        $this->assertSame('Update', $r->fresh()->notes);
    }

    public function test_unknown_reminder_returns_error(): void
    {
        User::factory()->create();
        $response = RemindServer::tool(Tool::class, ['reminder_id' => 9999, 'title' => 'X']);
        $response->assertSee('not found');
    }

    public function test_can_move_to_a_different_project(): void
    {
        $user = User::factory()->create();
        $list = ReminderList::factory()->create(['user_id' => $user->id]);
        $r = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $user->lists()->first()->id]);

        RemindServer::tool(Tool::class, ['reminder_id' => $r->id, 'project_id' => $list->id]);

        $this->assertSame($list->id, $r->fresh()->list_id);
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Mcp/Tools/UpdateReminder.php

namespace App\Mcp\Tools;

use App\Actions\Reminders\UpdateReminder as Action;
use App\Models\Reminder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;

#[Name('update-reminder')]
#[Description('Edit fields on an existing reminder. Any subset of fields is accepted.')]
class UpdateReminder extends Tool
{
    public function handle(Request $request, Action $action): Response
    {
        $reminder = Reminder::query()
            ->where('id', (int) $request->get('reminder_id'))
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $reminder) {
            return Response::error('Reminder not found.');
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
            return Response::error('Invalid input: '.collect($e->errors())->flatten()->first());
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
```

- [ ] **Step 4: Register, Step 5: run pass, Step 6: commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
docker compose exec app php artisan test --compact --filter=UpdateReminderToolTest
git add app/Mcp tests/Feature/Mcp/UpdateReminderToolTest.php
git commit -m "feat(mcp): update-reminder tool"
```

---

## Phase 5 — Daily purge & polish

### Task 23: `PurgeTrash` artisan command + daily schedule

**Files:**
- Create: `app/Console/Commands/PurgeTrash.php`
- Modify: `routes/console.php`
- Create: `tests/Feature/Console/PurgeTrashTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Console/PurgeTrashTest.php

namespace Tests\Feature\Console;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurgeTrashTest extends TestCase
{
    use RefreshDatabase;

    public function test_purges_soft_deleted_older_than_30_days(): void
    {
        $user = User::factory()->create();
        $list = $user->lists()->first();

        $old = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $old->delete();
        $old->forceFill(['deleted_at' => Carbon::now()->subDays(31)])->save();

        $recent = Reminder::factory()->create(['user_id' => $user->id, 'list_id' => $list->id]);
        $recent->delete();

        $this->artisan('reminders:purge-trash')->assertExitCode(0);

        $this->assertNull(Reminder::withTrashed()->find($old->id));
        $this->assertNotNull(Reminder::withTrashed()->find($recent->id));
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Generate the command**

```bash
docker compose exec app php artisan make:command PurgeTrash
```

- [ ] **Step 4: Implement**

```php
<?php
// app/Console/Commands/PurgeTrash.php

namespace App\Console\Commands;

use App\Models\Reminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class PurgeTrash extends Command
{
    protected $signature = 'reminders:purge-trash {--days=30}';
    protected $description = 'Permanently delete reminders that have been in the trash for more than N days.';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $cutoff = Carbon::now()->subDays($days);

        $count = Reminder::onlyTrashed()->where('deleted_at', '<', $cutoff)->count();
        Reminder::onlyTrashed()->where('deleted_at', '<', $cutoff)->forceDelete();

        $this->info("Purged {$count} reminder(s) deleted before {$cutoff->toDateTimeString()}.");
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Schedule daily in `routes/console.php`**

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('reminders:purge-trash')->daily();
```

- [ ] **Step 6: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=PurgeTrashTest
```

- [ ] **Step 7: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Console routes/console.php tests/Feature/Console/PurgeTrashTest.php
git commit -m "feat(console): reminders:purge-trash daily command"
```

---

### Task 24: `RenderNotes` markdown action

**Files:**
- Create: `app/Actions/Markdown/RenderNotes.php`
- Create: `tests/Feature/Actions/Markdown/RenderNotesTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
// tests/Feature/Actions/Markdown/RenderNotesTest.php

namespace Tests\Feature\Actions\Markdown;

use App\Actions\Markdown\RenderNotes;
use Tests\TestCase;

class RenderNotesTest extends TestCase
{
    public function test_returns_empty_string_for_null(): void
    {
        $this->assertSame('', (new RenderNotes())->run(null));
    }

    public function test_renders_basic_markdown(): void
    {
        $html = (new RenderNotes())->run("**hi**\n\n- a\n- b");
        $this->assertStringContainsString('<strong>hi</strong>', $html);
        $this->assertStringContainsString('<li>a</li>', $html);
    }

    public function test_strips_inline_html(): void
    {
        $html = (new RenderNotes())->run('<script>alert(1)</script> text');
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_rejects_javascript_links(): void
    {
        $html = (new RenderNotes())->run('[x](javascript:alert(1))');
        $this->assertStringNotContainsString('javascript:', $html);
    }
}
```

- [ ] **Step 2: Run — fail**

- [ ] **Step 3: Implement**

```php
<?php
// app/Actions/Markdown/RenderNotes.php

namespace App\Actions\Markdown;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\MarkdownConverter;

class RenderNotes
{
    public function run(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        $config = [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ];

        $env = new Environment($config);
        $env->addExtension(new CommonMarkCoreExtension());

        return (new MarkdownConverter($env))->convert($markdown)->getContent();
    }
}
```

- [ ] **Step 4: Run — pass**

```bash
docker compose exec app php artisan test --compact --filter=RenderNotesTest
```

- [ ] **Step 5: Pint + commit**

```bash
docker compose exec app vendor/bin/pint --dirty --format agent
git add app/Actions/Markdown tests/Feature/Actions/Markdown
git commit -m "feat(markdown): RenderNotes via league/commonmark safe mode"
```

---

### Task 25: End-to-end MCP capture flow

**Files:**
- Create: `tests/Feature/Mcp/EndToEndCaptureFlowTest.php`

- [ ] **Step 1: Test**

```php
<?php
// tests/Feature/Mcp/EndToEndCaptureFlowTest.php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\RemindServer;
use App\Mcp\Tools\AddReminder;
use App\Mcp\Tools\CompleteReminder;
use App\Mcp\Tools\CreateProject;
use App\Mcp\Tools\ListProjects;
use App\Mcp\Tools\ListReminders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EndToEndCaptureFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_capture_flow(): void
    {
        User::factory()->create(); // observer creates Inbox

        // 1. Inspect what projects exist
        $r1 = RemindServer::tool(ListProjects::class, []);
        $r1->assertOk();
        $names = collect($r1->json('structuredContent')['projects'])->pluck('name');
        $this->assertContains('Inbox', $names);

        // 2. Create a new project for this work
        $r2 = RemindServer::tool(CreateProject::class, ['name' => 'Re:Mind dev', 'color' => '#7aa2f7']);
        $r2->assertOk();
        $projectId = $r2->json('structuredContent')['project']['id'];

        // 3. File a reminder with context
        $r3 = RemindServer::tool(AddReminder::class, [
            'project_id' => $projectId,
            'title' => 'Refactor MCP route binding',
            'context' => [
                'repo' => 'git@github.com:foo/bar.git',
                'branch' => 'feature/mcp',
                'file' => 'routes/ai.php',
                'cwd' => '/home/aymeric/zWEB/remind',
            ],
        ]);
        $r3->assertOk();
        $reminderId = $r3->json('structuredContent')['reminder']['id'];

        // 4. List reminders for that project — should see ours
        $r4 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId]);
        $r4->assertOk();
        $this->assertCount(1, $r4->json('structuredContent')['reminders']);

        // 5. Complete it
        $r5 = RemindServer::tool(CompleteReminder::class, ['reminder_id' => $reminderId]);
        $r5->assertOk();

        // 6. Default list (open only) should now be empty for this project
        $r6 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId]);
        $this->assertCount(0, $r6->json('structuredContent')['reminders']);

        // 7. status=all surfaces it again
        $r7 = RemindServer::tool(ListReminders::class, ['project_id' => $projectId, 'status' => 'all']);
        $this->assertCount(1, $r7->json('structuredContent')['reminders']);
    }
}
```

- [ ] **Step 2: Run — expect pass**

```bash
docker compose exec app php artisan test --compact --filter=EndToEndCaptureFlowTest
```

- [ ] **Step 3: Run the WHOLE feature suite**

```bash
docker compose exec app php artisan test --compact
```

Expected: ALL green.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Mcp/EndToEndCaptureFlowTest.php
git commit -m "test(mcp): end-to-end capture flow (list → create → add → list → complete)"
```

- [ ] **Step 5: Final smoke — hit `/mcp` from outside the container**

```bash
curl -s -X POST http://localhost:8000/mcp \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list","params":{}}' | head -200
```

Expected: a JSON response listing the six tools (`list-projects`, `create-project`, `add-reminder`, `list-reminders`, `complete-reminder`, `update-reminder`).

---

## Self-review

- **Spec coverage:**
  - §3 architecture (one Laravel app, MCP at /mcp, SQLite) → Tasks 1, 2, 16.
  - §4 data model (lists, reminders, invariants) → Tasks 4-7.
  - §5 dashboard UX → out of scope here; placeholder page in Task 14. Full UI = Plan 2.
  - §6 MCP — transport, no auth, 6 tools → Tasks 16-22.
  - §7 mutations — delete reminder (soft), delete list with strategies, reposition → Tasks 10, 13-15.
  - §8 stack — Docker, Wayfinder, Fortify off → Tasks 2, 3, 14.
  - §9 code organization mirrors the file map above.
  - §10 security — `User::sole()`, markdown safe, FormRequests, policies, cross-user tests → Tasks 6, 7, 14-16, 24.
  - §11 testing — every controller/action/tool has its own feature test + end-to-end flow → Tasks 6-25.
  - §12 risks acknowledged in spec; tooling stays in Actions to isolate from laravel/mcp churn.
- **Placeholder scan:** every step shows code/commands; no TBD/TODO.
- **Type consistency:** `ReminderList` class used throughout; tool names use kebab-case matching the spec; `STATUS_OPEN`/`STATUS_DONE` constants referenced in Reminder and the CompleteReminder action.
- **Public surface** — MCP exposes "project" naming, internal model is `ReminderList` everywhere (verified in §4.1 of the spec and Tasks 4-25).

## Out of scope for this plan (lands in Plan 2)

- Sidebar with drag reorder, color picker, list rename UI.
- Main pane with inline-expand editor card, markdown preview toggle, soft-due picker.
- Completed footer + Completed view.
- Keyboard shortcuts.
- Settings → MCP setup page (the config snippet shown).
- Brand wordmark in the app shell.
