-- ===========================================================================
-- Relationship integrity tests
--
--   mysql -u root clbs < database\tests\relationships.test.sql
--
-- Every statement below is EXPECTED TO FAIL, and each failure proves one
-- guarantee the application relies on. Read the "EXPECT" line under each test.
--
-- The whole script runs inside a transaction that is ROLLED BACK, so running
-- it never leaves the database altered - important because a careless DELETE
-- here would otherwise wipe real booking history.
--
-- Note: a failing statement in MySQL/MariaDB rolls back only that statement,
-- not the transaction, so ROLLBACK at the end really does undo everything.
-- ===========================================================================

USE `clbs`;

-- Mirror the mode the application itself connects with (see
-- config/database.php). Without this, test 6 would wrongly appear to pass.
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';

START TRANSACTION;

-- A throwaway user that we will try to delete while it still owns a booking.
INSERT INTO users (name, email, password, role)
VALUES ('ZZ Test Subject', 'zz.test@invalid.local', 'not-a-real-hash', 'user');
SET @uid = LAST_INSERT_ID();

SELECT '--- 1. orphan booking: user_id does not exist ---' AS test;
INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose)
VALUES ('ZZ-TEST-1', 999999, 1, CURDATE(), '08:00:00', '09:00:00', 'orphan user');
-- EXPECT: ERROR 1452 - a booking can never point at a missing user


SELECT '--- 2. orphan booking: laboratory_id does not exist ---' AS test;
INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose)
VALUES ('ZZ-TEST-2', @uid, 999999, CURDATE(), '08:00:00', '09:00:00', 'orphan lab');
-- EXPECT: ERROR 1452 - a booking can never point at a missing laboratory


SELECT '--- 3. delete a laboratory that still has bookings (RESTRICT) ---' AS test;
DELETE FROM laboratories WHERE id = 1;
-- EXPECT: ERROR 1451 - booking history must survive the deletion of its lab


SELECT '--- 4. delete a user that still has bookings (RESTRICT) ---' AS test;
INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose)
VALUES ('ZZ-TEST-4', @uid, 1, CURDATE(), '08:00:00', '09:00:00', 'gives the test user a booking');
DELETE FROM users WHERE id = @uid;
-- EXPECT: the INSERT succeeds, the DELETE fails with ERROR 1451
-- This is the test that matters most: it is the guarantee that a student
-- cannot be removed in a way that erases their booking record.


SELECT '--- 5. duplicate email (UNIQUE) ---' AS test;
INSERT INTO users (name, email, password) VALUES ('Impostor', 'admin@lab.edu.zm', 'x');
-- EXPECT: ERROR 1062 - one account per email address


SELECT '--- 6. value outside the status ENUM ---' AS test;
UPDATE bookings SET status = 'banana' WHERE id = 1;
-- EXPECT: ERROR 1265 - status is constrained to the five known values.
-- Without strict mode this silently stores '' instead, which would let a
-- corrupted booking slip past the conflict check.


SELECT '--- 7. end_time earlier than start_time ---' AS test;
INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose)
VALUES ('ZZ-TEST-7', @uid, 1, CURDATE(), '12:00:00', '09:00:00', 'backwards period');
-- EXPECT: SUCCEEDS - a known gap. MariaDB 10.1 parses CHECK constraints but
-- ignores them, so the database cannot stop this. The booking form therefore
-- validates the period in PHP before inserting.


SELECT '--- 8. booking outside every enabled time slot ---' AS test;
INSERT INTO bookings (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time, purpose)
VALUES ('ZZ-TEST-8', @uid, 1, CURDATE(), '03:00:00', '05:00:00', '3am slot');
-- EXPECT: SUCCEEDS - another documented gap, checked in PHP on submission.


-- Undo everything: the throwaway user, the ZZ bookings, and the attempt to
-- delete a real laboratory.
ROLLBACK;

SELECT '--- cleanup check (all must be 0) ---' AS verify;
SELECT
  (SELECT COUNT(*) FROM users     WHERE email = 'zz.test@invalid.local') AS leftover_test_users,
  (SELECT COUNT(*) FROM bookings  WHERE booking_ref LIKE 'ZZ-TEST-%')     AS leftover_test_bookings,
  (SELECT COUNT(*) FROM laboratories WHERE id = 1)                        AS lab_1_still_present,
  (SELECT COUNT(*) FROM users WHERE email = 'admin@lab.edu.zm')           AS admin_intact;
