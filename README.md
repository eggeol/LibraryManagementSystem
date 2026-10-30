# Web-Based Library Management System

A Dockerized library management application for administrators, librarians, and members. It includes catalog and inventory management, member accounts, borrowing, returns, reservations, fines and payments, reports, notifications, audit history, library settings, and a non-functional chatbot placeholder.

## Architecture

| Service | Location | Responsibility |
| --- | --- | --- |
| Frontend | `frontend/` | Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios, Tailwind CSS, shadcn-vue, and Lucide icons |
| Backend | `backend/` | Laravel REST API, PostgreSQL access, Sanctum session authentication, and library rules |
| Database | PostgreSQL container | Persistent relational data |
| Web entry point | `docker/nginx/` | Serves the Vue dev server and forwards API and Sanctum requests to Laravel PHP-FPM |

The browser uses one origin at `http://localhost:8080`. Nginx sends `/api/*` and `/sanctum/*` requests to Laravel and sends other paths to the Vue development server. The API health endpoint runs a simple PostgreSQL query so the UI can report both service and database connectivity.

## Requirements

- WSL 2 with an Ubuntu distribution
- Docker Desktop with WSL integration enabled
- Docker Compose v2
- Git

Node.js, PHP, and Composer run in containers; they do not need to be installed in the WSL distribution.

## Start the development environment

Run these commands from the repository root in WSL Ubuntu:

```bash
cp .env.example .env
cp backend/.env.example backend/.env
docker compose build
docker compose run --rm --no-deps backend php artisan key:generate
docker compose up -d --wait
docker compose exec backend php artisan migrate --force
docker compose exec backend php artisan db:seed --force
```

Open the app at <http://localhost:8080>. The API health check is available at <http://localhost:8080/api/health>. The local PostgreSQL port is `5432` by default; change `POSTGRES_PORT` in `.env` if that port is already in use.

## Common commands

```bash
# View service and health-check status
docker compose ps

# Follow logs
docker compose logs -f

# Run the frontend production build and TypeScript check
docker compose exec frontend npm run build

# Run backend tests
docker compose exec -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: -e DB_URL= backend php artisan test

# Stop containers; keep PostgreSQL data
docker compose down
```

To reset the local database volume, run `docker compose down -v`; this permanently deletes the development database contents.

## Environment files

- Root `.env` supplies Docker Compose settings. Start with `.env.example`.
- `backend/.env` supplies Laravel's local application key and configuration. Start with `backend/.env.example`, then generate the key with the command above.
- Both actual `.env` files are ignored by Git. The example database password is for local development and should be changed before sharing a development environment.

## Documentation

- [Database schema](docs/database.md)
- [API endpoints](docs/api.md)
- [Architecture and agent instructions](AGENTS.md)

## Demo accounts

The local seeder creates these development accounts. All use the password password.

| Role | Email |
| --- | --- |
| Administrator | admin@library.test |
| Librarian | librarian@library.test |
| Member | member@library.test |

Change these credentials before using a shared environment.

## Chatbot

The navigation includes a chatbot placeholder to reserve space for a future assistant. It does not send messages, call an AI service, or require credentials.
