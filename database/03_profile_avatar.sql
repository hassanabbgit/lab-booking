-- Add the profile picture column used by includes/profile.php.
--
-- Safe to re-run: the column is only added when it is not already there, so
-- this can be applied to a database created from an older schema.sql without
-- erroring, and running it twice changes nothing.
--
-- Apply with:
--   C:\xampp\mysql\bin\mysql.exe -u root < database\03_profile_avatar.sql
--
-- or paste into phpMyAdmin.

USE `clbs`;

SET @col := (
    SELECT COUNT(*)
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'clbs'
       AND TABLE_NAME   = 'users'
       AND COLUMN_NAME  = 'avatar'
);

SET @sql := IF(
    @col = 0,
    'ALTER TABLE `users` ADD COLUMN `avatar` VARCHAR(191) DEFAULT NULL AFTER `phone`',
    'DO 0'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
