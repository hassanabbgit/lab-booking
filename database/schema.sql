-- ---------------------------------------------------------------------------
-- Computer Laboratory Booking System - database schema
--
--   Target : MySQL / MariaDB 10.1+ (XAMPP)
--   Charset: utf8mb4 (full Unicode, incl. emoji and accented names)
--
-- How to import
--   phpMyAdmin : Import -> choose this file
--   Command    : mysql -u root < database/schema.sql
--
-- The script is idempotent: re-running it will not destroy existing data.
-- To rebuild from scratch, drop the database first (see README).
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `clbs`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `clbs`;

-- Drop in child -> parent order so the script can be re-run after edits.
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `activity_logs`;
DROP TABLE IF EXISTS `bookings`;
DROP TABLE IF EXISTS `time_slots`;
DROP TABLE IF EXISTS `laboratories`;
DROP TABLE IF EXISTS `users`;


-- ---------------------------------------------------------------------------
-- users
-- Accounts for administrators and students. Passwords are bcrypt hashes
-- produced by PHP password_hash(); plaintext is never stored.
-- ---------------------------------------------------------------------------
CREATE TABLE `users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(120)  NOT NULL,
  `email`         VARCHAR(191)  NOT NULL,
  `password`      VARCHAR(255)  NOT NULL,
  `role`          ENUM('admin','user')   NOT NULL DEFAULT 'user',
  `status`        ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `student_no`    VARCHAR(50)   DEFAULT NULL,
  `phone`         VARCHAR(30)   DEFAULT NULL,
  `last_login_at` DATETIME      DEFAULT NULL,
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_student_no` (`student_no`),
  KEY `idx_users_role_status` (`role`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- laboratories
-- The bookable computer laboratories.
-- ---------------------------------------------------------------------------
CREATE TABLE `laboratories` (
  `id`             INT(11)      NOT NULL AUTO_INCREMENT,
  `name`           VARCHAR(120)  NOT NULL,
  `location`       VARCHAR(120)  NOT NULL,
  `capacity`       INT(11)      NOT NULL DEFAULT 0,
  `computer_count` INT(11)      NOT NULL DEFAULT 0,
  `description`    TEXT          DEFAULT NULL,
  `status`         ENUM('available','maintenance','inactive') NOT NULL DEFAULT 'available',
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_laboratories_name` (`name`),
  KEY `idx_laboratories_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- time_slots
-- The bookable windows for a working day. Administrators enable or disable
-- these; a booking must fall entirely inside one enabled slot.
-- ---------------------------------------------------------------------------
CREATE TABLE `time_slots` (
  `id`         INT(11)     NOT NULL AUTO_INCREMENT,
  `label`      VARCHAR(60) NOT NULL,
  `start_time` TIME         NOT NULL,
  `end_time`   TIME         NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_time_slots_range` (`start_time`,`end_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- bookings
-- One reserved period in one laboratory.
--
-- Overlap rule: two bookings conflict when
--     existing.start_time < new.end_time  AND  existing.end_time > new.start_time
-- on the same laboratory and date, ignoring rows that are rejected or
-- cancelled. MySQL/MariaDB cannot express that as a constraint, so it is
-- enforced in PHP when a booking is submitted or approved.
--
-- idx_bookings_conflict is deliberately ordered to match that lookup.
--
-- Deletion policy
--   RESTRICT on the two parents: a laboratory or user that still has bookings
--   cannot be deleted. Cascading here would silently destroy booking history,
--   which the admin "booking history" and student "my bookings" reports both
--   depend on. Remove a record by flipping status instead:
--     laboratories.status = 'inactive'   users.status = 'inactive'
--   SET NULL on reviewed_by: if the reviewing administrator is deleted, the
--   decision they recorded must survive, just without an attribution.
-- ---------------------------------------------------------------------------
CREATE TABLE `bookings` (
  `id`            INT(11)     NOT NULL AUTO_INCREMENT,
  `booking_ref`   VARCHAR(20)  NOT NULL,
  `user_id`       INT(11)     NOT NULL,
  `laboratory_id` INT(11)     NOT NULL,
  `booking_date`  DATE         NOT NULL,
  `start_time`    TIME         NOT NULL,
  `end_time`      TIME         NOT NULL,
  `purpose`       VARCHAR(255) NOT NULL,
  `status`        ENUM('pending','approved','rejected','cancelled','completed')
                                NOT NULL DEFAULT 'pending',
  `reviewed_by`   INT(11)      DEFAULT NULL,
  `reviewed_at`   DATETIME     DEFAULT NULL,
  `admin_note`    VARCHAR(255) DEFAULT NULL,
  `created_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bookings_ref` (`booking_ref`),
  KEY `idx_bookings_conflict` (`laboratory_id`,`booking_date`,`status`),
  KEY `idx_bookings_user` (`user_id`),
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_created` (`created_at`),
  CONSTRAINT `fk_bookings_user`
    FOREIGN KEY (`user_id`)       REFERENCES `users` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_laboratory`
    FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_bookings_reviewer`
    FOREIGN KEY (`reviewed_by`)   REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- activity_logs
-- Audit trail for sign-ins and administrative changes.
-- ----------------------------------------------------------------------
CREATE TABLE `activity_logs` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11)      DEFAULT NULL,
  `action`      VARCHAR(64)  NOT NULL,
  `entity`      VARCHAR(32)  DEFAULT NULL,
  `entity_id`   INT(11)      DEFAULT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`),
  CONSTRAINT `fk_activity_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------------
-- notifications
-- Messages shown in the user bell menu (e.g. "your booking was approved").
-- ---------------------------------------------------------------------- */
CREATE TABLE `notifications` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11)      NOT NULL,
  `title`      VARCHAR(120) NOT NULL,
  `message`    VARCHAR(255) NOT NULL,
  `link`       VARCHAR(191) DEFAULT NULL,
  `is_read`    TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_unread` (`user_id`,`is_read`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
