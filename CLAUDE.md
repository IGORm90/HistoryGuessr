# HistoryGuessr

Web game: the player sees a historical photo, picks a point on a Leaflet map and a year, and gets a server-calculated score.
Product spec: `mvp-plan.md`. Laravel API + Vue 3 SPA live in one repo and deploy together; the closed admin is Filament.

## Stack

- Laravel 13, PHP 8.4 (Docker), PostgreSQL 17, Redis 7 (cache, sessions, queue), S3-compatible storage (RustFS locally)
- Filament 5 at `/admin`, roles via `users.role` (`admin` | `editor`) + Policies — no permission packages
- Vue 3 + TypeScript + Vite, Vue Router, Pinia, Leaflet 1.9.x, Tailwind 4
- Tests: Pest (backend, runs against PostgreSQL `historyguessr_test`), Vitest (`tests/js`), Playwright (`tests/e2e`)

## Commands

Everything PHP runs inside Docker (`make shell` for a shell). Node tooling runs on the host.

| Command | What it does |
|---|---|
| `make setup` | First-time setup: build, install, migrate, seed |
| `make start` / `make stop` | Start / stop services (app on http://localhost:8080) |
| `make test` | Pest + Vitest |
| `make lint` | Pint `--test` + ESLint |
| `make fix` | Auto-format PHP and TS |
| `make analyse` | PHPStan (level 6) + `vue-tsc` |
| `make build` | Production frontend build |
| `make fresh` | `migrate:fresh --seed` |

Before finishing a task: `make lint analyse test` must be green.
Dev logins: `admin@example.com` / `editor@example.com`, password `password`.

## Backend rules

- Thin controllers in `app/Http/Controllers/Api/V1`; validation in Form Requests; game operations in `app/Actions/Game/*`.
- Use Eloquent directly — no repository layer. API output only through API Resources.
- All `/api/*` errors use the envelope `{ message, code, errors? }` (`app/Http/ApiExceptionRenderer.php`). Throw HTTP exceptions; don't hand-build error JSON.
- API routes live under `/api/v1`. External identifiers are UUID `public_id`, never sequential ids.
- **The correct answer (coordinates, year, location name, reveal text) must never appear in a payload before the answer is submitted.** Public question data and reveal data are separate Resources; every endpoint change needs a leak test.
- Scores are calculated only on the server and persisted at answer time; never accept distance/score from the client.
- Answer submission is idempotent (`idempotency_key`) and protected by DB unique constraints.
- Scoring constants live in `config/game.php`.
- Questions and quizzes share `App\Enums\PublicationStatus` (draft / published / archived). Drafts may be incomplete; PostgreSQL CHECK `questions_published_complete` refuses to publish a question without image, answer and attribution.
- Integrity rules (year ≠ 0, coordinate ranges, one answer per question, unique idempotency key, `total = geo + year`) are enforced by DB constraints, not only in PHP. CHECK constraints are added with `DB::statement` in migrations.
- `public_id` is set by the `HasPublicId` model event: don't use `WithoutModelEvents` in seeders.
- Years are signed integers: `-44` = 44 BC, year `0` does not exist. Use `App\Support\HistoricalYear` for differences and validation.
- `prompt` and `reveal_text` are plain text; no HTML is stored or rendered.
- Images never pass through PHP at request time: they are served from S3/CDN, stored under UUID keys, EXIF/GPS stripped.
  Question photos: the admin uploads a private original to `originals/`; `QuestionObserver` dispatches `ProcessQuestionImage`, which uses `App\Services\ImageProcessor` to write public `questions/{uuid}/{large,medium}.webp`. Quiz covers are processed synchronously on upload.
- Only `questions/*` and `quizzes/*` are publicly readable in the bucket; originals are reached via presigned URLs. Locally they are signed against `AWS_PUBLIC_ENDPOINT` by `App\Support\Filesystem\PublicPresigningS3Adapter`.
- A malformed UUID in a route resolves to 404 (`HasPublicId::resolveRouteBindingQuery`), never a database error.

## Game API (`/api/v1`)

| Endpoint | Purpose |
|---|---|
| `GET quizzes` | Published quizzes with at least one published question |
| `POST game-sessions` `{quiz_id}` | Start: weighted random sample (`WeightedSampler`), order fixed in `game_session_questions`; 201 + game state |
| `GET game-sessions/{id}` | Current state; `answer` is present only once the current question is answered (reload recovery) |
| `POST game-sessions/{id}/answers` | `{question_id, latitude, longitude, year, idempotency_key, duration_ms}`; 201 when created, 200 for an idempotent replay |
| `POST game-sessions/{id}/advance` `{position}` | Leave `position`; repeating it is a no-op; the last one completes the game |
| `GET game-sessions/{id}/result` | Per-round scores; 409 until completed |

- Players are anonymous: `EnsureAnonymousPlayer` stores a UUID in the cookie session. Sanctum only starts that session for requests from the SPA origin (`SANCTUM_STATEFUL_DOMAINS`); API tests must send `Referer: APP_URL`.
- Another player's game is a 404, not a 403. Rule violations are `GameException` (409) with codes like `already_answered`, `question_mismatch`, `answer_required`, `game_not_in_progress`, `quiz_unavailable`.
- Answers and advances lock the game session row (`lockForUpdate`); resources are unwrapped (`JsonResource::withoutWrapping()`).
- Game reads go through `QuestionCache` (Redis); `QuestionObserver` and `ProcessQuestionImage` invalidate it.

## Admin (Filament)

- Resources live in `app/Filament/Resources/{Questions,Quizzes,Users}` with separate `Schemas/` and `Tables/` classes.
- Status changes go through `App\Filament\Actions\PublicationActions` → `App\Actions\Admin\{PublishQuestion,PublishQuiz,ChangePublicationStatus}`. Never set `status = published` directly in a form.
- Editors and admins manage content; only admins manage users (Policies in `app/Policies`). Answered questions and played quizzes cannot be deleted — archive them.
- The map field is `App\Filament\Forms\Components\LeafletLocationPicker` (Alpine component in `resources/js/admin/location-picker.ts`, loaded via a panel render hook). Shared Leaflet setup is in `resources/js/lib/leaflet.ts`.

## Frontend rules

- `<script setup lang="ts">` + Composition API everywhere; typed props/emits.
- The server is the source of truth for game state; after reload the state is restored via the API.
- Pinia only for state shared across screens (`stores/game.ts`).
- All HTTP goes through `resources/js/api/client.ts` (CSRF, `ApiError`); every request has loading / error / retry states (`useAsyncState`).
- Tile URL and attribution come only from `resources/js/config/map.ts` (env `VITE_MAP_TILE_*`).
- Coordinates outside Leaflet components are always named: `{ latitude, longitude }`. `[lat, lng]` arrays exist only inside `components/maps`.
- Page components never touch the Leaflet API directly; wrap it in `components/maps/*` and `useLeafletMap`.
- Map components: init in `onMounted`, `map.remove()` in `onBeforeUnmount`, `invalidateSize()` after layout changes.
- Mobile-first layouts.

## Out of scope for the MVP

Registration, OAuth, leaderboards, multiplayer/WebSockets, SSR, microservices, Octane (until a measured need).
