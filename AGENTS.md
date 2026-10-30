# Project instructions

## Product scope

- Build the complete library management system as a modular monolith.
- Keep the application a modular monolith with a separate Vue frontend and Laravel REST API.
- Laravel owns business rules, authentication, authorization, and persistence. Vue owns presentation and client-side interaction.
- Keep PostgreSQL as the primary database. Model book titles separately from their physical copies.
- Include a visible chatbot placeholder only. Do not add an AI provider, API key, embeddings, RAG, or vector database; the chatbot must remain non-functional.

## Implementation standards

- Follow Vue 3, TypeScript, Vite, Pinia, Vue Router, Axios, Tailwind CSS, and shadcn-vue conventions in `frontend/`.
- Follow Laravel conventions in `backend/`. Keep controllers small, validate with Form Requests, authorize with Policies or middleware, and put multi-step business operations in services and database transactions.
- Add constraints, indexes, and foreign keys in migrations. Keep generated secrets in ignored `.env` files.
- Keep API, database, and setup documentation current when their contracts or commands change.
- Do not add dependencies or services without a project need. Redis is not part of the current foundation.

## Development environment

- Run the project through Docker Compose from the repository root in WSL 2 Ubuntu.
- Use `compose.yaml` as the service source of truth. The root `README.md` documents setup, verification, and shutdown commands.
- Frontend dependencies are managed by `frontend/package-lock.json`; backend dependencies are managed by `backend/composer.lock`.
- Keep development and production configuration separate. The current Compose setup is for local development.
