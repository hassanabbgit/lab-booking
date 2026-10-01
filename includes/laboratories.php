<?php
/**
 * Computer Laboratory Booking System - laboratory management rules.
 *
 * Loaded by includes/bootstrap.php, so it is available to every page. The
 * administrator pages use it to add, edit, re-status and delete laboratories;
 * the student pages only read from the table.
 *
 * MariaDB 10.1 parses but ignores CHECK constraints, so every rule that the
 * column types cannot express is enforced here instead: a name has to be
 * unique, a laboratory has to seat somebody, and the computer count cannot
 * exceed the number of seats.
 *
 * PHP 5.6 only: no scalar type hints, no ??, no arrow functions.
 */

if (!defined('APP_BOOTSTRAPPED')) {
    exit('Direct access is not allowed.');
}

if (!defined('LABORATORY_MIN_CAPACITY')) {
    define('LABORATORY_MIN_CAPACITY', 1);
    define('LABORATORY_MAX_CAPACITY', 2000);
    define('LABORATORY_MAX_COMPUTERS', 2000);
    define('LABORATORY_NAME_MAX', 120);
    define('LABORATORY_LOCATION_MAX', 120);
    define('LABORATORY_DESCRIPTION_MAX', 2000);
}

/**
 * The three states a laboratory can be in, in the order they are offered.
 *
 * available   bookable by students
 * maintenance temporarily offline, existing bookings are left alone
 * inactive    out of service
 *
 * @return array
 */
function laboratory_statuses()
{
    return array('available', 'maintenance', 'inactive');
}

/**
 * Human wording for a status, used everywhere so the student and
 * administrator screens cannot drift apart.
 *
 * @param  string $status
 * @return string
 */
function laboratory_status_label($status)
{
    $map = array(
        'available'   => 'Available',
        'maintenance' => 'In maintenance',
        'inactive'    => 'Inactive',
    );
    return isset($map[$status]) ? $map[$status] : ucfirst($status);
}

/**
 * Only 'available' laboratories can be booked. The student booking form and
 * booking_validate() both rely on this being the single definition.
 *
 * @param  string $status
 * @return bool
 */
function laboratory_is_bookable($status)
{
    return $status === 'available';
}

/**
 * How many bookings a laboratory has, split by the ones that still matter.
 *
 * @param  int $labId
 * @return array
 */
function laboratory_booking_summary($labId)
{
    $row = db_one(
        'SELECT COUNT(*) AS total,
                SUM(status = \'pending\')   AS pending,
                SUM(status = \'approved\')  AS approved,
                SUM(status = \'rejected\')  AS rejected,
                SUM(status = \'cancelled\') AS cancelled,
                SUM(status = \'completed\') AS completed
           FROM bookings
          WHERE laboratory_id = ?',
        array($labId)
    );

    $out = array('total' => 0, 'active' => 0, 'pending' => 0, 'approved' => 0,
        'rejected' => 0, 'cancelled' => 0, 'completed' => 0);

    if ($row !== null) {
        // Copy across only the columns the query actually returned: "active" is
        // worked out below and is not one of them.
        foreach ($out as $key => $ignored) {
            if (array_key_exists($key, $row)) {
                $out[$key] = (int) $row[$key];
            }
        }
    }

    // A room is "active" while it is still blocking time for students, which is
    // exactly the two statuses booking_find_conflict() treats as taken.
    $out['active'] = $out['pending'] + $out['approved'];

    return $out;
}

/**
 * Check a set of submitted laboratory fields.
 *
 * $ignoreId is the laboratory being edited, so that saving a record without
 * changing its name is not reported as a clash with itself. Pass 0 when adding.
 *
 * @param  array $input   keys: name, location, capacity, computer_count,
 *                        description, status
 * @param  int   $ignoreId
 * @return array field name => message, empty when everything is acceptable
 */
function laboratory_validate(array $input, $ignoreId = 0)
{
    $errors = array();

    $name        = trim(isset($input['name']) ? $input['name'] : '');
    $location    = trim(isset($input['location']) ? $input['location'] : '');
    $capacityRaw = trim(isset($input['capacity']) ? $input['capacity'] : '');
    $computersRaw = trim(isset($input['computer_count']) ? $input['computer_count'] : '');
    $description = trim(isset($input['description']) ? $input['description'] : '');
    $status      = trim(isset($input['status']) ? $input['status'] : '');

    /* ---------------------------------------------------------------- name */
    if ($name === '') {
        $errors['name'] = 'Give the laboratory a name.';
    } elseif (mb_strlen($name) < 3) {
        $errors['name'] = 'The name needs to be at least 3 characters.';
    } elseif (mb_strlen($name) > LABORATORY_NAME_MAX) {
        $errors['name'] = 'Keep the name under ' . LABORATORY_NAME_MAX . ' characters.';
    } else {
        $clash = db_one(
            'SELECT id FROM laboratories WHERE name = ? AND id <> ? LIMIT 1',
            array($name, (int) $ignoreId)
        );
        if ($clash !== null) {
            $errors['name'] = 'Another laboratory is already called that.';
        }
    }

    /* ------------------------------------------------------------ location */
    if ($location === '') {
        $errors['location'] = 'Say where the laboratory is.';
    } elseif (mb_strlen($location) > LABORATORY_LOCATION_MAX) {
        $errors['location'] = 'Keep the location under ' . LABORATORY_LOCATION_MAX . ' characters.';
    }

    /* ------------------------------------------------------------ capacity */
    $capacity = null;
    if ($capacityRaw === '') {
        $errors['capacity'] = 'Enter how many students the laboratory seats.';
    } elseif (!preg_match('/^\d{1,7}$/', $capacityRaw)) {
        $errors['capacity'] = 'Use a whole number of seats, with no sign or decimal point.';
    } else {
        $capacity = (int) $capacityRaw;
        if ($capacity < LABORATORY_MIN_CAPACITY) {
            $errors['capacity'] = 'A laboratory has to seat at least '
                . LABORATORY_MIN_CAPACITY . ' student.';
        } elseif ($capacity > LABORATORY_MAX_CAPACITY) {
            $errors['capacity'] = 'That is more seats than any laboratory here holds ('
                . LABORATORY_MAX_CAPACITY . ').';
        }
    }

    /* ------------------------------------------------------ computer count */
    if ($computersRaw === '') {
        $errors['computer_count'] = 'Enter how many computers it has.';
    } elseif (!preg_match('/^\d{1,7}$/', $computersRaw)) {
        $errors['computer_count'] = 'Use a whole number of computers.';
    } else {
        $computers = (int) $computersRaw;
        if ($computers > LABORATORY_MAX_COMPUTERS) {
            $errors['computer_count'] = 'That is more computers than the facility has ('
                . LABORATORY_MAX_COMPUTERS . ').';
        } elseif ($capacity !== null && $computers > $capacity) {
            // A room cannot hold more machines than it has seats for.
            $errors['computer_count'] = 'There are only ' . $capacity
                . ' seats, so it cannot have ' . $computers . ' computers.';
        }
    }

    /* --------------------------------------------------------- description */
    if (mb_strlen($description) > LABORATORY_DESCRIPTION_MAX) {
        $errors['description'] = 'Keep the description under '
            . LABORATORY_DESCRIPTION_MAX . ' characters.';
    }

    /* -------------------------------------------------------------- status */
    if (!in_array($status, laboratory_statuses(), true)) {
        $errors['status'] = 'Choose whether it is available, in maintenance or inactive.';
    }

    return $errors;
}

/**
 * Pull the six fields out of a request and normalise them, so the same code
 * serves both the add and the edit form.
 *
 * @return array
 */
function laboratory_input_from_post()
{
    return array(
        'name'           => post('name'),
        'location'       => post('location'),
        'capacity'       => trim(post('capacity')),
        'computer_count' => trim(post('computer_count')),
        'description'    => post('description'),
        'status'         => post('status'),
    );
}

/**
 * Add a laboratory.
 *
 * The name is checked before the insert as well, but the UNIQUE index is what
 * actually guarantees it: two administrators saving the same name at the same
 * moment would both pass the check.
 *
 * @param  array $input keys as laboratory_validate()
 * @return array array('ok' => bool, 'id' => int, 'error' => string)
 */
function laboratory_create(array $input)
{
    try {
        $id = db_insert(
            'INSERT INTO laboratories (name, location, capacity, computer_count, description, status)
             VALUES (?, ?, ?, ?, ?, ?)',
            array(
                trim($input['name']),
                trim($input['location']),
                (int) $input['capacity'],
                (int) $input['computer_count'],
                trim($input['description']) === '' ? null : trim($input['description']),
                $input['status'],
            )
        );

        return array('ok' => true, 'id' => $id, 'error' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return array('ok' => false, 'id' => 0,
                'error' => 'Another laboratory is already called that.');
        }
        error_log('CLBS laboratory create failed: ' . $e->getMessage());
        return array('ok' => false, 'id' => 0,
            'error' => 'The laboratory could not be created. Please try again.');
    }
}

/**
 * Save changes to an existing laboratory.
 *
 * @param  int   $id
 * @param  array $input keys as laboratory_validate()
 * @return array array('ok' => bool, 'id' => int, 'error' => string)
 */
function laboratory_update($id, array $input)
{
    $id = (int) $id;

    if (db_one('SELECT id FROM laboratories WHERE id = ?', array($id)) === null) {
        return array('ok' => false, 'id' => $id, 'error' => 'That laboratory no longer exists.');
    }

    try {
        db_exec(
            'UPDATE laboratories
                SET name = ?, location = ?, capacity = ?, computer_count = ?,
                    description = ?, status = ?
              WHERE id = ?',
            array(
                trim($input['name']),
                trim($input['location']),
                (int) $input['capacity'],
                (int) $input['computer_count'],
                trim($input['description']) === '' ? null : trim($input['description']),
                $input['status'],
                $id,
            )
        );

        return array('ok' => true, 'id' => $id, 'error' => '');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return array('ok' => false, 'id' => $id,
                'error' => 'Another laboratory is already called that.');
        }
        error_log('CLBS laboratory update failed: ' . $e->getMessage());
        return array('ok' => false, 'id' => $id,
            'error' => 'The changes could not be saved. Please try again.');
    }
}

/**
 * Move a laboratory between available, maintenance and inactive.
 *
 * This never touches its bookings. Taking a room offline only stops it being
 * offered to students; anything already reserved stays reserved so the
 * history remains true.
 *
 * @param  int    $id
 * @param  string $status
 * @return array array('ok' => bool, 'error' => string)
 */
function laboratory_set_status($id, $status)
{
    $id = (int) $id;

    if (!in_array($status, laboratory_statuses(), true)) {
        return array('ok' => false, 'error' => 'That is not a laboratory status.');
    }

    $lab = db_one('SELECT * FROM laboratories WHERE id = ?', array($id));
    if ($lab === null) {
        return array('ok' => false, 'error' => 'That laboratory no longer exists.');
    }

    if ($lab['status'] === $status) {
        return array('ok' => false,
            'error' => 'It is already marked as ' . lcfirst(laboratory_status_label($status)) . '.');
    }

    db_exec('UPDATE laboratories SET status = ? WHERE id = ?', array($status, $id));

    return array('ok' => true, 'error' => '');
}

/**
 * Delete a laboratory, but only while it has never been booked.
 *
 * A laboratory with any booking history is kept and taken offline instead: the
 * foreign key is ON DELETE RESTRICT precisely so that a room cannot vanish out
 * from under its own history. This check exists to turn that database error
 * into a sentence an administrator can act on.
 *
 * @param  int $id
 * @return array array('ok' => bool, 'error' => string)
 */
function laboratory_delete($id)
{
    $id = (int) $id;

    $lab = db_one('SELECT * FROM laboratories WHERE id = ?', array($id));
    if ($lab === null) {
        return array('ok' => false, 'error' => 'That laboratory no longer exists.');
    }

    $bookings = (int) db_value('SELECT COUNT(*) FROM bookings WHERE laboratory_id = ?', array($id));

    if ($bookings > 0) {
        return array(
            'ok'    => false,
            'error' => $lab['name'] . ' has ' . $bookings . ' booking'
                . ($bookings === 1 ? '' : 's')
                . ' recorded against it, so it cannot be deleted without losing that history. '
                . 'Mark it inactive or in maintenance instead.',
        );
    }

    try {
        db_exec('DELETE FROM laboratories WHERE id = ?', array($id));
        return array('ok' => true, 'error' => '');
    } catch (PDOException $e) {
        // 1451/1452 are the MySQL numbers behind SQLSTATE 23000: something else
        // still points at this row.
        $errno = isset($e->errorInfo[1]) ? (int) $e->errorInfo[1] : 0;
        if ($e->getCode() === '23000' || $errno === 1451 || $errno === 1452) {
            return array('ok' => false,
                'error' => 'Something else still refers to this laboratory, so it was kept. '
                    . 'Mark it inactive instead.');
        }
        error_log('CLBS laboratory delete failed: ' . $e->getMessage());
        return array('ok' => false, 'error' => 'The laboratory could not be deleted.');
    }
}

/**
 * Totals for the administrator dashboard strip.
 *
 * @return array
 */
function laboratory_totals()
{
    $row = db_one(
        'SELECT COUNT(*) AS total,
                SUM(status = \'available\')   AS available,
                SUM(status = \'maintenance\') AS maintenance,
                SUM(status = \'inactive\')    AS inactive,
                SUM(capacity)                 AS seats,
                SUM(computer_count)           AS computers
           FROM laboratories'
    );

    $out = array('total' => 0, 'available' => 0, 'maintenance' => 0, 'inactive' => 0,
        'seats' => 0, 'computers' => 0);

    if ($row !== null) {
        foreach ($out as $key => $ignored) {
            if (array_key_exists($key, $row)) {
                $out[$key] = (int) $row[$key];
            }
        }
    }

    return $out;
}
