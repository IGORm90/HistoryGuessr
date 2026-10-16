# HistoryGuessr

Историческая геоигра: угадай место и год события по историческому изображению.

## Быстрый старт

Нужны Docker и Node.js 22+.

```sh
make setup   # сборка образов, зависимости, миграции и seed
make start   # http://localhost:8080, админка — http://localhost:8080/hstradmn
```

Логины для разработки: `admin@example.com` / `editor@example.com`, пароль `password`.

| Сервис | Адрес |
|---|---|
| Приложение | http://localhost:8080 |
| Vite dev server | http://localhost:5173 |
| S3 (RustFS) console | http://localhost:9001 |
| Mailpit | http://localhost:8025 |

Команды: `make test`, `make lint`, `make analyse`, `make build`, `make help`.

Правила архитектуры и разработки — в [CLAUDE.md](CLAUDE.md). Продуктовый план — в [mvp-plan.md](mvp-plan.md).
