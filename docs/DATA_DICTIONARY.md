# Data dictionary

Reference for the `clbs` database. Written for the project report and for
anyone picking up the schema later.

Engine **InnoDB**, charset **utf8mb4**, collation **utf8mb4_unicode_ci**
throughout, so accented names and non-Latin characters store correctly.

---

## 1. Entity relationships

```
                 ┌──────────────┐
                 │    users     │
                 └──────┬───────┘
                        │
        owns            │            reviews
   (ON DELETE           │           (ON DELETE
    RESTRICT)           │            SET NULL)
                        │               │
                 ┌──────▼───────────────▼───┐
                 │        bookings           │
                 └──────────┬────────────────┘
                            │
          booked_in         │   (ON DELETE RESTRICT)
                            │
                 ┌──────────▼─────────┐
                 │    laboratories    │
                 └────────────────────┘

  notifications ──► users        activity_logs ──► users
  (CASCADE, transient)           (SET NULL, audit trail survives)
  time_slots: standalone reference data, no foreign keys
```

| Relationship | Cardinality | Enforced by |
|---|---|---|
| user → bookings | one to many | `bookings.user_id` FK, `ON DELETE RESTRICT` |
| laboratory → bookings | one to many | `bookings.laboratory_id` FK, `ON DELETE RESTRICT` |
| administrator → bookings reviewed | one to many | `bookings.reviewed_by` FK, `ON DELETE SET NULL` |
| user → notifications | one to many | `notifications.user_id` FK, `ON DELETE CASCADE` |
| user → activity_logs | one to many | `activity_logs.user_id` FK, `ON DELETE SET NULL` |

`bookings` is the only junction between the two parent tables: it is the fact
that a *user* occupies a *laboratory* for a *period*, which is the whole point
of the system.

### Why `RESTRICT` and not `CASCADE` on the two parents

Cascading would mean deleting one laboratory silently erased every booking
made against it, and deleting one student erased their entire history. Both
admin ("view booking history") and student ("my bookings") reports depend on
those rows, so they must survive. Deletion is therefore *restricted* while a
booking exists, and removal is done by flipping a status instead:

```sql
UPDATE laboratories SET status = 'inactive'   WHERE id = ?;
UPDATE users        SET status = 'inactive'   WHERE id = ?;
```

`reviewed_by` is the exception: an administrator who is deleted should not
take the record of their decision with them, so the column becomes `NULL` and
the decision itself is preserved.

---

## 2. Tables

### `users`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `name` | VARCHAR(120) | no | | full name |
| `email` | VARCHAR(191) | no | | UNIQUE `uq_users_email`; login identifier |
| `password` | VARCHAR(255) | no | | bcrypt hash from `password_hash()`; never plaintext |
| `role` | ENUM('admin','user') | no | 'user' | 'user' = student |
| `status` | ENUM('active','inactive') | no | 'active' | soft-delete / suspension |
| `student_no` | VARCHAR(50) | yes | NULL | UNIQUE `uq_users_student_no`; staff number for admins |
| `phone` | VARCHAR(30) | yes | NULL | |
| `avatar` | VARCHAR(191) | yes | NULL | project-relative path, e.g. `public/uploads/avatars/12.jpg`; NULL means no picture and the page shows initials |
| `last_login_at` | DATETIME | yes | NULL | set on every successful sign-in |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | no | CURRENT_TIMESTAMP | auto-updates |

Indexes: `uq_users_email` (UNIQUE), `uq_users_student_no` (UNIQUE),
`idx_users_role_status` (role, status) for the admin user list.

> `email` and `student_no` are `VARCHAR(191)` rather than 255 because
> utf8mb4 uses up to 4 bytes per character, and an index key on this MySQL
> version is limited to 767 bytes. 191 × 4 = 764, which fits.
>
> A UNIQUE index permits any number of NULLs, so students who leave
> `student_no` blank do not collide with one another. This is also why a blank
> optional value is stored as `NULL` rather than `''`: MariaDB treats a unique
> index as satisfied by more than one `''`, but not by more than one `NULL`.

#### `avatar`

`avatar` was added after the initial schema by `database/03_profile_avatar.sql`,
which is safe to re-run. `schema.sql` already includes it, so only a database
imported from an older `schema.sql` needs the migration.

The column holds a path, never user input. `includes/profile.php` always writes
`public/uploads/avatars/<user id>.jpg` and always reads it back through
`profile_avatar_url()`, which matches the stored value against
`#^public/uploads/avatars/[0-9]+\.jpg$#` and confirms the file still exists
before producing a URL. The value is rendered into an `src` attribute, so a row
edited by hand must not be able to turn into a broken image or an unexpected
path.

The picture is decoded with GD and written back as a fresh 256×256 JPEG, so the
file on disk is always something this code produced rather than something that
was uploaded.

#### Rules the column types cannot enforce

`NOT NULL` does not stop `''`, and an ENUM rejection is a database error rather
than a sentence a person can act on. `includes/users.php` therefore checks
these in `user_validate()` before writing anything:

| Rule | Why |
|---|---|
| `name` must be 3 to 120 characters | `NOT NULL` still allows `''` |
| `email` must be a usable address, at most 191 characters | the ENUM and the index cannot judge a malformed address |
| `phone`, if given, must look like a phone number | `VARCHAR(30)` would take anything |
| `role` must be `admin` or `user` | as above |
| `status` must be `active` or `inactive` | as above |
| `email` unique | the `UNIQUE` index guarantees it, but it is checked first so the message is readable |
| `student_no` unique, if given | as above |
| password at least 8 characters | `VARCHAR(255)` happily stores `'a'` |

#### What self-service may change

`admin/user.php` and the two profile pages are different jobs. An administrator
editing somebody else's account can change details, role, status and password.
A user on their own profile page can change name, email, phone and password, and
nothing else.

That is enforced in three places rather than one, because the form alone is only
a suggestion:

* the profile forms post no `role` or `status` field at all;
* `profile_details_input_from_post()` fills both in from the stored record, so
  `user_validate()` has something to check;
* `user_update()` never writes those two columns, so even a hand-crafted POST
  or a direct call to the function cannot change them.

A student's `student_no` is pinned the same way. It is a registered identity
rather than a contact detail, so a wrong one is corrected by an administrator on
the Users page; an administrator's own staff number stays editable, because it is
theirs to keep.

#### Normalisation

Values are cleaned before they are stored, so the same person cannot end up with
two accounts differing only in case or spacing:

* `email` is trimmed and lower-cased, which also makes the uniqueness check
  case-insensitive regardless of the column collation.
* `student_no` is trimmed and upper-cased, because student numbers are issued
  in one case and are typed in several.
* a blank `student_no` or `phone` becomes `NULL`, not `''`.

#### Why accounts are deactivated rather than deleted

There is no delete anywhere in the user interface, and that is the point rather
than an omission. `bookings.user_id` is `ON DELETE RESTRICT` precisely so a
student's history cannot be erased, and `activity_logs.user_id` is
`SET NULL` so the audit trail outlives the account it describes. Deleting an
account would also orphan every notification it received.

`status = 'inactive'` is the reversible equivalent: the account can no longer
sign in, and `sync_user_state()` ends any session it still holds, but the
bookings, notifications and log entries stay attached and readable.

#### Who may change role or status

`role` and `status` are deliberately **not** editable from the details form.
Each has its own form and its own guard in `user_change_access()`, and
`user_update()` never writes those two columns at all, so an edit cannot be used
to slip a privilege change past a check that only the dedicated path performs.

The guards, all inside one transaction with the affected rows locked using
`SELECT ... FOR UPDATE`:

| Guard | Reason |
|---|---|
| An administrator cannot change their own role or status | a single administrator could otherwise lock every user out of user management |
| The last active administrator cannot be demoted or deactivated | the system must never be left with nobody who can administer it |
| A value the account already has is refused | nothing changed, so nothing should be logged as though it had |

The lock is what makes the second rule safe. Two administrators acting at the
same moment would otherwise both read a count of two, both pass the check, and
leave the system with none. The refusal tells the administrator to promote
somebody else first.

An access change takes effect on the affected account's **next request**,
without a sign-out, because `sync_user_state()` re-reads the account on every
guarded page.

### `laboratories`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `name` | VARCHAR(120) | no | | UNIQUE `uq_laboratories_name` |
| `location` | VARCHAR(120) | no | | e.g. "Block A - Room 101" |
| `capacity` | INT(11) | no | 0 | maximum students |
| `computer_count` | INT(11) | no | 0 | machines available |
| `description` | TEXT | yes | NULL | |
| `status` | ENUM('available','maintenance','inactive') | no | 'available' | only 'available' is bookable |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | no | CURRENT_TIMESTAMP | auto-updates |

#### Rules the column types cannot enforce

MariaDB 10.1 ignores `CHECK`, so the following are enforced in
`includes/laboratories.php` by `laboratory_validate()` instead:

| Rule | Why |
|---|---|
| `name` must be 3 to 120 characters | `VARCHAR(120)` alone would still allow an empty or one-character name |
| `name` must be unique | enforced by the `UNIQUE` index, but checked first so the message is readable |
| `location` is required | `NOT NULL` does not stop `''` |
| `capacity` between 1 and 2000 | the column defaults to `0`, and a room seating nobody is not bookable |
| `computer_count` between 0 and 2000 | as above |
| `computer_count` must not exceed `capacity` | a room cannot hold more machines than it has seats for |
| `status` must be one of the three values | the ENUM rejects anything else, but the rejection is a database error rather than a sentence |
| `description` at most 2000 characters | `TEXT` would hold 64 KB, which is not a description |

#### Deleting a laboratory

`bookings.laboratory_id` is `ON DELETE RESTRICT`, so a laboratory that has ever
been booked **cannot** be deleted at all. `laboratory_delete()` checks the count
first and returns an explanation rather than letting the database raise the
error, and the interface offers "mark inactive" or "mark in maintenance"
instead. Both of those stop the laboratory being offered to students without
touching any booking, so history is never lost.

A status change is deliberately not a delete: a room under maintenance keeps its
reservations, and the students holding them can still see them.

### `time_slots`

Bookable windows for a working day. **Added beyond the original three core
tables** because the specification lists "manage available time slots" as an
administrator capability, and because a booking must be bounded by opening
hours rather than being an arbitrary time.

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `label` | VARCHAR(60) | no | | e.g. "Morning Session" |
| `start_time` | TIME | no | | |
| `end_time` | TIME | no | | |
| `is_active` | TINYINT(1) | no | 1 | 0 = not offered to students |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | no | CURRENT_TIMESTAMP | auto-updates |

Seeded with Morning 08:00-12:00, Afternoon 13:00-17:00, Evening 17:00-20:00,
and a disabled Weekend 09:00-13:00.

### `bookings`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `booking_ref` | VARCHAR(20) | no | | UNIQUE `uq_bookings_ref`; human-readable, e.g. `BK-2026-0011` |
| `user_id` | INT(11) | no | | FK → `users.id`, RESTRICT |
| `laboratory_id` | INT(11) | no | | FK → `laboratories.id`, RESTRICT |
| `booking_date` | DATE | no | | |
| `start_time` | TIME | no | | |
| `end_time` | TIME | no | | |
| `purpose` | VARCHAR(255) | no | | shown to the approver |
| `status` | ENUM('pending','approved','rejected','cancelled','completed') | no | 'pending' | |
| `reviewed_by` | INT(11) | yes | NULL | FK → `users.id`, SET NULL |
| `reviewed_at` | DATETIME | yes | NULL | when the decision was made |
| `admin_note` | VARCHAR(255) | yes | NULL | reason shown to the student |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | no | CURRENT_TIMESTAMP | auto-updates |

#### Status lifecycle

```
                    ┌────────────► rejected   (terminal)
                    │
   [new] ──► pending ┼────────────► cancelled (terminal, by student or admin)
                    │
                    └────────────► approved ──► completed
```

`rejected`, `cancelled` and `completed` are terminal. Only `pending` and
`approved` rows hold a laboratory, so only they participate in the conflict
check.

The transitions are enforced in `includes/booking_rules.php`, not in the pages:

| From | Allowed to | Extra conditions |
|---|---|---|
| `pending` | `approved` | no overlapping `pending`/`approved` row, laboratory still `available`, date not in the past |
| `pending` | `rejected` | a note is required, so the student knows what to change |
| `pending` | `cancelled` | by the owner, or by an administrator |
| `approved` | `completed` | on or after the booking date |
| `approved` | `cancelled` | the date has not passed |

`booking_decide()` performs the change, the guards and the audit entry in one
transaction, taking a `FOR UPDATE` lock on the row first, so two administrators
pressing Approve at the same moment cannot both succeed. It re-runs the conflict
check on approval rather than trusting what the queue displayed, because a slot
can be taken while a request is waiting.

A cancellation reason is optional. When one is given it replaces whatever
`admin_note` held, so a note left over from an earlier decision cannot be
mistaken for the cancellation's own reason; when none is given the column is
cleared. When an administrator cancels, `reviewed_by` and `reviewed_at` record
who did it. When a student cancels their own booking, all three are left NULL or
cleared: nobody reviewed it, and there is no administrator note. `reviewed_at`
is also set by completion, which takes no note.

### `activity_logs`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `user_id` | INT(11) | yes | NULL | FK → `users.id`, SET NULL so the trail outlives the account |
| `action` | VARCHAR(64) | no | | see the list below |
| `entity` | VARCHAR(32) | yes | NULL | e.g. 'laboratory', 'booking' |
| `entity_id` | INT(11) | yes | NULL | |
| `description` | VARCHAR(255) | yes | NULL | human-readable summary |
| `ip_address` | VARCHAR(45) | yes | NULL | 45 chars allows IPv6 |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | second resolution, see note below |

`action` is a free-text column rather than an ENUM, so a new event type can be
logged without a schema change. The values currently written by the
application are:

| `action` | Written when |
|---|---|
| `login` | an account signs in successfully |
| `logout` | a user signs out |
| `login_failed` | a password check fails, or the email is unknown |
| `login_blocked` | correct password but the account is not `active` |
| `session_revoked` | a live session was cut short by a status or role change |
| `register` | somebody signs themselves up |
| `create` / `update` / `delete` | administrative changes to a record |
| `approved` / `rejected` / `cancelled` / `completed` | a booking's status was changed; the name of the action is the status it moved to |
| `cancel` | a student cancelled their own booking, where there is no reviewer to record |

For user management, `entity` is `'user'` and `entity_id` is the account that
was changed, while `user_id` is the administrator who made the change. A role
change, a status change and a password reset are all `update`; the
`description` distinguishes them:

| Change | `description` |
|---|---|
| Added | `Added Jane Chisopa as student` |
| Details edited | `Updated Jane Chisopa` |
| Role changed | `Changed Jane Chisopa from student to administrator` |
| Deactivated | `Deactivated Jane Chisopa` |
| Reactivated | `Reactivated Jane Chisopa` |
| Password reset | `Reset the password for Jane Chisopa` |

Because the subject is in `entity_id` rather than `user_id`, the trail still
reads correctly after the account it describes is deactivated. No password ever
appears in a description.

Two of these rows are load-bearing for security, which is worth knowing before
anyone tidies the table away:

* `login_failed` rows are what the brute-force throttle counts
  (`login_failure_count()` in `includes/auth.php`). The limit is 5 failures per
  IP address per 15 minutes. They are deleted for that IP address once the
  same address signs in successfully, so a few fumbled keystrokes do not
  accumulate towards a lockout.
* Because `created_at` has only one-second resolution, the counter must never
  be derived by comparing against "the last successful login" — a failure
  recorded in the same second would be excluded by the strict comparison and
  let extra guesses through. This was a real bug, fixed by clearing the rows on
  success instead.

### `notifications`

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| `id` | INT(11) | no | auto | PK |
| `user_id` | INT(11) | no | | FK → `users.id`, CASCADE |
| `title` | VARCHAR(120) | no | | e.g. 'Booking approved' |
| `message` | VARCHAR(255) | no | | |
| `link` | VARCHAR(191) | yes | NULL | target page |
| `is_read` | TINYINT(1) | no | 0 | drives the navbar badge |
| `created_at` | DATETIME | no | CURRENT_TIMESTAMP | |

---

## 3. Preventing conflicting bookings

Two bookings clash when they are on the **same laboratory**, on the **same
date**, their periods overlap, and neither has been rejected or cancelled:

```
existing.start_time < new.end_time   AND   existing.end_time > new.start_time
```

The boundary conditions are deliberate: 08:00-12:00 and 12:00-14:00 do **not**
conflict, because `12:00 < 12:00` is false. Back-to-back sessions are allowed.

The matching index is `idx_bookings_conflict (laboratory_id, booking_date,
status)`, ordered so the conflict lookup is a single range scan rather than a
full table scan.

The query is:

```sql
SELECT id FROM bookings
 WHERE laboratory_id = ?
   AND booking_date   = ?
   AND status IN ('pending', 'approved')
   AND start_time  < ?      -- the new end_time
   AND end_time    > ?      -- the new start_time
```

### Known database-level gaps

MariaDB 10.1 parses `CHECK` constraints but **ignores** them, so the following
cannot be enforced by the schema and must be validated in PHP before insert:

| Gap | Enforced in PHP |
|---|---|
| `end_time` earlier than `start_time` | booking form validation |
| booking outside every enabled time slot | time-slot check |
| booking in the past or beyond the 30 day window | date window check |
| a `pending` request being approved over a slot taken in the meantime | re-run the conflict check on approval |

`database/tests/relationships.test.sql` demonstrates the first two gaps
deliberately, so the limitation is proven rather than assumed.

---

## 4. Strict SQL mode

XAMPP's MariaDB starts with `sql_mode = NO_AUTO_CREATE_USER,
NO_ENGINE_SUBSTITUTION`, which is **not** strict. In that mode an out-of-range
ENUM value is silently coerced rather than rejected:

```sql
UPDATE bookings SET status = 'banana';   -- stores '' with no error
```

This is not cosmetic. The conflict check ignores any row whose status is not
`pending` or `approved`, so a booking that had been corrupted to `''` would
quietly stop blocking a conflicting booking.

`config/database.php` therefore sets a strict `sql_mode` on every connection,
which turns that silent corruption into a hard error (SQLSTATE 1265). Keep that
line if you ever rewrite the connection.

---

## 5. Verifying the schema

```powershell
# Full rebuild from scratch
C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
C:\xampp\php\php.exe database\seed.php

# Relationship and constraint tests (non-destructive, rolls itself back)
C:\xampp\mysql\bin\mysql.exe -u root clbs < database\tests\relationships.test.sql
```

`relationships.test.sql` contains 8 tests. Six are designed to fail and prove a
guarantee: orphaned foreign keys (1452), deletion restricted while bookings
exist (1451), duplicate email (1062), and the status ENUM (1265). Two are
designed to succeed, proving the gaps listed in section 3. The script wraps
itself in a transaction and rolls back, so it never damages your data, and it
finishes by confirming that nothing was left behind.
