# HistoryGuessr

Web game: the player sees a historical photo, picks a point on a map and a year, and gets a server-calculated score.
Product spec: `mvp-plan.md`. Laravel API + Vue 3 SPA live in one repo and deploy together; the closed admin is Filament.

## Stack

- Laravel 13, PHP 8.4 (Docker), PostgreSQL 17, Redis 7 (cache, sessions, queue), S3-compatible storage (RustFS locally)
- Filament 5 at `/admin`, roles via `users.role` (`admin` | `editor`) + Policies — no permission packages
- Vue 3 + TypeScript + Vite, Vue Router, Pinia, MapLibre GL JS, Tailwind 4
- Self-hosted map: VersaTiles server (service `maps`) with OSM Shortbread tiles, behind nginx at `/maps/` (`docker/maps/README.md`)
- Tests: Pest (backend, runs against PostgreSQL `historyguessr_test`), Vitest (`tests/js`); Playwright is not set up yet

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
| `make build` | Production frontend build + scan for external map services |
| `make fresh` | `migrate:fresh --seed` |
| `make maps` / `make maps-smoke` | Fetch and activate the dev map data (zoom 0–8) / check it through nginx |
| `make maps-style` | Regenerate the map style (`resources/js/config/map-style.json`) |

Before finishing a task: `make lint analyse test` must be green.

## Git

Work directly on `main`: no feature branches or worktrees unless the user explicitly asks for one.
Dev logins: `admin@example.com` / `editor@example.com`, password `password`.

## Backend rules

- Thin controllers in `app/Http/Controllers/Api/V1`; validation in Form Requests; game operations in `app/Actions/Game/*`.
- Use Eloquent directly — no repository layer. API output only through API Resources.
- All `/api/*` errors use the envelope `{ message, code, errors? }` (`app/Http/ApiExceptionRenderer.php`). Throw HTTP exceptions; don't hand-build error JSON.
- API routes live under `/api/v1`. External identifiers are UUID `public_id`, never sequential ids.
- **The correct answer (answer text, coordinates, year, location name, reveal text) must never appear in a payload before the answer is submitted.** Public question data and reveal data are separate Resources; every endpoint change needs a leak test.
- Scores are calculated only on the server and persisted at answer time; never accept distance/score from the client.
- Answer submission is idempotent (`idempotency_key`) and protected by DB unique constraints.
- Scoring constants live in `config/game.php`.
- Questions and quizzes share `App\Enums\PublicationStatus` (draft / published / archived). Drafts may be incomplete; PostgreSQL CHECK `questions_published_complete` refuses to publish a question without image, answer and attribution.
- Integrity rules (year ≠ 0, coordinate ranges, one answer per question, unique idempotency key, `total = geo + year`, an answer without a guess is a 0-point timeout) are enforced by DB constraints, not only in PHP. CHECK constraints are added with `DB::statement` in migrations.
- `public_id` is set by the `HasPublicId` model event: don't use `WithoutModelEvents` in seeders.
- Years are signed integers: `-44` = 44 BC, year `0` does not exist. Use `App\Support\HistoricalYear` for differences and validation.
- The player sees only the photo (no question text). `answer` (what the photo shows; also the admin title) and `reveal_text` are revealed after answering and are plain text; no HTML is stored or rendered.
- Images never pass through PHP at request time: they are served from S3/CDN, stored under UUID keys, EXIF/GPS stripped.
  Livewire temporary uploads use the `local` disk (`config/livewire.php`): its presigned S3 PUT would be signed for the internal `s3:9000` host.
  Question photos: the admin uploads a private original to `originals/`; `QuestionObserver` dispatches `ProcessQuestionImage`, which uses `App\Services\ImageProcessor` to write public `questions/{uuid}/{large,medium}.webp`. Quiz covers are processed synchronously on upload.
- Only `questions/*` and `quizzes/*` are publicly readable in the bucket; originals are reached via presigned URLs. Locally they are signed against `AWS_PUBLIC_ENDPOINT` by `App\Support\Filesystem\PublicPresigningS3Adapter`.
- A malformed UUID in a route resolves to 404 (`HasPublicId::resolveRouteBindingQuery`), never a database error.

## Game API (`/api/v1`)

| Endpoint | Purpose |
|---|---|
| `GET quizzes` | Published quizzes with at least one published question |
| `POST game-sessions` `{quiz_id?}` | Start: weighted random sample of the quiz (`WeightedSampler`); without `quiz_id` (home «Начать игру») `game.random_round_size` random published questions, equal odds, `quiz: null` (`409 questions_unavailable` if none); order fixed in `game_session_questions`; 201 + game state |
| `GET game-sessions/{id}` | Current state; `answer` is present only once the current question is answered (reload recovery); `round` holds the timer; `notice` is set once after a skipped round |
| `POST game-sessions/{id}/answers` | `{question_id, latitude, longitude, year, idempotency_key, timed_out?}`; with `timed_out` the point/year are optional; 201 when created, 200 for an idempotent replay |
| `POST game-sessions/{id}/advance` `{position}` | Leave `position`; repeating it is a no-op; the last one completes the game |
| `GET game-sessions/{id}/result` | Per-round scores and correct places (`question.latitude/longitude` for the result map); 409 until completed |

- Round timer: a question's round starts when the server shows it (game start / `advance`); `game_session_questions.started_at`/`deadline_at`, limit snapshotted in `game_sessions.round_time_limit_seconds` (`config('game.round')`: 90 s + 3 s grace). `App\Support\RoundClock` holds the rules: expired = `now > deadline + grace` without an answer.
  At zero the client sends `timed_out: true` with whatever was picked: a point is scored as usual (`timed_out` answer with a guess), no point scores 0. The server does not second-guess the client's countdown (no "too early" error); any answer after `deadline + grace` is 409 `round_expired`. `duration_ms` is measured by the server.
  A round left unanswered past the grace (player away/offline) is skipped lazily by `ExpireRound` on GET or `advance`: a zero-point answer without a guess, then `MoveToNextRound` opens the next question with a fresh timer (or completes the game); that GET alone returns `notice: "previous_round_expired"`. The skipped question is never revealed. The reveal screen has no limit.
  `round: {time_limit_ms, grace_ms, started_at, deadline_at, remaining_ms}`; the client counts down from `remaining_ms`.
- Players are anonymous: `EnsureAnonymousPlayer` stores a UUID in the cookie session. Sanctum only starts that session for requests from the SPA origin (`SANCTUM_STATEFUL_DOMAINS`); API tests must send `Referer: APP_URL`.
- Another player's game is a 404, not a 403. Rule violations are `GameException` (409) with codes like `already_answered`, `question_mismatch`, `answer_required`, `game_not_in_progress`, `quiz_unavailable`, `round_expired`.
- Answers and advances lock the game session row (`lockForUpdate`); resources are unwrapped (`JsonResource::withoutWrapping()`).
- Game reads go through `QuestionCache` (Redis); `QuestionObserver` and `ProcessQuestionImage` invalidate it.

## Admin (Filament)

- Resources live in `app/Filament/Resources/{Questions,Quizzes,Users}` with separate `Schemas/` and `Tables/` classes.
- Status changes go through `App\Filament\Actions\PublicationActions` → `App\Actions\Admin\{PublishQuestion,PublishQuiz,ChangePublicationStatus}`. Never set `status = published` directly in a form.
- A question's quizzes are picked in the question form (`quizzes` select; archived quizzes only if already attached). Saving goes through `App\Actions\Admin\SyncQuestionQuizzes`: new quizzes get the question appended via `AttachQuestionsToQuiz` (weight 1), removed ones detach it, kept ones keep position and weight.
- Editors and admins manage content; only admins manage users (Policies in `app/Policies`). Only archived questions can be deleted (`App\Actions\Admin\DeleteQuestion`: also deletes the games they were in and their images); played quizzes cannot be deleted — archive them.
- The map field is `App\Filament\Forms\Components\MapLocationPicker` (Alpine component `mapLocationPicker` in `resources/js/admin/location-picker.ts`, loaded via a panel render hook). Shared MapLibre setup is in `resources/js/lib/maplibre.ts`.

## Frontend rules

- `<script setup lang="ts">` + Composition API everywhere; typed props/emits.
- The server is the source of truth for game state; after reload the state is restored via the API.
- Pinia only for state shared across screens (`stores/game.ts`).
- All HTTP goes through `resources/js/api/client.ts` (CSRF, `ApiError`); every request has loading / error / retry states (`useAsyncState`).
- The map is fully self-hosted: style, tiles, glyphs and sprites are same-origin under `/maps/`. Never add an external map URL (tiles, fonts, CDN, API keys); `tests/js/map-style.spec.ts` and `npm run check:maps` guard this.
- The style is generated (`make maps-style`, from `scripts/maps/build-style.mjs`) and committed; zoom limits live in `resources/js/config/map.ts`. Labels are English (`name_en`, fallback `name`).
- Coordinates outside map components are always named: `{ latitude, longitude }`. MapLibre's `[lng, lat]` arrays exist only inside `components/maps` (`coordinates.ts`).
- Page components never touch the MapLibre API directly; wrap it in `components/maps/*` and `useMap` (which owns create/`resize()`/`remove()`, `ready` after style load, `error` + `retry()`).
- Layers and sources (e.g. the answer line) are added only once `ready` is true; update them with `setData`, never by recreating the map.
- Mobile-first layouts; desktop is `lg` (`useMediaQuery(DESKTOP_QUERY)`). Game and result pages are full-screen maps with panels laid over them (overlay `z-10`, above MapLibre's controls; `.map-fullscreen` moves the controls and attribution clear of the panels).
- `GameMap` is the one map of the game page: guessing (click/drag the point, caption with coordinates) and the reveal (geodesic `greatCircle` line, captions, animated `fitBounds` with `padding` for the panels) on the same instance. `ResultMap` pins every round; `WorldBackdrop` is the still home hero map whose «?» pins are decorative — never real question places (that would leak answers).
- `createMap` adds the 15° coordinate grid (`addGraticule`, under the labels) to every map.
- Game flow lives in `stores/game.ts`: `phase` is derived from server state (`guessing` → `revealed` → `completed`); `submit()` keeps one idempotency key per question until the answer is confirmed; `advance()` sends the current position.
  The round timer is server-driven: `deadline` = now + `round.remaining_ms` on every applied state; at zero (`timeUp`) `submitOnTimeout()` sends the picked point with `year ?? DEFAULT_YEAR` (`config/game.ts`, also the ruler's resting year) unless a manual answer is in flight; the server's `notice` is kept in the store until dismissed or the next answer.
- Visual language «Atlas» (the map is a sheet of an atlas): tokens in `resources/css/app.css` (`paper` page and panels, `ink` text, frames and dark buttons, `ink-soft` secondary text, `stamp` red for correct answers, the timer and accents, `guess` teal for the player's point, `muted-pin` for timed-out rounds, `hatch` for skeletons). No rounded corners except round markers. Barlow Condensed (UI, uppercase labels, numbers) and Newsreader (headings, years, reveal text); neither has Cyrillic, so Russian text falls back to the system font on purpose. Panels: `.sheet` (double frame), `.panel`, `.sheet-frame` (frame over a full-screen map), `.polaroid` (pinned photo), `.eyebrow`, `.scale-bar`, `.skeleton`; actions `.btn-primary` (ink, `stamp` on hover) / `.btn-secondary`. The game header is `GameCartouche` (compact strip on phones) with `RoundTimer` (pulses in the last 15 s); `AppHeader` serves the other pages. The year picker (`YearInput`, `layout="inline"` on desktop) draws a 50-year scale bar under a `stamp` needle.
- Vitest specs live in `tests/js`; MapLibre is mocked there (`vi.mock('@/lib/maplibre')`, no WebGL in jsdom), coordinate logic is tested in isolation.

## Out of scope for the MVP

Registration, OAuth, leaderboards, multiplayer/WebSockets, SSR, microservices, Octane (until a measured need).
