# Подробный план разработки MVP исторической геоигры

## 1. Цель продукта

Создать веб-игру, в которой пользователь получает изображение исторического события и текстовый вопрос, после чего должен:

1. выбрать предполагаемую точку на карте;
2. указать предполагаемый год события;
3. отправить ответ;
4. увидеть правильное место, правильный год, величину ошибки и начисленные очки;
5. пройти несколько вопросов и получить итоговый результат раунда.

Одновременно должна существовать закрытая административная панель, в которой редактор сможет самостоятельно создавать вопросы, загружать изображения, указывать правильную точку на карте и собирать вопросы в игровые подборки.

Главная цель MVP — проверить, интересно ли пользователям угадывать исторические события одновременно по месту и времени. Первая версия должна быть достаточно простой, чтобы её можно было выпустить быстро, но не должна создавать архитектурный тупик при росте нагрузки.

---

## 2. Зафиксированные технические решения

| Область | Решение |
|---|---|
| Backend | Laravel |
| Публичный frontend | Vue 3 + TypeScript |
| Сборка frontend | Vite |
| Карта | Leaflet 1.9.x |
| Административная панель | Filament |
| База данных | PostgreSQL |
| Cache и sessions | Redis |
| Хранение изображений | S3-совместимое object storage |
| Раздача изображений | CDN |
| Первый production runtime | Nginx + PHP-FPM |
| Возможное ускорение | Laravel Octane + Swoole/OpenSwoole |
| Backend-тесты | Pest или PHPUnit |
| Frontend unit-тесты | Vitest |
| End-to-end тесты | Playwright |
| Контейнеризация разработки | Docker Compose |

Публичная часть будет SPA на Vue. Laravel и Vue находятся в одном репозитории и разворачиваются вместе. Laravel обслуживает API и отдаёт собранный Vue bundle. Такой подход упрощает MVP:

- один домен;
- один deployment;
- нет отдельной CORS-конфигурации;
- проще использовать cookies и CSRF-защиту;
- frontend и backend меняются в одной pull request;
- позже frontend можно вынести отдельно без изменения модели данных.

Filament используется только для закрытой админки. Делать собственную Vue-админку в MVP не требуется.

---

## 3. Границы MVP

### 3.1. Что входит в MVP

#### Публичная часть

- главная страница;
- список опубликованных подборок;
- запуск анонимной игровой сессии;
- раунд из заданного количества вопросов;
- изображение события;
- текст вопроса;
- выбор точки на Leaflet-карте;
- ввод года;
- отправка ответа;
- серверный расчёт расстояния и очков;
- показ правильного места и года;
- краткое историческое пояснение;
- переход к следующему вопросу;
- экран итогов раунда;
- восстановление текущего состояния после перезагрузки страницы;
- адаптация для телефона и компьютера.

#### Административная часть

- авторизация администратора;
- CRUD исторических вопросов;
- загрузка и предпросмотр изображения;
- выбор точки на Leaflet-карте;
- указание года;
- ввод вопроса и пояснения;
- сохранение источника и лицензии изображения;
- черновик и публикация;
- CRUD подборок;
- добавление вопросов в подборку;
- изменение порядка вопросов;
- предварительный просмотр вопроса;
- фильтрация вопросов по статусу и сложности.

#### Инфраструктура

- development-окружение через Docker Compose;
- автоматические тесты;
- production-конфигурация PHP-FPM;
- Redis;
- queue worker;
- резервное копирование PostgreSQL;
- health check;
- базовый мониторинг ошибок.

### 3.2. Что не входит в MVP

- обязательная регистрация игроков;
- OAuth и социальный вход;
- глобальный рейтинг;
- друзья и команды;
- синхронный multiplayer;
- WebSocket;
- достижения;
- платные подписки;
- мобильное приложение;
- сложная модерация пользовательского контента;
- несколько языков;
- AI-генерация вопросов;
- автоматический поиск исторических изображений;
- собственный сервер картографических тайлов;
- SSR;
- микросервисы;
- Kubernetes;
- Laravel Octane до появления измеренной необходимости.

---

## 4. Пользовательские сценарии

### 4.1. Запуск игры

1. Пользователь открывает главную страницу.
2. Frontend получает список опубликованных подборок.
3. Пользователь выбирает подборку.
4. Vue отправляет запрос на создание игровой сессии.
5. Laravel выбирает вопросы и фиксирует их порядок.
6. Пользователь перенаправляется на `/play/{gameId}`.
7. API возвращает только публичные данные первого вопроса.

### 4.2. Ответ на вопрос

1. Пользователь изучает изображение и описание.
2. Пользователь кликает по карте.
3. Vue устанавливает маркер и сохраняет `latitude` и `longitude`.
4. Пользователь вводит год.
5. Кнопка ответа становится активной только после выбора точки и года.
6. Vue отправляет ответ с уникальным `idempotency_key`.
7. Laravel проверяет, что вопрос относится к текущей сессии и ещё не отвечен.
8. Сервер вычисляет расстояние, ошибку по году и очки.
9. Результат записывается в PostgreSQL.
10. API возвращает правильную точку, правильный год и пояснение.
11. Vue показывает два маркера, линию между ними и breakdown очков.

### 4.3. Переход к следующему вопросу

1. Пользователь нажимает «Следующий вопрос».
2. Vue отправляет команду `advance`.
3. Laravel переводит игровую сессию к следующей позиции.
4. API возвращает следующий публичный вопрос.
5. После последнего вопроса сессия получает статус `completed`.

Отдельная команда `advance` нужна для того, чтобы после перезагрузки страницы пользователь не потерял экран с результатом только что отправленного ответа.

### 4.4. Создание вопроса администратором

1. Администратор открывает Filament.
2. Создаёт новый вопрос со статусом `draft`.
3. Загружает изображение.
4. Указывает источник и лицензию.
5. Добавляет текст вопроса.
6. Выбирает место на Leaflet-карте.
7. Указывает год.
8. Добавляет пояснение, которое игрок увидит после ответа.
9. Открывает preview.
10. Публикует вопрос.

---

## 5. Архитектура приложения

```text
Браузер
└── Vue 3 SPA
    ├── Vue Router
    ├── Pinia
    ├── Leaflet
    └── API client
           │
           ▼
Laravel
├── Web/API controllers
├── Form Requests
├── Actions / Services
├── Eloquent models
├── API Resources
├── Filament Admin
├── Queue jobs
└── Cache layer
     │        │          │
     ▼        ▼          ▼
PostgreSQL  Redis      S3/CDN
```

### 5.1. Принципы backend

- Контроллеры остаются тонкими.
- Валидация находится в Form Request.
- Игровые операции реализуются отдельными Action-классами.
- Eloquent используется напрямую без избыточного Repository pattern.
- Ответы API формируются через API Resources.
- Все очки рассчитываются на сервере.
- Правильный ответ не попадает в payload до успешной отправки догадки.
- Результат сохраняется в момент ответа и не пересчитывается задним числом.
- Все операции ответа выполняются идемпотентно.

### 5.2. Предлагаемая структура backend

```text
app/
├── Actions/
│   └── Game/
│       ├── StartGame.php
│       ├── SubmitAnswer.php
│       ├── AdvanceGame.php
│       └── CompleteGame.php
├── DTO/
│   └── Game/
├── Enums/
│   ├── GameSessionStatus.php
│   ├── QuestionStatus.php
│   └── QuizStatus.php
├── Filament/
│   ├── Resources/
│   └── Forms/
├── Http/
│   ├── Controllers/Api/V1/
│   ├── Requests/
│   └── Resources/
├── Jobs/
│   └── ProcessQuestionImage.php
├── Models/
├── Services/
│   ├── DistanceCalculator.php
│   ├── ScoreCalculator.php
│   └── ImageProcessor.php
└── Policies/
```

### 5.3. Принципы frontend

- Vue 3 Composition API.
- Все компоненты используют `<script setup lang="ts">`.
- Pinia хранит только состояние, нужное нескольким экранам.
- Сервер остаётся источником истины для игровой сессии.
- После reload состояние восстанавливается через API.
- Карта инкапсулирована в отдельных компонентах.
- Компоненты экранов не работают с Leaflet API напрямую.
- Для каждого запроса существуют loading, error и retry состояния.

### 5.4. Предлагаемая структура frontend

```text
resources/js/
├── api/
│   ├── client.ts
│   ├── games.ts
│   └── quizzes.ts
├── components/
│   ├── game/
│   │   ├── QuestionImage.vue
│   │   ├── QuestionPrompt.vue
│   │   ├── YearInput.vue
│   │   ├── ScoreBreakdown.vue
│   │   └── RoundProgress.vue
│   ├── maps/
│   │   ├── LeafletMapBase.vue
│   │   ├── GuessMap.vue
│   │   └── AnswerMap.vue
│   └── ui/
├── composables/
│   ├── useGameApi.ts
│   ├── useLeafletMap.ts
│   └── useAsyncState.ts
├── pages/
│   ├── HomePage.vue
│   ├── QuizListPage.vue
│   ├── GamePage.vue
│   ├── GameResultPage.vue
│   └── NotFoundPage.vue
├── router/
├── stores/
│   └── game.ts
├── types/
│   ├── api.ts
│   ├── game.ts
│   └── quiz.ts
├── App.vue
└── main.ts
```

---

## 6. Модель данных

### 6.1. `questions`

| Поле | Тип | Назначение |
|---|---|---|
| `id` | bigint | Внутренний идентификатор |
| `public_id` | uuid | Идентификатор для API |
| `answer` | varchar | Ответ: что изображено (показывается после ответа, заголовок в админке) |
| `image_original_path` | varchar | Оригинал изображения |
| `image_large_path` | varchar | Большая web-версия |
| `image_medium_path` | varchar | Средняя web-версия |
| `latitude` | decimal(9,6) | Правильная широта |
| `longitude` | decimal(9,6) | Правильная долгота |
| `answer_year` | integer | Правильный год |
| `location_name` | varchar | Название места после ответа |
| `reveal_text` | text | Описание, показывается после ответа |
| `source_url` | varchar | Ссылка на источник |
| `image_author` | varchar nullable | Автор изображения |
| `image_license` | varchar | Лицензия или public domain |
| `difficulty` | smallint | Уровень сложности |
| `status` | enum/varchar | draft, published, archived |
| `published_at` | timestamp nullable | Дата публикации |
| timestamps | timestamps | Служебные поля |

Для MVP достаточно хранить координаты как два числовых поля. PostGIS не нужен, потому что приложение сравнивает только две известные точки. Расстояние рассчитывается формулой Haversine в PHP. PostGIS можно добавить позже для географического поиска, регионов и аналитики.

### 6.2. Работа с годами до нашей эры

Если такие вопросы входят в MVP:

- год хранится как signed integer;
- `-44` означает 44 год до н. э.;
- значение `0` запрещено;
- отображение выполняется через общий formatter;
- расчёт разницы должен учитывать отсутствие нулевого года.

Если первая подборка относится только к современной истории, поддержку отрицательных годов можно протестировать, но не выводить в интерфейсе до появления соответствующего контента.

### 6.3. `quizzes`

| Поле | Тип |
|---|---|
| `id` | bigint |
| `public_id` | uuid |
| `title` | varchar |
| `slug` | varchar unique |
| `description` | text nullable |
| `cover_image_path` | varchar nullable |
| `round_size` | smallint |
| `status` | varchar |
| `published_at` | timestamp nullable |
| timestamps | timestamps |

### 6.4. `quiz_questions`

| Поле | Тип |
|---|---|
| `quiz_id` | foreign key |
| `question_id` | foreign key |
| `position` | integer |
| `weight` | smallint default 1 |

Ограничения:

- unique `(quiz_id, question_id)`;
- index `(quiz_id, position)`.

### 6.5. `game_sessions`

| Поле | Тип |
|---|---|
| `id` | bigint |
| `public_id` | uuid unique |
| `quiz_id` | foreign key |
| `anonymous_id` | uuid/index |
| `status` | varchar |
| `current_position` | smallint |
| `total_score` | integer |
| `started_at` | timestamp |
| `completed_at` | timestamp nullable |
| timestamps | timestamps |

Статусы:

```text
in_progress
completed
abandoned
```

### 6.6. `game_session_questions`

Таблица фиксирует конкретный набор вопросов в начатой игре.

| Поле | Тип |
|---|---|
| `game_session_id` | foreign key |
| `question_id` | foreign key |
| `position` | smallint |

Ограничения:

- unique `(game_session_id, position)`;
- unique `(game_session_id, question_id)`.

### 6.7. `answers`

| Поле | Тип |
|---|---|
| `id` | bigint |
| `game_session_id` | foreign key |
| `question_id` | foreign key |
| `idempotency_key` | uuid |
| `guessed_latitude` | decimal(9,6) |
| `guessed_longitude` | decimal(9,6) |
| `guessed_year` | integer |
| `distance_meters` | integer |
| `year_difference` | integer |
| `geo_score` | integer |
| `year_score` | integer |
| `total_score` | integer |
| `duration_ms` | integer nullable |
| `answered_at` | timestamp |

Ограничения:

- unique `(game_session_id, question_id)`;
- unique `(game_session_id, idempotency_key)`;
- index `(question_id, answered_at)` для будущей аналитики.

---

## 7. API-контракт

Все endpoints получают префикс `/api/v1`.

### 7.1. Получить опубликованные подборки

```http
GET /api/v1/quizzes
```

Возвращаются только `published` подборки.

### 7.2. Начать игру

```http
POST /api/v1/game-sessions
```

```json
{
  "quiz_id": "uuid"
}
```

Сервер:

1. проверяет публикацию подборки;
2. выбирает вопросы;
3. создаёт `game_session`;
4. фиксирует порядок в `game_session_questions`;
5. возвращает текущий публичный вопрос.

### 7.3. Получить состояние игры

```http
GET /api/v1/game-sessions/{publicId}
```

Если текущий вопрос ещё не отвечен, правильные данные отсутствуют. Если ответ уже отправлен, сервер возвращает reveal для этого ответа.

### 7.4. Отправить ответ

```http
POST /api/v1/game-sessions/{publicId}/answers
```

```json
{
  "question_id": "uuid",
  "latitude": 41.715137,
  "longitude": 44.827096,
  "year": 1921,
  "idempotency_key": "uuid",
  "duration_ms": 18450
}
```

Успешный ответ включает:

- правильные координаты;
- правильный год;
- название места;
- расстояние;
- ошибку в годах;
- очки;
- reveal-текст;
- attribution изображения.

### 7.5. Перейти дальше

```http
POST /api/v1/game-sessions/{publicId}/advance
```

Разрешено только после ответа на текущий вопрос.

### 7.6. Получить итог

```http
GET /api/v1/game-sessions/{publicId}/result
```

Итог доступен после завершения раунда и содержит score по каждому вопросу и общую сумму.

### 7.7. Общие правила API

- Laravel session cookie связывает браузер с анонимным игроком.
- Изменяющие состояние запросы защищены CSRF.
- Доступ к чужой активной сессии запрещён.
- UUID используется во внешних URL вместо последовательного ID.
- Повтор запроса с тем же `idempotency_key` возвращает ранее созданный результат.
- Клиентские значения score игнорируются.
- API никогда не принимает `distance` или `total_score` от frontend.

---

## 8. Подсчёт результата

### 8.1. Географическое расстояние

Для MVP используется формула Haversine.

```text
distanceKm = haversine(userPoint, answerPoint)
```

Расчёт должен быть детерминированным и покрыт unit-тестами для:

- одинаковых точек;
- соседних городов;
- противоположных сторон Земли;
- перехода через линию перемены дат.

### 8.2. Начальная формула очков

```text
geoScore  = round(5000 × exp(-distanceKm / geoScale))
yearScore = round(5000 × exp(-yearDifference / yearScale))
total     = geoScore + yearScore
```

Начальные значения:

```text
geoScale  = 2000
yearScale = 25
```

Эти значения должны находиться в конфигурации, а не быть разбросаны по контроллерам.

В `answers` сохраняются уже рассчитанные значения. Если формула изменится, старые результаты останутся прежними.

Перед публичным запуском формулу нужно проверить на 15–20 примерах и убедиться, что распределение очков ощущается справедливым.

---

## 9. Leaflet-карта

### 9.1. Карта игрока

`GuessMap.vue` должен:

- инициализировать Leaflet в `onMounted`;
- удалить карту через `map.remove()` в `onBeforeUnmount`;
- принимать клик или tap;
- создавать один draggable marker;
- обновлять `latitude` и `longitude` через `v-model`;
- поддерживать pinch zoom;
- блокировать редактирование после отправки;
- вызывать `invalidateSize()` после изменения layout;
- показывать обязательную атрибуцию tile-провайдера.

### 9.2. Карта ответа

`AnswerMap.vue` должен:

- показывать маркер пользователя;
- показывать правильный маркер другим цветом;
- рисовать polyline;
- использовать `fitBounds()`;
- показывать расстояние;
- быть read-only.

### 9.3. Порядок координат

Leaflet использует:

```text
[latitude, longitude]
```

В приложении координаты нельзя передавать как неименованный массив за пределами Leaflet-компонента. В API, store и backend используются только именованные поля:

```ts
type Coordinates = {
  latitude: number;
  longitude: number;
};
```

### 9.4. Тайлы

- Leaflet не предоставляет картографические тайлы.
- URL тайлов и attribution задаются через environment/config.
- Для локальной разработки допустим тестовый слой.
- Для production используется tile-провайдер с подходящими условиями и SLA.
- URL tile-провайдера не должен быть жёстко зашит во множество компонентов.

### 9.5. Filament map picker

Для формы Question создаётся переиспользуемое поле `LeafletLocationPicker`:

- хранит latitude и longitude;
- поддерживает клик и drag;
- показывает существующее значение при редактировании;
- имеет числовые поля для точной корректировки;
- валидирует диапазоны `-90..90` и `-180..180`.

---

## 10. Работа с изображениями

### 10.1. Обязательные проверки

- допустимые форматы: JPEG, PNG и WebP;
- проверяется реальный MIME-type;
- ограничивается максимальный размер, например 15 MB;
- изображение декодируется и повторно кодируется;
- EXIF и GPS удаляются;
- оригинальное имя не используется в публичном URL;
- ключ объекта строится на UUID.

### 10.2. Варианты изображения

Рекомендуемые размеры:

```text
medium: до 960 px по длинной стороне
large:  до 1600 px по длинной стороне
```

Frontend использует `srcset` или выбирает вариант по viewport. Оригинал не отдаётся игроку без необходимости.

### 10.3. Авторские права

Нельзя публиковать вопрос без заполненных полей:

- `source_url`;
- `image_license`;
- автора, если он известен;
- attribution-текста, если его требует лицензия.

После ответа attribution отображается пользователю.

---

## 11. Административная панель

### 11.1. Question Resource

Форма разделена на блоки:

1. **Основное** — answer, difficulty, reveal text (описание).
2. **Изображение** — upload, preview, source, author, license.
3. **Правильный ответ** — Leaflet picker, latitude, longitude, year, location name.
4. **Публикация** — status, published_at.

Список вопросов показывает:

- thumbnail;
- answer;
- year;
- difficulty;
- status;
- количество подборок;
- дату изменения.

Фильтры:

- status;
- difficulty;
- наличие/отсутствие лицензии;
- подборка.

Bulk actions:

- publish;
- return to draft;
- archive;
- добавить в подборку.

### 11.2. Quiz Resource

- title и description;
- обложка;
- round size;
- relation manager вопросов;
- drag-and-drop ordering;
- preview;
- status;
- проверка, что опубликованных вопросов не меньше `round_size`.

### 11.3. Права доступа

В MVP достаточно двух ролей:

- `admin` — всё;
- `editor` — вопросы и подборки без управления пользователями.

---

## 12. Безопасность и защита от простого читерства

- Правильные координаты и год не включаются в начальный API payload.
- Score рассчитывается только Laravel.
- Изображения получают непрозрачные UUID-имена.
- EXIF удаляется.
- Неопубликованные вопросы недоступны публичному API.
- API использует rate limiting.
- Повторный ответ блокируется database constraint.
- Активная игра привязана к анонимной browser session.
- Админка защищена отдельной авторизацией.
- Все текстовые значения экранируются.
- HTML в `answer` и `reveal_text` либо запрещается, либо очищается allowlist-sanitizer.
- Upload не доверяет расширению файла.
- Production secrets не хранятся в репозитории.

Полностью защититься от reverse image search невозможно. Для MVP это принимается как ограничение продукта.

---

## 13. Тестирование

### 13.1. Backend unit-тесты

- Haversine для известных пар координат;
- одинаковые точки дают нулевое расстояние;
- расчёт year difference;
- расчёт очков;
- отрицательные годы, если они включены;
- минимальный и максимальный score.

### 13.2. Backend feature-тесты

- отображаются только опубликованные подборки;
- нельзя запустить unpublished quiz;
- создаётся правильное количество session questions;
- порядок фиксируется;
- до ответа API не содержит правильных координат и года;
- валидный ответ сохраняется;
- повторный ответ не создаёт вторую запись;
- idempotency возвращает тот же результат;
- нельзя ответить на чужую сессию;
- нельзя перейти дальше до ответа;
- последний вопрос завершает игру;
- итоговый score совпадает с суммой ответов;
- draft question не попадает в новую игру.

### 13.3. Frontend unit-тесты

- `YearInput` валидирует значение;
- кнопка submit заблокирована без точки;
- кнопка submit заблокирована без года;
- store корректно применяет состояние API;
- повторный клик submit не отправляет второй запрос;
- score breakdown корректно форматируется;
- formatter корректно отображает годы до н. э.

### 13.4. Leaflet-тесты

Логику координат следует тестировать отдельно от реального canvas/DOM Leaflet:

- клик преобразуется в `Coordinates`;
- marker update вызывает emit;
- после `disabled=true` изменения игнорируются;
- reveal получает обе точки;
- `fitBounds` вызывается после появления обеих точек.

### 13.5. Playwright end-to-end

Основной сценарий:

```text
администратор создаёт вопрос
→ создаёт подборку
→ публикует её
→ игрок начинает игру
→ выбирает точку и год
→ отправляет ответ
→ видит reveal
→ проходит весь раунд
→ видит итог
```

Дополнительные сценарии:

- reload до ответа;
- reload после ответа;
- мобильный viewport;
- ошибка сети и retry;
- double click по кнопке ответа;
- возврат к завершённой игре.

---

## 14. Производительность

### 14.1. Правила MVP

- Изображения не проходят через PHP.
- Публичные данные Question кэшируются в Redis.
- При ответе правильные данные можно получать из Redis.
- `SubmitAnswer` выполняет не более одного обязательного INSERT и минимальное число чтений.
- В списках админки устраняются N+1 queries.
- Для больших изображений всегда используется CDN.
- Vue bundle разбивается по маршрутам.

### 14.2. Начальный performance budget

На сервере 2 vCPU / 4 GB:

- p95 cached GET API: менее 200 ms;
- p95 submit answer: менее 300 ms;
- error rate: менее 1%;
- отсутствие устойчивого swap;
- PostgreSQL slow queries отсутствуют в основном игровом flow.

### 14.3. Нагрузочные сценарии

Нужно подготовить k6-сценарии:

1. Получение списка подборок.
2. Создание игровой сессии.
3. Получение текущего вопроса.
4. Отправка ответа.
5. Полный раунд из 5–10 вопросов.
6. Кратковременный burst одновременных ответов.

### 14.4. Когда рассматривать Octane

Переход рассматривается только если после оптимизации SQL, cache и CDN выполняется одно из условий:

- CPU PHP-FPM становится реальным bottleneck;
- ожидается более 500–1000 одновременно активных игроков;
- p95 не укладывается в budget из-за повторного bootstrap Laravel;
- количество FPM workers упирается в память;
- есть подтверждённая польза на нагрузочном тесте.

Перед переходом:

1. проверить пакеты на совместимость;
2. найти mutable static state;
3. проверить singleton-сервисы;
4. убедиться, что Request/User/Session не сохраняются в singleton;
5. провести длительный soak test;
6. запустить сначала 2 workers;
7. сравнить 2 и 4 workers;
8. оставить готовый rollback на PHP-FPM.

---

## 15. Development-окружение

### 15.1. Docker Compose services

```text
app          PHP-FPM + Laravel
nginx        web server
postgres     database
redis        cache/session/queue
queue        Laravel queue worker
mailpit      локальная почта, если потребуется
```

S3 в локальной разработке можно заменить local filesystem или MinIO. Код приложения в любом случае работает через Laravel Storage abstraction.

### 15.2. Обязательные команды проекта

Проект должен иметь короткие стандартные команды через Makefile или Composer/NPM scripts:

```text
setup
start
stop
test
lint
analyse
build
e2e
```

---

## 16. CI/CD

### 16.1. Проверки pull request

- Composer validation;
- PHP formatter;
- Larastan/PHPStan;
- backend tests;
- ESLint;
- TypeScript typecheck;
- Vitest;
- frontend production build;
- migration smoke test;
- минимальный Playwright smoke test.

### 16.2. Production deployment

1. CI собирает Vue assets.
2. Создаётся release artifact или Docker image.
3. Загружается новая версия.
4. Выполняются migrations.
5. Очищается и прогревается Laravel cache.
6. Перезапускается queue worker.
7. Выполняется health check.
8. При ошибке приложение возвращается на предыдущий release.

Frontend не следует собирать непосредственно на слабом production VPS, если сборку можно выполнить в CI.

---

## 17. Production-конфигурация первого этапа

Ориентир для MVP:

```text
2 shared vCPU
4 GB RAM
80 GB NVMe
Linux
Nginx
PHP-FPM
PostgreSQL
Redis
1 queue worker
S3/CDN для изображений
внешний tile provider
```

Примерное распределение памяти:

| Компонент | Ориентир |
|---|---:|
| ОС и Nginx | 300–450 MB |
| PostgreSQL | 700–1000 MB |
| Redis | 128–256 MB |
| PHP-FPM | 800–1400 MB |
| Queue worker | 200–400 MB |
| Резерв | 500+ MB |

Точные значения задаются после измерения памяти реального приложения.

---

## 18. Наблюдаемость и обслуживание

Минимально необходимы:

- централизованный сбор Laravel exceptions;
- uptime monitor;
- `/health` endpoint;
- контроль свободного диска;
- контроль памяти и swap;
- лог медленных запросов PostgreSQL;
- ежедневный backup базы;
- регулярная проверка восстановления backup;
- алерт при росте queue backlog;
- исключение чувствительных данных из логов.

`/health` должен проверять:

- запуск приложения;
- соединение с PostgreSQL;
- соединение с Redis;
- доступность storage без тяжёлой записи на каждый health request.

---

## 19. Этапы разработки

### Этап 0. Продуктовая фиксация — 1 день

- определить первую тему подборки;
- подготовить 15–20 реальных вопросов;
- утвердить формулу очков;
- утвердить количество вопросов в раунде;
- сделать простой wireframe desktop/mobile;
- зафиксировать правила публикации изображений.

**Результат:** понятен конкретный контент и игровой flow.

### Этап 1. Основа проекта — 1–2 дня

- Laravel;
- Vue 3 + TypeScript + Vite;
- Vue Router и Pinia;
- PostgreSQL;
- Redis;
- Filament;
- Docker Compose;
- базовый CI;
- общая обработка API errors.

**Результат:** приложение запускается одной командой, тесты выполняются в CI.

### Этап 2. Модели и migrations — 2–3 дня

- questions;
- quizzes;
- quiz_questions;
- game_sessions;
- game_session_questions;
- answers;
- enums;
- factories;
- seeders;
- constraints и indexes.

**Результат:** доменная модель готова и покрыта базовыми тестами.

### Этап 3. Filament-админка — 3–5 дней

- Question Resource;
- Quiz Resource;
- Leaflet location picker;
- upload и image processing;
- preview;
- draft/publish;
- ordering;
- роли admin/editor.

**Результат:** весь контент можно подготовить без ручной работы с базой.

### Этап 4. Игровой API — 3–5 дней

- запуск сессии;
- выдача текущего вопроса;
- SubmitAnswer action;
- Haversine;
- score calculator;
- idempotency;
- advance;
- завершение;
- итоговый результат;
- Redis cache;
- feature-тесты.

**Результат:** полный раунд работает через API.

### Этап 5. Vue frontend — 5–8 дней

- главная;
- список подборок;
- GamePage;
- GuessMap;
- YearInput;
- submit flow;
- AnswerMap;
- ScoreBreakdown;
- progress;
- result page;
- reload recovery;
- mobile layout;
- обработка сетевых ошибок.

**Результат:** пользователь проходит игру в браузере.

### Этап 6. QA и безопасность — 3–5 дней

- unit и feature tests;
- Playwright;
- проверка API на утечки ответов;
- upload hardening;
- rate limits;
- permission checks;
- accessibility pass;
- cross-browser проверка;
- контентная проверка.

**Результат:** release candidate.

### Этап 7. Deploy и нагрузочный тест — 2–3 дня

- production VPS;
- TLS;
- database backup;
- object storage/CDN;
- tile provider;
- monitoring;
- k6 baseline;
- устранение bottleneck;
- публикация MVP.

**Результат:** публично доступная версия с измеренной baseline-производительностью.

### Общая оценка

Для одного разработчика, уверенно владеющего Laravel:

```text
ориентировочно 20–30 рабочих дней
```

При готовом дизайне и заранее подготовленном контенте MVP можно приблизить к 3–4 неделям. Если дизайн и исторический контент создаются параллельно разработке, стоит планировать 4–6 недель.

---

## 20. Критерии готовности MVP

MVP считается готовым, когда:

- администратор создаёт и публикует вопрос без помощи разработчика;
- администратор создаёт подборку и меняет порядок вопросов;
- пользователь начинает игру без регистрации;
- игровой раунд содержит правильное количество вопросов;
- правильные координаты и год отсутствуют в API до ответа;
- пользователь может выбрать точку на Leaflet-карте;
- пользователь может указать год;
- ответ нельзя отправить дважды;
- score рассчитывается сервером;
- после ответа показываются обе точки и линия;
- reload не ломает активную игру;
- завершённый раунд показывает итог;
- изображения не содержат GPS EXIF;
- мобильный сценарий проходит полностью;
- ключевые backend и frontend тесты выполняются в CI;
- production имеет backup и health check;
- проведён нагрузочный baseline на PHP-FPM.

---

## 21. Основные риски

| Риск | Вероятность | Мера |
|---|---|---|
| Утечка правильного ответа в API | Средняя | Отдельные public/reveal resources и feature-тест |
| Пользователь найдёт изображение через reverse search | Высокая | Принять для MVP, позже таймер/кропы |
| Нарушение лицензии изображения | Средняя | Обязательные source/license поля |
| Плохой UX карты на телефоне | Средняя | Mobile-first и ранний прототип |
| Перепутаны latitude/longitude | Средняя | Именованный Coordinates type и тесты |
| Слабый tile provider | Средняя | Конфигурация через env и возможность замены |
| Слишком сложная формула очков | Средняя | Тестирование формулы на реальных вопросах |
| N+1 и лишние Eloquent queries | Средняя | Query budget и profiling |
| Преждевременный переход на Octane | Средняя | Сначала baseline PHP-FPM |
| Потеря данных на одном VPS | Средняя | Внешние backups и object storage |

---

## 22. Codex Skills для репозитория

Skills лучше создавать вокруг конкретных повторяемых workflows. Общие постоянные правила проекта следует держать в `AGENTS.md`, а пошаговые процессы — в отдельных `SKILL.md`.

Рекомендуемая структура:

```text
.codex/skills/
├── implement-game-feature/
│   └── SKILL.md
├── build-laravel-api/
│   └── SKILL.md
├── build-vue-game-screen/
│   └── SKILL.md
├── build-filament-resource/
│   └── SKILL.md
├── leaflet-map-workflow/
│   └── SKILL.md
├── review-octane-readiness/
│   └── SKILL.md
└── run-quality-gate/
    └── SKILL.md
```

### 22.1. `implement-game-feature`

Используется для вертикального изменения игровой механики. Требует проверить:

- migration/model;
- backend action;
- API contract;
- Vue UI;
- отсутствие утечки правильного ответа;
- тесты;
- документацию изменения.

### 22.2. `build-laravel-api`

Используется при создании endpoint. Требует:

- Form Request;
- тонкий controller;
- Action/Service;
- API Resource;
- authorization;
- transaction при необходимости;
- idempotency для mutations;
- feature-тест;
- query budget.

### 22.3. `build-vue-game-screen`

Используется для игровых Vue-экранов. Закрепляет:

- Composition API;
- TypeScript;
- typed props/emits;
- Pinia только для shared state;
- loading/error/retry;
- mobile-first;
- accessibility;
- Vitest.

### 22.4. `build-filament-resource`

Используется для административных ресурсов. Проверяет:

- form schema;
- validation;
- filters;
- permissions;
- draft/publish;
- preview;
- отсутствие N+1;
- feature-тесты критической логики.

### 22.5. `leaflet-map-workflow`

Закрепляет правила:

- Leaflet 1.9.x;
- `[latitude, longitude]` только внутри Leaflet boundary;
- cleanup через `map.remove()`;
- `invalidateSize()`;
- tile attribution;
- GuessMap и AnswerMap;
- Filament picker;
- тестирование координат;
- отсутствие зависимости бизнес-логики от Leaflet объектов.

### 22.6. `review-octane-readiness`

Используется перед подключением Octane или при ревью долгоживущих сервисов. Ищет:

- request/user/session в singleton;
- mutable static state;
- глобальные накопители;
- незакрытые транзакции и ресурсы;
- несовместимые пакеты;
- memory growth;
- отсутствие worker restart strategy.

### 22.7. `run-quality-gate`

Используется перед завершением задачи:

- formatter;
- Larastan/PHPStan;
- backend tests;
- ESLint;
- TypeScript check;
- Vitest;
- production build;
- Playwright smoke;
- migration check;
- проверка API на утечку answer data.

Дополнительные skills после MVP:

- `load-test-game-api`;
- `optimize-database-query`;
- `process-historical-image`;
- `prepare-production-release`;
- `diagnose-production-incident`.

---

## 23. Рекомендуемый порядок старта

Первые практические задачи:

1. Создать репозиторий и `AGENTS.md` с архитектурными правилами.
2. Поднять Laravel, Vue, PostgreSQL и Redis.
3. Создать модели Question и Quiz.
4. Сделать Filament Question Resource с Leaflet picker.
5. Загрузить 10 реальных исторических вопросов.
6. Реализовать StartGame и SubmitAnswer.
7. Собрать один полноценный Vue GamePage.
8. Пройти раунд end-to-end.
9. Только после этого расширять дизайн, фильтры и аналитику.

Самая полезная первая вертикаль:

```text
один вопрос в админке
→ один API-ответ
→ один Vue-экран
→ выбор точки и года
→ серверный score
→ reveal
```

После успешной вертикали можно безопасно добавлять подборки, несколько вопросов, итоговый экран и обработку изображений.

---

## 24. Ссылки на основную документацию

- Laravel: <https://laravel.com/docs>
- Laravel Octane: <https://laravel.com/docs/octane>
- Vue: <https://vuejs.org/guide/introduction.html>
- Leaflet: <https://leafletjs.com/>
- Leaflet API: <https://leafletjs.com/reference.html>
- Filament: <https://filamentphp.com/docs>
- PostgreSQL: <https://www.postgresql.org/docs/>
- Redis: <https://redis.io/docs/latest/>
- OpenStreetMap tile policy: <https://operations.osmfoundation.org/policies/tiles/>
- Codex Skills: <https://developers.openai.com/api/docs/guides/tools-skills>

