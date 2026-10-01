<?php
/**
 * Computer Laboratory Booking System
 * -----------------------------------
 * Demo data seeder. Run from the command line:
 *
 *     C:\xampp\php\php.exe database\seed.php
 *     C:\xampp\php\php.exe database\seed.php --fresh
 *
 * --fresh empties the tables first, so the script can be re-run cleanly.
 * Without it the script refuses to touch a database that already has users.
 */

if (PHP_SAPI !== 'cli') {
    die('This script can only be run from the command line.');
}

require_once __DIR__ . '/../includes/bootstrap.php';

$fresh = in_array('--fresh', $argv, true);

/* -------------------------------------------------------------------------
 | Safety checks
 | ---------------------------------------------------------------------- */

$existingUsers = (int) db_value('SELECT COUNT(*) FROM users');

if ($existingUsers > 0 && !$fresh) {
    echo "The database already contains " . $existingUsers . " user(s).\n";
    echo "Re-run with --fresh to wipe and reseed:\n\n";
    echo "    C:\\xampp\\php\\php.exe database\\seed.php --fresh\n";
    exit(1);
}

if ($fresh) {
    echo "Clearing existing data...\n";
    // Children first because of the foreign keys.
    db_exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (array('notifications', 'activity_logs', 'bookings', 'time_slots', 'laboratories', 'users') as $table) {
        db_exec('TRUNCATE TABLE `' . $table . '`');
    }
    db_exec('SET FOREIGN_KEY_CHECKS = 1');
}

/* -------------------------------------------------------------------------
 | Time slots
 | ---------------------------------------------------------------------- */

$slots = array(
    array('Morning Session',    '08:00:00', '12:00:00', 1),
    array('Afternoon Session',  '13:00:00', '17:00:00', 1),
    array('Evening Session',    '17:00:00', '20:00:00', 1),
    array('Weekend Hours',      '09:00:00', '13:00:00', 0),
);

foreach ($slots as $slot) {
    db_exec(
        'INSERT INTO time_slots (label, start_time, end_time, is_active) VALUES (?, ?, ?, ?)',
        $slot
    );
}
echo 'Inserted ' . count($slots) . " time slots.\n";

/* -------------------------------------------------------------------------
 | Users
 | ---------------------------------------------------------------------- */

$adminPassword = 'Admin@123';
$userPassword  = 'Student@123';

$admins = array(
    array('System Administrator', 'admin@lab.edu.zm',   'NCC-STAFF-001', '+260 971 000 101'),
    array('Dr. Thandiwe Banda',   'lecturer@lab.edu.zm', 'NCC-STAFF-002', '+260 971 000 102'),
);

$students = array(
    array('Chisoka Mwange',    'student@lab.edu.zm', 'NCC-2026-001', '+260 972 100 001'),
    array('Mwape Chanda',     'mubanga@lab.edu.zm',  'NCC-2026-002', '+260 972 100 002'),
    array('Kalusha Phiri',    'kphiri@lab.edu.zm',   'NCC-2026-003', '+260 972 100 003'),
    array('Naledi Zulu',      'nzulu@lab.edu.zm',    'NCC-2026-004', '+260 972 100 004'),
    array('Chibwe Mumba',     'cmumba@lab.edu.zm',   'NCC-2026-005', '+260 972 100 005'),
    array('Sikwandiwe Banda', 'sbanda@lab.edu.zm',   'NCC-2026-006', '+260 972 100 006'),
    array('Mutinta Kasonde',  'mkasonde@lab.edu.zm', 'NCC-2026-007', '+260 972 100 007'),
    array('Joseph Mwape',     'jmwape@lab.edu.zm',   'NCC-2026-008', '+260 972 100 008'),
    array('Thandiwe Ncube',   'tncube@lab.edu.zm',   'NCC-2026-009', '+260 972 100 009'),
    array('Kabaso Phiri',     'kphiri2@lab.edu.zm',  'NCC-2026-010', '+260 972 100 010'),
);

$userIds = array();

foreach ($admins as $row) {
    $id = db_insert(
        'INSERT INTO users (name, email, password, role, status, student_no, phone)
         VALUES (?, ?, ?, \'admin\', \'active\', ?, ?)',
        array($row[0], $row[1], password_hash($adminPassword, PASSWORD_DEFAULT), $row[2], $row[3])
    );
    $userIds[$row[1]] = $id;
}

foreach ($students as $row) {
    $id = db_insert(
        'INSERT INTO users (name, email, password, role, status, student_no, phone)
         VALUES (?, ?, ?, \'user\', \'active\', ?, ?)',
        array($row[0], $row[1], password_hash($userPassword, PASSWORD_DEFAULT), $row[2], $row[3])
    );
    $userIds[$row[1]] = $id;
}
echo 'Inserted ' . count($admins) . " administrators and " . count($students) . " students.\n";

/* -------------------------------------------------------------------------
 | Laboratories
 | ---------------------------------------------------------------------- */

$labs = array(
    array('Computer Laboratory 1',  'Block A - Room 101', 40, 40,
          'General purpose teaching laboratory with 40 dual-core workstations.',
          'available'),
    array('Computer Laboratory 2',  'Block A - Room 102', 35, 35,
          'General purpose teaching laboratory with 35 dual-core workstations.',
          'available'),
    array('Networking Laboratory',  'Block B - Lab 2',     25, 25,
          'Isolated network for routing, switching and firewall practicals.',
          'available'),
    array('Advanced Computing Lab', 'Block B - Lab 3',     20, 20,
          'High-performance lab for virtualisation, cloud and cluster work.',
          'available'),
    array('Computer Laboratory 3',  'Block C - Room 201', 30, 30,
          'Reserved for remedial sessions. Currently being rewired.',
          'maintenance'),
);

$labIds = array();
foreach ($labs as $lab) {
    $labIds[$lab[0]] = db_insert(
        'INSERT INTO laboratories (name, location, capacity, computer_count, description, status)
         VALUES (?, ?, ?, ?, ?, ?)',
        $lab
    );
}
echo 'Inserted ' . count($labs) . " laboratories.\n";

/* -------------------------------------------------------------------------
 | Bookings
 |
 | day  = offset in days from today (negative = in the past)
 * Every period sits inside one of the active time slots above, and no two
 * active rows share a laboratory, date and overlapping period - the same
 | invariant the application enforces in Phase 2.
 * ---------------------------------------------------------------------- */

$bookings = array(
    // Completed
    array('Computer Laboratory 1',  -20, '08:00', '12:00', 'Data Structures practical session',        'completed', 'student@lab.edu.zm'),
    array('Computer Laboratory 2',  -18, '13:00', '16:00', 'Networking fundamentals practical',        'completed', 'mubanga@lab.edu.zm'),
    array('Networking Laboratory',  -15, '08:00', '11:00', 'Linux shell scripting workshop',          'completed', 'kphiri@lab.edu.zm'),
    array('Computer Laboratory 1',  -12, '13:00', '17:00', 'Web development practical',               'completed', 'nzulu@lab.edu.zm'),
    array('Advanced Computing Lab', -10, '09:00', '12:00', 'Cyber security awareness session',        'completed', 'cmumba@lab.edu.zm'),

    // Approved
    array('Computer Laboratory 1',  -7,  '08:00', '12:00', 'Operating systems practical',             'approved',  'sbanda@lab.edu.zm'),
    array('Computer Laboratory 2',  -5,  '13:00', '16:00', 'Cloud computing fundamentals',            'approved',  'mkasonde@lab.edu.zm'),
    array('Networking Laboratory',  1,   '08:00', '11:00', 'Database systems practical',             'approved',  'jmwape@lab.edu.zm'),
    array('Computer Laboratory 1',  2,   '13:00', '16:00', 'Python programming lab',                 'approved',  'tncube@lab.edu.zm'),
    array('Advanced Computing Lab', 3,   '09:00', '12:00', 'Network configuration workshop',         'approved',  'kphiri2@lab.edu.zm'),

    // Pending - these are what the administrator approves in the demo
    array('Computer Laboratory 2',  4,   '08:00', '10:00', 'Packet analysis practical',               'pending',   'student@lab.edu.zm'),
    array('Computer Laboratory 1',  5,   '13:00', '15:00', 'Group project work',                     'pending',   'mubanga@lab.edu.zm'),
    array('Networking Laboratory',  6,   '08:00', '11:00', 'Virtualisation hands-on',                'pending',   'kphiri@lab.edu.zm'),
    array('Advanced Computing Lab', 7,   '14:00', '17:00', 'Ethical hacking seminar',                'pending',   'nzulu@lab.edu.zm'),
    array('Computer Laboratory 1',  8,   '08:00', '12:00', 'Compiler design practical',              'pending',   'cmumba@lab.edu.zm'),
    array('Computer Laboratory 2',  9,   '15:00', '17:00', 'Server configuration practice',          'pending',   'sbanda@lab.edu.zm'),

    // Rejected
    array('Networking Laboratory',  -3,  '08:00', '12:00', 'Extra tutoring session',                 'rejected',  'mkasonde@lab.edu.zm', 'Lab was reserved for faculty training'),
    array('Advanced Computing Lab', -2,  '13:00', '16:00', 'Student club meeting',                   'rejected',  'jmwape@lab.edu.zm',   'Clashes with scheduled maintenance'),

    // Cancelled by the student
    array('Computer Laboratory 1',  -1,  '13:00', '15:00', 'Personal study session',                 'cancelled', 'tncube@lab.edu.zm'),
    array('Computer Laboratory 2',  10,  '08:00', '11:00', 'Preparation for final examinations',      'cancelled', 'kphiri2@lab.edu.zm'),
);

$adminId  = $userIds['admin@lab.edu.zm'];
$sequence = 1;

foreach ($bookings as $b) {
    $labName    = $b[0];
    $date       = date('Y-m-d', strtotime($b[1] . ' days'));
    $ref        = 'BK-' . date('Y', strtotime($date)) . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    $sequence++;

    $status     = $b[5];
    $studentId  = $userIds[$b[6]];
    $note       = isset($b[7]) ? $b[7] : null;

    // Only reviewed statuses get a reviewer and a timestamp.
    $reviewedBy   = in_array($status, array('approved', 'rejected', 'completed'), true) ? $adminId : null;
    $reviewedAt   = $reviewedBy === null ? null : $date . ' 09:00:00';

    $bookingId = db_insert(
        'INSERT INTO bookings
            (booking_ref, user_id, laboratory_id, booking_date, start_time, end_time,
             purpose, status, reviewed_by, reviewed_at, admin_note, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        array(
            $ref,
            $studentId,
            $labIds[$labName],
            $date,
            $b[2] . ':00',
            $b[3] . ':00',
            $b[4],
            $status,
            $reviewedBy,
            $reviewedAt,
            $note,
            $date . ' 07:30:00',
        )
    );

    // Tell the student about the outcome so the bell menu has content.
    if ($status === 'approved') {
        db_exec(
            'INSERT INTO notifications (user_id, title, message, is_read, created_at)
             VALUES (?, ?, ?, 0, ?)',
            array($studentId, 'Booking approved', 'Your booking ' . $ref . ' for ' . $labName . ' on ' . $date . ' was approved.', $reviewedAt)
        );
    } elseif ($status === 'rejected') {
        db_exec(
            'INSERT INTO notifications (user_id, title, message, is_read, created_at)
             VALUES (?, ?, ?, 0, ?)',
            array($studentId, 'Booking rejected', 'Your booking ' . $ref . ' for ' . $labName . ' was rejected. ' . $note, $reviewedAt)
        );
    }
}
echo 'Inserted ' . count($bookings) . " bookings across all statuses.\n";

/* -------------------------------------------------------------------------
 | Activity log
 | ---------------------------------------------------------------------- */

$actions = array(
    array($adminId,  'login',  'user', $adminId,  'Signed in'),
    array($adminId,  'create', 'laboratory', $labIds['Computer Laboratory 1'], 'Created Computer Laboratory 1'),
    array($adminId,  'create', 'laboratory', $labIds['Networking Laboratory'], 'Created Networking Laboratory'),
    array($adminId,  'update', 'laboratory', $labIds['Computer Laboratory 3'], 'Set Computer Laboratory 3 to maintenance'),
);

foreach ($actions as $action) {
    db_exec(
        'INSERT INTO activity_logs (user_id, action, entity, entity_id, description, ip_address, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        array($action[0], $action[1], $action[2], $action[3], $action[4], '127.0.0.1', date('Y-m-d H:i:s', strtotime('-2 days')))
    );
}
echo 'Inserted ' . count($actions) . " activity log entries.\n";

/* -------------------------------------------------------------------------
 | Summary
 | ---------------------------------------------------------------------- */

echo "\n";
echo "Seed complete.\n";
echo str_repeat('-', 52) . "\n";
printf("  %-22s %d\n", 'Users', (int) db_value('SELECT COUNT(*) FROM users'));
printf("  %-22s %d\n", 'Laboratories', (int) db_value('SELECT COUNT(*) FROM laboratories'));
printf("  %-22s %d\n", 'Bookings', (int) db_value('SELECT COUNT(*) FROM bookings'));
printf("  %-22s %d\n", 'Pending approvals', (int) db_value("SELECT COUNT(*) FROM bookings WHERE status = 'pending'"));
echo str_repeat('-', 52) . "\n\n";
echo "Sign in at /auth/login.php\n";
echo "  Admin   " . $admins[0][1] . "  /  " . $adminPassword . "\n";
echo "  Student " . $students[0][1] . " /  " . $userPassword . "\n";
echo "\n";
