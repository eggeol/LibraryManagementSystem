# API reference

Laravel owns authentication, authorization, validation, circulation rules, and persistence. The Vue app uses same-origin requests through Nginx. JSON API routes use the /api prefix; Sanctum's CSRF endpoint is /sanctum/csrf-cookie.

## Authentication

The browser first requests /sanctum/csrf-cookie, then posts credentials to /api/auth/login. A session cookie authenticates subsequent requests. API requests accept and return JSON.

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| GET | /api/health | Public | Confirms the API and PostgreSQL connection |
| POST | /api/auth/login | Public, rate limited | Start a session |
| POST | /api/auth/register | Public, rate limited | Create a member account; public registration can never assign staff roles |
| GET | /api/auth/me | Signed in | Return the current account |
| POST | /api/auth/logout | Signed in | End the current session |

## Roles

| Capability | Administrator | Librarian | Member |
| --- | --- | --- | --- |
| Browse book titles and availability | Yes | Yes | Yes |
| Manage catalog, copies, and member accounts | Yes | Yes | No |
| Assign administrator or librarian roles | Yes | No | No |
| Check out and return copies | Yes | Yes | No |
| Request or cancel personal reservations | Yes | Yes | Yes |
| Renew personal loans | Yes | Yes | Yes |
| View personal loans, reservations, fines, and notifications | Yes | Yes | Yes |
| Record fine payments | Yes | Yes | No |
| Waive fines | Yes | No | No |
| View reports | Yes | Yes | No |
| Change library settings | Yes | No | No |
| View audit history | Yes | No | No |

Inactive accounts cannot use protected endpoints. Member endpoints automatically scope records to the signed-in member. Librarians see only member accounts in member management.

## Catalog and inventory

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| GET | /api/books | Signed in | Search titles by text, category, and availability; returns paginated data |
| GET | /api/books/{book} | Signed in | View title details and physical copies |
| POST | /api/books | Administrator or librarian | Create a title and link authors, publisher, and categories |
| PUT | /api/books/{book} | Administrator or librarian | Update title metadata and links |
| DELETE | /api/books/{book} | Administrator or librarian | Delete a title only when it has no physical copies |
| GET, POST | /api/lookups/{authors,publishers,categories} | GET: signed in; writes: staff | Search or create catalog metadata |
| PUT, DELETE | /api/lookups/{type}/{id} | Administrator or librarian | Update or remove unlinked catalog metadata |
| GET, POST | /api/copies | Staff | List or add physical copies |
| PUT, DELETE | /api/copies/{copy} | Staff | Update a copy or remove one without circulation history |

Copies use available, borrowed, reserved, damaged, or lost statuses. Borrowed and reserved copies cannot be manually edited or removed.

## Members and circulation

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| GET, POST | /api/members | Staff | Search accounts or create a member |
| PUT, DELETE | /api/members/{member} | Staff | Update or deactivate an account |
| GET | /api/borrowings | Signed in | List own loans; staff can list all and filter by status |
| POST | /api/borrowings | Administrator or librarian | Check out a physical copy to an active member |
| POST | /api/borrowings/{borrowing}/return | Administrator or librarian | Check in a copy, calculate any overdue fine, and allocate a queued reservation |
| POST | /api/borrowings/{borrowing}/renew | Loan owner or staff | Extend a current loan when no one is waiting |
| GET | /api/reservations | Signed in | List own reservations; staff can list all |
| POST | /api/books/{book}/reservations | Active member | Reserve a title or join its waiting queue |
| DELETE | /api/reservations/{reservation} | Owner or staff | Cancel an open reservation |

Reservation holds expire after the configured pickup window. Expired holds are processed when the reservation queue or checkout endpoint is accessed.

## Accounts, reports, and administration

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| GET | /api/fines | Signed in | List own fines; staff can list all |
| POST | /api/fines/{fine}/payments | Administrator or librarian | Record a cash, card, transfer, or other payment |
| POST | /api/fines/{fine}/waive | Administrator | Waive an unpaid balance with a reason |
| GET | /api/notifications | Signed in | List personal notifications and unread count |
| POST | /api/notifications/{id}/read | Signed in | Mark one personal notification as read |
| POST | /api/notifications/read-all | Signed in | Mark all personal notifications as read |
| GET | /api/dashboard | Signed in | Return role-scoped activity and library summary metrics |
| GET | /api/reports | Administrator or librarian | Circulation totals, popular titles, overdue loans, and recent activity |
| GET | /api/settings | Administrator or librarian | Read circulation policy |
| PUT | /api/settings | Administrator | Change library name and circulation policy |
| GET | /api/audit-logs | Administrator | Search the audit history |

Paginated endpoints use Laravel's standard response with data, current_page, last_page, and total fields. Validation failures return field errors with HTTP 422. Authorization failures return HTTP 403.

## Chatbot placeholder

There is no chatbot API. The frontend page is intentionally static and disabled; it has no provider, network call, or credentials.
