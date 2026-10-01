# Computer Laboratory Booking System

A web-based system for managing and booking computer laboratory facilities
within a school or institution. It replaces paper booking registers and
spreadsheets with a reliable application that guarantees two bookings can
never occupy the same laboratory and time period.

Built for the **Networking & Cloud Computing** department as a final year
project, demonstrating client-server architecture and LAN-based access.

---

## 1. Requirements

| Component | Version used | Notes |
|---|---|---|
| PHP | 5.6 (XAMPP) | The project deliberately targets PHP 5.6 syntax |
| MySQL / MariaDB | MariaDB 10.1.9 | Any MySQL 5.5+ or MariaDB 10+ works |
| Web server | Apache 2.4 | `.htaccess` support required |
| Bootstrap | 5.3.3 | Vendored in `assets/vendor/`, no CDN needed |

> **Why PHP 5.6 syntax?** XAMPP ships PHP 5.6.15, which is end-of-life. The whole
> codebase therefore avoids PHP 7+ features: no `??` operator, no scalar or
> return type declarations, no `list()` short destructuring, no arrow functions
> and no `str_contains()`. If you upgrade to PHP 7.3+ (Laragon ships 8.3), the
> code still runs unchanged, and you may then use modern syntax.

---

## 2. Installation

1. **Copy the project** into your web root so it sits at
   `C:\xampp\htdocs\lab-booking`.

2. **Start Apache and MySQL** from the XAMPP Control Panel.

3. **Create the database.** Either
   - open <http://localhost/phpmyadmin>, create a database called `clbs`, then
     **Import** `database/schema.sql`; or
   - run from a terminal:

     ```
     C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
     ```

   The schema script creates the database itself, so step 3a can be skipped
   entirely.

4. **Check the credentials** in `config/config.php`. The defaults match a stock
   XAMPP install (`root`, no password, `127.0.0.1:3306`).

5. **Load the demo data** so the dashboards have something to show:

   ```
   C:\xampp\php\php.exe database\seed.php
   ```

   Re-running with `--fresh` wipes the tables and reseeds:

   ```
   C:\xampp\php\php.exe database\seed.php --fresh
   ```

6. Open **<http://localhost/lab-booking/>**.

If you imported an **older** `database/schema.sql` into an existing database,
apply the profile picture column as well:

```
C:\xampp\mysql\bin\mysql.exe -u root < database\03_profile_avatar.sql
```

It is safe to re-run, so it can be applied to a current database without
changing anything. `schema.sql` already contains the column, so a fresh install
does not need it.

### Demo accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@lab.edu.zm` | `Admin@123` |
| Administrator | `lecturer@lab.edu.zm` | `Admin@123` |
| Student | `student@lab.edu.zm` | `Student@123` |
| Students 2-10 | `mubanga@lab.edu.zm`, `kphiri@lab.edu.zm`, ... | `Student@123` |

The seed also creates 5 laboratories, 4 time slots and 20 bookings spread
across every status, so the dashboard statistics and charts are populated.

---

## 3. Accessing it from the local network

The system is designed to run on a LAN with **no internet access**. Bootstrap
and the icon font are stored inside `assets/vendor/`, so pages render correctly
on a machine that has never been online.

1. Find the server's LAN address. On Windows:

   ```
   ipconfig
   ```

   Look for the IPv4 address on the adapter connected to the network, for
   example `192.168.1.10`.

2. Make sure Apache listens on all interfaces (the XAMPP default
   `Listen 80` already does).

3. **Allow Apache through Windows Firewall.** By default XAMPP has no
   firewall rule, so other machines are blocked. Either tick
   *Allow access* on the Apache prompt that XAMPP shows, or run this from an
   **administrator** PowerShell prompt:

   ```powershell
   New-NetFirewallRule -DisplayName "Apache HTTP (LAN)" `
     -Direction Inbound -Protocol TCP -LocalPort 80 `
     -Action Allow -Profile Private
   ```

   Restricting the rule to the `Private` profile keeps it off public networks.

4. Other devices then open `http://<server-ip>/lab-booking/` — for example
   `http://192.168.1.10/lab-booking/`.

5. If a classmate still cannot connect, check that Windows is set to a
   *Private* network profile and that the client and server are on the same
   subnet.

---

## 4. Project structure

```
lab-booking/
├── config/            Database credentials and the PDO connection
│   ├── .htaccess          blocks direct web access
│   ├── config.php         constants: credentials, app name, base URL
│   └── database.php       db() -> shared PDO handle
├── public/            Web-servable folder for generated files
│   ├── index.php          redirects to the landing page
│   ├── uploads/           user uploads, with .htaccess denying execution
│   │   └── avatars/       profile pictures, created at runtime
│   └── exports/           (reserved) CSV / PDF report exports
├── assets/
│   ├── css/style.css      custom styles layered on Bootstrap
│   ├── js/app.js          sidebar, password toggle, date guard, reason prompt
│   ├── images/
│   └── vendor/            Bootstrap 5.3.3 + Bootstrap Icons 1.11.3
├── includes/          Shared server-side components
│   ├── .htaccess          blocks direct web access
│   ├── bootstrap.php      single entry point every page requires
│   ├── functions.php      escaping, URLs, queries, flash, CSRF, formatting
│   ├── auth.php           session, login, route guards
│   ├── alerts.php         Bootstrap alert rendering
│   ├── layout.php         layout_start() / layout_end() and page helpers
│   ├── header.php         <head>, opening <body>, navbar
│   ├── navbar.php         top bar
│   ├── sidebar.php        role-aware sidebar
│   ├── footer.php        footer, scripts, closing tags
│   ├── booking_rules.php  availability, validation, conflicts, insert, status changes
│   ├── laboratories.php   laboratory rules: validation, add, edit, status, delete
│   ├── time_slots.php     booking window rules: validation, add, edit, guarded delete
│   ├── reports.php        report queries: counts, usage, activity, CSV rows
│   ├── profile.php        self-service: details, password, picture, activity
│   └── users.php         user rules: validation, add, edit, role, status, password
├── admin/             Administrator pages
│   ├── index.php          dashboard counters
│   ├── approvals.php      pending request queue: approve, reject, cancel
│   ├── bookings.php       all bookings: filters, cancel, mark completed
│   ├── booking.php        one full record, with its audit trail
│   ├── laboratories.php   add, edit and change status
│   ├── laboratory.php     one laboratory, with guarded delete
│   ├── time-slots.php     booking windows, with usage counts and guarded delete
│   ├── reports.php        period reports, with CSV exports
│   ├── profile.php        own profile self-service
│   ├── users.php          all accounts: search, filters, totals, add
│   └── user.php           one account: details, role, status, password
├── user/              Student pages
│   ├── book.php           availability and the request form
│   ├── bookings.php       own bookings, by status
│   ├── booking.php        own booking, with self-cancel
│   ├── history.php        sessions that have happened
│   └── profile.php        own profile self-service
├── auth/              login, logout, register
├── database/
│   ├── .htaccess          blocks direct web access
│   ├── schema.sql         full DDL
│   ├── 02_restrict_deletion.sql  ALTERs that apply RESTRICT to an existing DB
│   ├── 03_profile_avatar.sql    adds users.avatar to an existing DB
│   ├── seed.php           demo data seeder (command line only)
│   └── tests/
│       ├── relationships.test.sql  non-destructive constraint proof
│       ├── rules.test.php         booking rules engine checks (command line)
│       ├── laboratories.test.php  laboratory management checks (command line)
│       ├── time_slots.test.php    booking window checks (command line)
│       ├── booking_status.test.php  booking lifecycle checks (command line)
│       ├── users.test.php         user management checks (command line)
│       ├── reports.test.php       reporting checks (command line)
│       └── profile.test.php       profile self-service checks (command line)
├── docs/
│   └── DATA_DICTIONARY.md column-by-column schema reference
├── .htaccess          blocks .sql/.dotfiles, disables directory listing
└── index.php          public landing page
```

### About the folder layout

The requested structure keeps `admin/`, `user/` and `auth/` as direct children
of the project root so they are reachable as short URLs
(`/admin/index.php`, `/user/index.php`). Because the document root is the
project folder, that would normally expose `config/` and `includes/` over HTTP.

This is solved with two layers of protection:

* `config/.htaccess`, `includes/.htaccess` and `database/.htaccess` each
  `Require all denied`.
* the root `.htaccess` additionally blocks `*.sql`, `*.ini`, `*.log` and
  dotfiles, and turns off directory listing, so a folder-level file being
  removed by accident is not immediately fatal.

Verified: requesting `/config/config.php` returns **403 Forbidden**.

> If you ever move the project to a different setup, keep the `.htaccess`
> files. Losing them exposes the database password.

---

## 5. Database schema

Six tables in the `clbs` database, all InnoDB / `utf8mb4_unicode_ci`. A
column-by-column reference lives in **[docs/DATA_DICTIONARY.md](docs/DATA_DICTIONARY.md)**.

| Table | Purpose |
|---|---|
| `users` | Students and administrators, bcrypt password hashes |
| `laboratories` | Name, location, capacity, computer count, availability status |
| `time_slots` | The bookable windows of a working day |
| `bookings` | One reserved period in one laboratory |
| `activity_logs` | Audit trail of sign-ins and administrative changes |
| `notifications` | Messages shown in the user's bell menu |

`bookings` is the only junction table: it records that a *user* occupies a
*laboratory* for a *period*.

### Relationships

```
users ──1:N──► bookings ──N:1──► laboratories
   │              ▲
   │              └── reviewed_by ──► users  (SET NULL)
   ├──1:N──► notifications        (CASCADE)
   └──1:N──► activity_logs        (SET NULL)
```

### Preventing conflicting bookings

Two bookings conflict when, on the same laboratory and date:

```
existing.start_time < new.end_time   AND   existing.end_time > new.start_time
```

and neither is `rejected` or `cancelled`. Back-to-back sessions (08:00-12:00
and 12:00-14:00) correctly do *not* conflict. MySQL and MariaDB cannot express
this as a constraint, so it is enforced in PHP. The supporting index
`idx_bookings_conflict (laboratory_id, booking_date, status)` is ordered to
match that lookup exactly.

### Deletion policy

`bookings.user_id` and `bookings.laboratory_id` are `ON DELETE **RESTRICT**`,
not `CASCADE`. Cascading would mean deleting one laboratory silently erased
every booking ever made against it, and deleting one student erased their whole
history — which the admin "booking history" and student "my bookings" reports
both depend on. Remove a record by flipping a status instead:

```sql
UPDATE laboratories SET status = 'inactive' WHERE id = ?;
UPDATE users        SET status = 'inactive' WHERE id = ?;
```

`bookings.reviewed_by` is `ON DELETE SET NULL` on purpose: an administrator who
is removed should not take the record of their decision with them.

### Strict SQL mode

XAMPP's MariaDB is not strict by default, and in non-strict mode an invalid
ENUM value is silently coerced (`status = 'banana'` stores `''`). That matters
because the conflict check ignores rows whose status is not `pending` or
`approved` — a corrupted row would quietly stop blocking a conflicting booking.
`config/database.php` therefore sets a strict `sql_mode` on every connection, so
this fails loudly instead.

### Testing the schema

```
C:\xampp\mysql\bin\mysql.exe -u root clbs < database\tests\relationships.test.sql
```

Eight tests. Six are *designed to fail* and each failure proves a guarantee:
orphaned foreign keys (1452), deletion restricted while bookings exist (1451),
duplicate email (1062), and the status ENUM (1265). Two are designed to
succeed, proving the documented gaps MariaDB cannot close (`end_time` before
`start_time`, and bookings outside every enabled time slot) — those are checked
in PHP. The script runs inside a transaction and rolls back, so it cannot
damage your data, and it ends by confirming nothing was left behind.

---

## 6. Security measures

* **Prepared statements everywhere.** `db_query()` binds every parameter and
  `PDO::ATTR_EMULATE_PREPARES` is `false`, so user input is never
  interpolated into SQL.
* **bcrypt password hashing** via `password_hash()` /
  `password_verify()`, transparently rehashed when the cost factor changes.
* **Output escaping.** `e()` wraps `htmlspecialchars`; every dynamic value in
  a template passes through it.
* **CSRF tokens** on every state-changing form, checked by `csrf_guard()`
  using `hash_equals()`.
* **Session hardening.** The session id is regenerated on login and on
  privilege change, cookies are `HttpOnly`, `Secure` is set when serving over
  HTTPS, and there is a 2 hour idle timeout.
* **Server-enforced authorisation.** `require_login()`, `require_admin()` and
  `require_user()` guard every page; the client-side UI is never the only
  check.
* **No user enumeration.** A login attempt for an unknown email still runs a
  password hash comparison, so the response takes the same time and wording.
* **Brute-force throttling.** After 5 failed sign-ins from one IP address
  within 15 minutes, further attempts from that address are refused for 15
  minutes and the form is disabled with an explanation. A successful sign-in
  clears the count. Settings live in the `AUTH_*` constants at the top of
  `includes/auth.php`. Counting by IP address means a group of students behind
  one shared router can lock each other out; that is an accepted trade-off for
  a single-site LAN install.
* **Live account checks.** `sync_user_state()` re-reads the account on every
  guarded request, so deactivating, deleting or re-roleing someone takes effect
  on their very next click instead of whenever their session happens to expire.
* **Self-service cannot escalate.** The profile forms post no `role` or
  `status`, and `profile_details_input_from_post()` fills both in from the
  stored record. `user_update()` never writes those two columns at all, so this
  is not a UI convention. A student's student number is pinned the same way,
  because it is a registered identity rather than a contact detail.
* **Password changes require the current password**, and the session id is
  regenerated afterwards, so a token captured beforehand cannot ride on the
  authenticated session. The session is kept, not ended.
* **Uploads are re-encoded, never copied.** A profile picture is decoded with
  GD and written back as a fresh 256×256 JPEG, so whatever was in the file
  cannot survive into the served image. The type is decided by `getimagesize()`,
  not `$_FILES['type']`, which is attacker-controlled. `is_uploaded_file()` is
  checked *before* the file is opened, so a hand-crafted path pointing at a
  server file is never read, let alone decoded. The stored path is always
  derived from the account id and re-validated against a strict pattern before
  being rendered, and `public/uploads/.htaccess` denies execution as a second
  layer.
* **Bounded work per request.** The reports period is capped at
  `REPORT_MAX_RANGE_DAYS` and the page says when it trimmed, because the daily
  chart and table build one row per day.
* **Audit trail.** Sign-ins, failed logins, administrative actions and profile
  changes are written to `activity_logs`.
* **`config/`, `includes/` and `database/` are not web accessible.**
* **Strict SQL mode** on every connection, so invalid enum values fail loudly
  instead of being silently coerced.

### Before exposing the server to a real network

These are deliberately left out of the demo setup so that you are not locked
out of your own machine, but they matter for a real deployment:

1. Give MySQL a password, or create a dedicated `clbs_app` user with grants on
   only the `clbs` database, and update `config/config.php`.
2. Set `APP_DEBUG` to `false` in `config/config.php` so PHP stops printing
   errors to the page.
3. Restrict or disable phpMyAdmin, which is otherwise reachable by anyone on
   the LAN.
4. Never use the seeded demo passwords on a real deployment.

---

## 7. Development status

| Phase | Module | State |
|---|---|---|
| 1 | Project foundation | **Complete** |
| 1 | Authentication (login, logout, register) | **Complete** |
| 1 | Administrator dashboard with live statistics | **Complete** |
| 1 | Student dashboard with live statistics | **Complete** |
| 1 | Layout system: navbar, sidebar, footer, alerts, CSRF, guards | **Complete** |
| 1 | Database, schema, seeder | **Complete** |
| 4 | Booking rules engine (availability, validation, conflicts) | **Complete** |
| 4 | Student laboratory directory, search and filter | **Complete** |
| 4 | Student laboratory details with 14-day availability | **Complete** |
| 4 | Student booking request form | **Complete** |
| 4 | Student's own bookings, with status filter | **Complete** |
| 4 | Student booking details, with self-cancel | **Complete** |
| 4 | Student booking history | **Complete** |
| 5 | Laboratory management (add, edit, status, guarded delete) | **Complete** |
| 6 | Booking approvals (approve, reject with reason, conflict re-check) | **Complete** |
| 6 | Booking management (all bookings, filters, cancel, mark complete) | **Complete** |
| 6 | Full booking record with decision history | **Complete** |
| 8 | User list: search by name, email, student number or phone | **Complete** |
| 8 | User list: role and status filters, with totals | **Complete** |
| 8 | Add a user, with role and initial status | **Complete** |
| 8 | User record: full information, booking history, last sign-in | **Complete** |
| 8 | Edit user details (name, email, student number, phone) | **Complete** |
| 8 | Change a user's role, with self-change and last-admin guards | **Complete** |
| 8 | Deactivate and reactivate an account | **Complete** |
| 8 | Reset another user's password | **Complete** |
| 9 | Time slot management (add, edit, guarded delete, usage counts) | **Complete** |
| 9 | Reports: period filter, status split, approval rate, daily chart | **Complete** |
| 9 | Reports: laboratory usage, slot demand, student activity, CSV exports | **Complete** |
| 10 | Profile self-service: edit own details (name, email, phone) | **Complete** |
| 10 | Password self-service: change own password, current password required | **Complete** |
| 10 | Profile picture: upload, replace and remove, cropped to a square | **Complete** |
| 10 | Profile page: booking summary figures above the forms | **Complete** |

### The booking lifecycle

```
pending ──approve──▶ approved ──complete──▶ completed
   │                     │
   │                     └────cancel────┐
   ├──reject──▶ rejected                │
   └────────cancel──▶ cancelled ◀───────┘
```

* Only `pending` and `approved` hold a laboratory. Both block a conflicting
  request, so a student can queue a second, overlapping request but only one of
  them can be approved.
* An administrator works from **Approvals** (the pending queue), **Bookings**
  (everything, filterable) or a single **booking record**, which also shows the
  audit trail.
* Approving re-runs the conflict check, confirms the laboratory is still
  `available` and refuses a past date, all inside one transaction, so two
  administrators cannot approve overlapping sessions at the same moment.
* A rejection must carry a reason, which is shown to the student. Approval notes
  are shown too. A cancellation reason is optional.
* A student can cancel their own `pending` or `approved` booking; a past
  session cannot be cancelled. Marking a session completed is only allowed on or
  after its date.
* Every decision is recorded in `activity_log` against the booking, and the
  student is notified once the change is committed.

All status changes go through `booking_decide()` or
`booking_cancel_by_owner()` in `includes/booking_rules.php`, so the guards
cannot be bypassed by a page that forgets to check one.

### User management

`admin/users.php` lists every account with the totals for active, deactivated,
administrator and student, and searches across name, email, student number and
phone. `admin/user.php` is one account: its details, its booking history, its
last sign-in, and three separate forms for the things an administrator is
allowed to change.

* **Accounts are never deleted.** `bookings.user_id` is `ON DELETE RESTRICT`
  precisely so a student's history survives, and `bookings.reviewed_by` is
  `SET NULL` so removing an administrator does not erase the record of their
  decisions. Deactivation is the reversible equivalent of deletion, and it is
  what the status form does.
* **Role and status are not part of the details form.** They have their own
  guarded forms, so an edit cannot slip a privilege change past a check that
  only the dedicated code path performs.
* **Nobody may change their own access.** An administrator who demotes or
  deactivates themselves is refused, so a single administrator cannot lock
  everyone out of user management.
* **The last active administrator is protected.** Demoting or deactivating the
  final `active` `admin` is refused, and the message says to promote somebody
  else first. This is checked inside a transaction with `SELECT ... FOR UPDATE`
  on the affected rows, so two administrators cannot both pass the check at the
  same moment and leave nobody in charge.
* **Values are normalised on the way in.** Email addresses are trimmed and
  lower-cased, student numbers trimmed and upper-cased, and a blank optional
  field is stored as `NULL` rather than an empty string. Both the email and the
  student number are unique, and a refusal names the field that actually
  collided instead of always blaming the email.
* **Passwords are never echoed back**, not into the form after a failure and not
  into the log. A reset requires the new password twice, and takes effect
  immediately: the old password stops working on the next sign-in attempt.
* A deactivated account cannot sign in, and an administrator's open session is
  ended on that person's next request by the existing `sync_user_state()`.

Every add, edit, role change, status change and password reset is written to
`activity_logs`.

---

## 8. Verification performed

Everything below was checked against a running Apache + MariaDB:

* 52 PHP files pass `php -l` with no syntax errors.
* All 43 page requests return HTTP 200 for an authorised role with no PHP
  warnings, notices or deprecations in the body or in `error.log`.
* `/config/config.php`, `/config/database.php`, `/includes/*.php` and
  `/database/schema.sql` all return **403**.
* Login succeeds for both roles and lands on the correct dashboard; a wrong
  password and an unknown email both stay on the login page.
* Anonymous visitors to `/admin/` and `/user/` are redirected to the login page.
* A student requesting `/admin/*` is bounced to their own dashboard, and an
  administrator requesting `/user/*` is bounced to theirs.
* A student's dashboard shows no other student's name or booking reference.
* Registration creates a `role = user` account with a bcrypt hash, and
  rejects duplicate emails and mismatched passwords.
* POST requests with a missing or bogus CSRF token return **403**.
* 5 wrong passwords in a row lock sign-in out from that IP; the correct
  password is refused until the cooling-off period ends, and the lock survives
  a brand-new browser session. Signing in successfully resets the count.
* Deactivating an account signs that user out on their next request, with an
  explanation, and their next sign-in attempt is refused.
* The seed data contains no overlapping active bookings and no booking outside
  an active time slot.
* `database/tests/relationships.test.sql` raises exactly the six expected
  errors (1452, 1452, 1451, 1451, 1062, 1265) and rolls back leaving no rows
  behind, confirming that booking history survives deletion of its laboratory
  and of its owner.
* The app is reachable over the LAN interface and still returns 403 for
  `config/` from that address.
* Administrator dashboard counters match the database exactly
  (5 laboratories, 10 active users, 6 pending, 5 approved).

### Booking rules

`database/tests/rules.test.php` runs 82 checks over `includes/booking_rules.php`
in the browser, and leaves every existing booking untouched: it works on a date
the data does not use, deletes only the rows it created, and asserts that the
total is unchanged rather than comparing against a fixed seed count.

* Time parsing, calendar validity (including 31 February and leap years), the
  30-day booking window, and same-day times that have already passed.
* Requests shorter than 30 minutes, longer than 8 hours, ending before they
  start, or straddling the gap between two sessions.
* Requests that do not fit inside a single active session, and requests against
  a laboratory that is not open for booking.
* Overlap detection: a second request for the same period is refused, a request
  that only clips the edge of a taken period is refused, and a request that ends
  exactly as the taken period starts is allowed.
* Reference generation is unique across a batch of bookings.
* A concurrent second insert is rejected by the transaction rather than
  creating a double booking.

### Booking pages

An end-to-end pass drives the real pages over HTTP as two different students,
covering 65 checks:

* The booking form keeps the chosen laboratory and date, bounds the date input to
  the 30-day window, and offers only the three active sessions.
* A valid request is written, starts `pending`, gets a `BK-YYYY-NNNN` reference,
  and is recorded in the activity log.
* One student gets a 404 for another student's booking, and cannot cancel it.
* Taken periods are shown with their times only; the other student's purpose is
  never rendered on the availability panel, the laboratory page or the listings.
* The server refuses a date outside the window or in the past, a session under
  30 minutes or over 8 hours, a period across the lunch gap, a period past the
  20:00 close, an end time before the start, a missing purpose, a laboratory
  that is not open for booking, and a laboratory that does not exist.
* Missing and forged CSRF tokens both return **403** and create nothing.
* The owner can cancel a `pending` or `approved` booking; the reviewer fields
  are cleared, the cancel button disappears, the booking moves to the cancelled
  filter and into the history, and the freed period can immediately be taken by
  another student.
* A `cancelled` booking no longer blocks the calendar, while a `pending` one
  still does.
* Unknown status filter values are ignored rather than treated as an error, and
  an unknown booking or laboratory id returns 404.
* The test removes exactly the rows it created: the booking count returns to the
  seeded 20 with no test rows left behind.
* A brand-new account with no bookings at all renders every student page, and
  every status filter, without a PHP notice. This is the case most likely to
  break the filter counts and the empty states, so it is checked separately.
* No response from any page, in any of the flows above, contains a PHP warning,
  notice or error.

### Laboratory management

`database/tests/laboratories.test.php` runs 78 checks over
`includes/laboratories.php`, touching only laboratories named `ZZ-LAB-%` and
counting the rows before and after, so it is repeatable and cannot damage the
seeded facility:

* Every field is validated: name length, required location, capacity and
  computer-count ranges, and the rule that a room cannot hold more computers
  than it has seats for.
* A duplicate name is refused, whether it collides with another laboratory or
  with a real seeded one, while saving a laboratory under its own name is not.
* Status changes move between available, maintenance and inactive, refuse an
  unknown status, and refuse to set the status a laboratory already has.
* A laboratory with no bookings is deleted; one that has ever been booked is
  refused with a count and an explanation, and survives the attempt untouched.
* A blank or whitespace-only description is stored as `NULL` rather than an
  empty string.
* The status and booking totals are internally consistent: the three status
  counts add up to the total, and `active` equals `pending` plus `approved`.

An end-to-end pass then drives the real administrator pages over HTTP, covering
76 checks:

* An administrator reaches both pages; a student is redirected to their own
  dashboard from both, and an anonymous visitor to the login page.
* Adding a laboratory stores every field and redirects to its new page, which
  reports that it has never been booked and therefore offers deletion.
* Adding is refused for a duplicate name, a missing or one-character name, a
  missing location, a capacity of zero, a negative capacity, more computers than
  seats, and an invented status. None of them create a row, and a missing or
  forged CSRF token gives **403**.
* Editing stores the new values, is refused when it would collide with another
  laboratory's name, and leaves the record untouched when refused.
* Taking a laboratory to maintenance removes it from the student's booking form,
  removes its Book button from the student directory, explains on its own
  student page that it cannot be booked, and rejects a hand-crafted booking
  request against it with "is not open for booking". Bringing it back into
  service reverses all of that.
* A laboratory that has bookings shows "Delete not possible", a hand-crafted
  delete of it is refused, it survives, and its bookings are untouched.
* Adding, editing, status changes and deletions are all recorded in the activity
  log.

### Booking lifecycle

`database/tests/booking_status.test.php` runs 112 checks over the status
machine in `includes/booking_rules.php`. It only touches rows tagged
`ZZ-STATUS-PROBE%` and laboratories named `ZZ-LAB-%`, deletes exactly what it
created, and asserts the totals are unchanged afterwards, so it is repeatable:

* Only the transitions on the diagram are allowed. `rejected`, `cancelled` and
  `completed` are final, and the first attempt to leave one is refused with the
  booking left as it was.
* The documented example is checked directly: an approved 10:00-12:00 booking
  blocks a request for 11:00-13:00, while 08:00-10:00 and 12:00-14:00 are both
  accepted, because the overlap test is strict on both ends.
* Approving re-runs the conflict check inside a transaction, so a request whose
  slot was taken while it waited is refused and stays `pending`.
* Approving is refused when the laboratory has since been taken out of service,
  and when the booking date has passed. Completing is refused before the date.
* A rejection without a reason is refused, the note is trimmed, and a note over
  255 characters is refused.
* A student cannot cancel through the owner path a booking that is not theirs,
  cannot cancel a terminal or past booking, and cannot cancel twice. An
  administrator cancelling on a student's behalf is recorded as the reviewer.
* Every decision writes an `activity_log` row carrying the reviewer, and creates
  one notification for the student.

An end-to-end pass then drives the real administrator and student pages over
HTTP, covering 107 checks:

* A student's request reaches the queue, can be found by reference and filtered
  by laboratory, and an overlapping request is refused with the taken period
  explained and removed from the free windows.
* The queue names any other request that already holds the period, whether it is
  still pending or already approved, and pressing Approve anyway redirects back
  with an explanation and changes nothing.
* Approving redirects, stores the note, shows it to the student, notifies them,
  and moves the booking out of the queue and into the approved list.
* Rejecting without a reason is refused; with one it succeeds, the student sees
  the reason, and the rejected booking can no longer be approved.
* An administrator can cancel an approved booking on a student's behalf, and the
  freed period is immediately offered to someone else.
* A student cancelling their own booking clears the reviewer fields, cannot be
  repeated, and gets 404 for another student's booking both when reading it and
  when hand-crafting a cancel POST.
* A past session can be marked completed; a future one cannot. A completed
  booking is shown as final.
* A student is kept out of the queue, the all-bookings list and the full record;
  an anonymous visitor is sent to the login page; a missing or forged token
  gives **403**; an invented decision redirects with a warning and changes
  nothing; a booking that does not exist gives **404**.
* Search, the laboratory, date-range and status filters work, an invented status
  is ignored rather than erroring, and a search with no matches renders the
  empty state.

A third pass checks the *markup* of those decision controls, because the pass
above posts directly and never runs JavaScript, so it cannot see a control that
looks right but does nothing when clicked:

* Every button that asks for a reason submits both a decision and a place to put
  the reason. The prompt does not call `form.submit()`, which would drop the
  pressed button's own `name="decision"` and leave the server with nothing to
  act on, and it can still stop the submission if no reason is given.
* A terminal booking offers no decision control at all, rather than one that
  would post an empty decision.

Both suites leave the database exactly as they found it, including the activity
log: the laboratory suite removes its own audit rows, which otherwise outlive
the laboratory it deletes.

### User management

`database/tests/users.test.php` runs 117 checks over `includes/users.php`. It
only touches accounts whose email begins `zz-user-`, and it counts the rows
before and after rather than comparing against a fixed number, so it is
repeatable:

* Role and status vocabularies, their labels, and the badge classes, which are
  kept separate from the booking status helper because an account's `inactive`
  is not a booking's.
* Normalisation of email and student number, including surrounding whitespace
  and mixed case.
* Every validation rule: name length, email shape and length, phone shape, an
  unknown role, an unknown status, and the optional fields.
* Adding an account stores a bcrypt hash rather than the password, and refuses
  a duplicate email or student number while naming the field that actually
  clashed. Two accounts may both have no student number, because a blank one is
  stored as `NULL` and so does not collide with the `UNIQUE` index.
* Editing stores the new values, refuses a collision with another account
  without writing anything, and **cannot** change the role, the status or the
  password — the check that the dedicated forms are not bypassable.
* Password policy at 8 characters, a reset that changes the stored hash, the old
  password ceasing to work, and a refused reset leaving the hash alone.
* Role and status changes, including refusals for an unknown value, a value the
  account already has, and an account that does not exist.
* An administrator cannot demote or deactivate themselves.
* The last active administrator cannot be demoted or deactivated, and a student
  still can be, so the guard is not simply refusing everything.

The administrator-stepping-down and last-administrator checks deliberately
disturb the real accounts, because the guards cannot be proven any other way.
A shutdown handler restores them, so even a fatal error part way through leaves
the system as it was found; the script then asserts they came back.

An end-to-end pass then drives the real pages over HTTP, covering 64 checks:

* An administrator reaches both pages; a student is redirected away from both,
  and an anonymous visitor to the login page. An unknown account id gives
  **404**, and a request with no CSRF token changes nothing.
* The list, its search, and each role and status filter render cleanly, and an
  invented filter value is ignored rather than erroring.
* Adding a valid account stores it and redirects to its new page; a missing
  email, a bad address and a short password are refused with the form still
  usable, and a duplicate is refused by name.
* **Editing works even though the details form posts no role or status.** It did
  not at first: the form has no such fields, and validation required them, so
  every save was rejected. The record's own values are now filled in for the
  purpose of checking, which is not a route around the access guards because
  the update never writes those two columns.
* Resetting a password refuses a mismatch, and on success the old password stops
  signing in and the new one works.
* Promoting a student takes effect on their **already open** session: their next
  request lands on the administrator dashboard and they can open the user list,
  with no sign-out first. Deactivating keeps them out of sign-in, and
  reactivating restores it.
* Neither self-change nor removal of the last administrator is possible over
  HTTP, and the refusal is explained on the page.
* Adds, edits and access changes are all recorded in the activity log.

The HTTP pass clears its own failed-sign-in rows before and after it runs. Those
checks sign in wrongly on purpose, and the lockout counts per device rather
than per account, so five of them would otherwise block the next run —
including the administrator's — for the cooling-off period, for a reason that
has nothing to do with the code under test.

Both suites leave the database exactly as they found it: 12 accounts, 2 active
administrators, and no `zz-user-%` rows or stray audit entries.

### Time slot management

`database/tests/time_slots.test.php` runs 65 checks over
`includes/time_slots.php`, working only on rows it created itself:

* Validation of start, end and label, including an end that is not after the
  start, and a window that runs past midnight.
* The seeded overlapping windows are **not** rejected. Overlap is a legitimate
  configuration choice, so the rule is left to the person managing the slots.
* Duplicate labels are refused, while saving a slot under its own label is not.
* **The containment count, and the delete guard built on it.** A booking is
  counted when the slot is *contained* in it, not only when the two are
  identical, so a window that merely overlaps an existing one still reads as
  in use. Deleting such a window is refused with the count and an explanation.
* `time_slot_delete()` refuses a window with any booking inside it, and a slot
  nobody booked is removed cleanly.

An end-to-end pass then drives the real administrator page over HTTP, covering 37
checks:

* The list, its in-use flags, and the add and edit forms.
* Deleting an unused window works; deleting one that holds a booking is refused
  and the row survives, with the button replaced by a disabled lock.
* Only a slot with no bookings inside it offers a delete control at all.

### Reports

`database/tests/reports.test.php` runs 49 checks over `includes/reports.php`:

* The date range, including a reversed range being swapped rather than returning
  nothing, and a malformed date falling back to the default.
* The status split sums to the total, the approval rate agrees with the counts,
  and the average lead time is non-negative.
* Laboratory usage, slot demand and student activity all sum back to the total
  number of bookings in the period.
* **"Students who booked" is `COUNT(DISTINCT user_id)`, not a row count.** It
  was wrong at first: the figure came from the 25-row student activity list, so
  it silently capped at the display limit while the table said there were more.
* **The period is bounded.** `REPORT_MAX_RANGE_DAYS` (three years) caps the
  range, trimming the far end rather than the start, and the page says so. The
  daily chart and table build one row per day, so an unbounded range is a
  request to allocate millions of rows.
* Day counting uses calendar arithmetic, not timestamps. XAMPP's PHP is a
  **32-bit** build, so `strtotime()` returns `false` for anything before 1970
  or after 2038, which would silently collapse a legitimate far-past date to a
  one-day period. `format_date()` has the same guard.

An end-to-end pass then drives the real page over HTTP, covering 56 checks:

* The cards, the chart, and every table agree with each other for a given
  period, and all of them change together when the dates change.
* Each CSV export downloads with the right `Content-Type` and filename, and its
  rows match the table it came from.
* An empty period shows an empty state rather than a zero-filled table.
* A year-spanning request returns promptly and says the period was trimmed.

### Profile self-service

`database/tests/profile.test.php` runs 64 checks over `includes/profile.php`, on a
throwaway account whose picture is written to its own slot and removed by a
shutdown handler:

* The avatar path rules: a tampered `avatar` column does not become a URL, a
  missing or broken upload is refused, and the size limit is named in the
  refusal. `is_uploaded_file()` runs **before** the file is opened, so a
  hand-crafted path pointing at a server file is never read, let alone decoded.
* The image type rules, split into `profile_avatar_inspect()` so they can be
  tested on their own: a PHP script is not an image, a real GIF and PNG are
  accepted with their dimensions read, and an absurdly large image is refused.
* `getimagesize()` decides the type, not `$_FILES['type']`, which is whatever the
  browser claimed.
* Password changes need the current password, and a refusal for a short
  password, a mismatched repeat or reuse of the current one writes **nothing** to
  the activity log. A valid change stores a hash, never the password, and is
  logged once.
* **The self-service form cannot escalate.** `profile_details_input_from_post()`
  fills `role` and `status` in from the stored record, `user_update()` never
  writes those two columns, and a write handed a forged array directly still
  leaves them alone.
* A student's forged student number is ignored, because it is a registered
  identity rather than a contact detail. An administrator's own staff number
  stays editable. The field is rendered `disabled` with no `name`, so it cannot
  be posted at all, and the value is pinned server-side rather than merely
  hidden.
* An account still cannot be deactivated through the profile page.
* The shared validation rules still refuse an empty or short name, a malformed
  email or phone, and an email already in use. A collision is checked against a
  **real** seeded address, since a made-up one would never collide with anything;
  keeping your own address is not a collision.
* The seeded system is untouched: still 2 active administrators, and no picture
  files left behind.

An end-to-end pass then drives both real profile pages over HTTP as both roles,
covering 113 checks. This is the only pass that can satisfy `is_uploaded_file()`,
so it is where the upload accept path is actually proven:

* A genuine multipart GIF upload is accepted, written, re-encoded as a square
  JPEG of the expected size, recorded in the `users` row as the path this code
  writes, and served back from the page.
* A replacement upload of a **tall** image comes out square, which is the crop
  and not a resize.
* A PHP script sent as a real upload with a `.jpg` name and a JPEG content type
  is refused, the page says it is not an image, and the **existing** picture and
  its `users` row are left alone.
* Removal deletes the file, clears the column, and takes the button away.
* Details save, with a forged `role`, `status` and student number all ignored; a
  duplicate email and a too-short name are both refused without writing.
* A POST with a forged CSRF token changes nothing.
* A wrong current password does not redirect, the old password keeps working, a
  mismatched repeat is refused, and a valid change makes the new password sign in
  and the old one fail. **The session that made the change stays signed in**,
  which is the point of regenerating the id rather than ending the session.
* A student is redirected away from the administrator page and vice versa, and
  neither page offers a role or status control.
* A seeded password cannot be changed without the current one, and a refused
  seeded change leaves the real hash untouched. A shutdown handler restores the
  seeded passwords even after a fatal error.
* The three panels that were cut from the page are asserted absent, the three
  that stayed are asserted present, and the password change is still checked in
  the `activity_logs` table rather than on the page.

Visual checks at 1400px and 480px found no horizontal overflow, no image without
alt text and no label pointing at a missing field on either page, and the
password button's `data-confirm` does fire the browser dialog.

---

## 9. Troubleshooting

**"Database connection failed"**
MySQL is not running, or `clbs` has not been imported. Check the XAMPP Control
Panel, then import `database/schema.sql`.

**Pages are unstyled**
`assets/vendor/` is incomplete. It must contain
`bootstrap/css/bootstrap.min.css`, `bootstrap/js/bootstrap.bundle.min.js`,
`bootstrap-icons/bootstrap-icons.min.css` and the `bootstrap-icons/fonts/`
directory. The icon CSS resolves its font relative to its own location, so the
file must sit *beside* the `fonts/` folder, not inside a `css/` folder.

**"Undefined constant" or blank page**
The project is running on a PHP version or configuration where
`display_errors` is off. Set `APP_DEBUG` to `true` in `config/config.php`
temporarily, or check `C:\xampp\apache\logs\error.log`.

**Cannot reach the server from another device**
See section 3. The usual cause is a missing Windows Firewall rule.

**Styles break when the project is moved or renamed**
`BASE_URL` is auto-detected, so nothing should need editing. If you have
edited `config/config.php`, restore the auto-detection block at the bottom.
