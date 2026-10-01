-- Apply the RESTRICT deletion policy from schema.sql to an existing database.
-- Safe to re-run.
USE `clbs`;

ALTER TABLE `bookings` DROP FOREIGN KEY `fk_bookings_user`;
ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_user`
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `bookings` DROP FOREIGN KEY `fk_bookings_laboratory`;
ALTER TABLE `bookings` ADD CONSTRAINT `fk_bookings_laboratory`
  FOREIGN KEY (`laboratory_id`) REFERENCES `laboratories` (`id`)
  ON DELETE RESTRICT ON UPDATE CASCADE;
