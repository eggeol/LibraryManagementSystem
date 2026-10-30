# Database schema

PostgreSQL is the primary database. Compose stores its data in the persistent postgres_data volume. Laravel connects to the service host db on port 5432.

## Identity and access

The users table stores account identity, contact details, membership code, active status, and one of three roles: admin, librarian, or member. Role checks are enforced in Laravel middleware and controllers. Sanctum uses session cookies for the first-party Vue application and keeps its personal access token table available for future integrations.

Local seeded accounts:

| Role | Email | Password |
| --- | --- | --- |
| Administrator | admin@library.test | password |
| Librarian | librarian@library.test | password |
| Member | member@library.test | password |

These credentials are for local development only.

## Catalog and physical inventory

- authors, publishers, and categories hold shared catalog metadata.
- books represent bibliographic titles. An ISBN is unique when supplied. Publisher and creator references are nullable.
- author_book and book_category provide many-to-many links without duplicating descriptive data.
- book_copies represent individual physical items, each with a unique accession number and optional unique barcode, shelf location, condition, and status.

Book titles and physical items are separate. Availability counts come from physical copy records.

## Circulation and accounts

- borrowings link one physical copy to a member and record checkout, due, and return times, plus the staff members who issued or received the item.
- A PostgreSQL partial unique index prevents a physical copy from having more than one active borrowing.
- reservations link a member to a title and optionally to the copy being held. Requested reservations are allocated in timestamp order when a copy is returned.
- fines link to the overdue borrowing and member. Payment records capture individual installments and their method, amount, time, and staff receiver.
- library_settings stores policy values such as loan length, fine rate, reservation hold period, and maximum active loans.
- notifications are Laravel database notifications scoped to their recipient.
- audit_logs record important changes, actor, entity, timestamp, request IP, and optional details.

## Integrity and lifecycle

Migrations define foreign keys, unique identifiers, searchable indexes, and delete behavior. Historical copies with borrowing records cannot be deleted; mark them lost instead. A title with copies cannot be deleted. Member accounts with history should be deactivated rather than deleted.

Checkout and return operations use transactions and row locks. Checkout verifies the member, active-loan limit, copy status, and any reservation hold. Return calculates late fees from the configured daily rate and allocates the next queued reservation. Payments cannot exceed the remaining fine balance.

The current schema is defined in backend/database/migrations. To apply it after starting the development services, run:

    docker compose exec backend php artisan migrate --force

To load local accounts and sample titles:

    docker compose exec backend php artisan db:seed --force

To reset local data, docker compose down -v deletes the PostgreSQL volume and every record in it.
