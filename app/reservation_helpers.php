<?php
declare(strict_types=1);

function reservation_parse_id(mixed $value): ?int
{
    $id = filter_var($value, FILTER_VALIDATE_INT);
    return $id !== false && $id !== null && $id > 0 ? $id : null;
}

function reservation_valid_date(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function reservation_time_minutes(string $time): ?int
{
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $time)) {
        return null;
    }

    return ((int) substr($time, 0, 2) * 60) + (int) substr($time, 3, 2);
}

function reservation_time_from_minutes(int $minutes): string
{
    return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
}

function reservation_intervals_overlap(int $newStart, int $newEnd, int $existingStart, int $existingEnd): bool
{
    return $newStart < $existingEnd && $newEnd > $existingStart;
}

function reservation_booking_context(PDO $connection, int $serviceId, int $employeeId, string $date): ?array
{
    if (!reservation_valid_date($date)) {
        return null;
    }

    $serviceQuery = $connection->prepare(
        'SELECT s.duration
         FROM services s
         INNER JOIN employee_services es ON es.service_id = s.id
         INNER JOIN employees e ON e.id = es.employee_id
         INNER JOIN users u ON u.id = e.user_id
         WHERE s.id = :service_id AND s.active = 1
           AND e.id = :employee_id AND e.active = 1 AND u.active = 1
         LIMIT 1'
    );
    $serviceQuery->execute(['service_id' => $serviceId, 'employee_id' => $employeeId]);
    $service = $serviceQuery->fetch();
    if ($service === false) {
        return null;
    }

    $dayOfWeek = (int) (new DateTimeImmutable($date))->format('N');
    $availabilityQuery = $connection->prepare(
        'SELECT start_time, end_time
         FROM employee_availability
         WHERE employee_id = :employee_id AND day_of_week = :day_of_week
         ORDER BY start_time'
    );
    $availabilityQuery->execute(['employee_id' => $employeeId, 'day_of_week' => $dayOfWeek]);

    $reservationsQuery = $connection->prepare(
        "SELECT start_time, end_time
         FROM reservations
         WHERE employee_id = :employee_id AND reservation_date = :reservation_date
           AND status IN ('pending', 'confirmed')"
    );
    $reservationsQuery->execute(['employee_id' => $employeeId, 'reservation_date' => $date]);

    return [
        'duration' => (int) $service['duration'],
        'availability' => $availabilityQuery->fetchAll(),
        'reservations' => $reservationsQuery->fetchAll(),
    ];
}

function reservation_context_allows_slot(array $context, string $date, string $startTime): bool
{
    $start = reservation_time_minutes($startTime);
    $duration = (int) ($context['duration'] ?? 0);
    if ($start === null || $duration < 1 || $start + $duration > 1439) {
        return false;
    }

    $today = new DateTimeImmutable('today');
    $requestedDate = new DateTimeImmutable($date);
    if ($requestedDate < $today) {
        return false;
    }
    if ($requestedDate->format('Y-m-d') === $today->format('Y-m-d')) {
        $appointment = $requestedDate->setTime(intdiv($start, 60), $start % 60);
        if ($appointment <= new DateTimeImmutable()) {
            return false;
        }
    }

    $end = $start + $duration;
    $withinAvailability = false;
    foreach ($context['availability'] as $period) {
        $periodStart = reservation_time_minutes((string) $period['start_time']);
        $periodEnd = reservation_time_minutes((string) $period['end_time']);
        if ($periodStart !== null && $periodEnd !== null && $start >= $periodStart && $end <= $periodEnd) {
            $withinAvailability = true;
            break;
        }
    }
    if (!$withinAvailability) {
        return false;
    }

    foreach ($context['reservations'] as $reservation) {
        $existingStart = reservation_time_minutes((string) $reservation['start_time']);
        $existingEnd = reservation_time_minutes((string) $reservation['end_time']);
        if ($existingStart !== null && $existingEnd !== null
            && reservation_intervals_overlap($start, $end, $existingStart, $existingEnd)
        ) {
            return false;
        }
    }

    return true;
}

function reservation_slot_is_available(
    PDO $connection,
    int $serviceId,
    int $employeeId,
    string $date,
    string $startTime
): bool {
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $startTime)) {
        return false;
    }

    $context = reservation_booking_context($connection, $serviceId, $employeeId, $date);
    return $context !== null && reservation_context_allows_slot($context, $date, $startTime);
}

function reservation_available_slots(PDO $connection, int $serviceId, int $employeeId, string $date): array
{
    $context = reservation_booking_context($connection, $serviceId, $employeeId, $date);
    if ($context === null) {
        return [];
    }

    $slots = [];
    foreach ($context['availability'] as $period) {
        $periodStart = reservation_time_minutes((string) $period['start_time']);
        $periodEnd = reservation_time_minutes((string) $period['end_time']);
        if ($periodStart === null || $periodEnd === null) {
            continue;
        }

        $start = (int) (ceil($periodStart / 15) * 15);
        for (; $start + $context['duration'] <= $periodEnd && $start + $context['duration'] <= 1439; $start += 15) {
            $time = reservation_time_from_minutes($start);
            if (reservation_context_allows_slot($context, $date, $time)) {
                $slots[] = substr($time, 0, 5);
            }
        }
    }

    return array_values(array_unique($slots));
}

function reservation_status_label(string $status): string
{
    return match ($status) {
        'pending' => 'Oczekująca',
        'confirmed' => 'Potwierdzona',
        'completed' => 'Zrealizowana',
        'cancelled' => 'Anulowana',
        default => 'Nieznany status',
    };
}

function reservation_status_class(string $status): string
{
    return match ($status) {
        'confirmed', 'completed' => 'status-badge status-active',
        'cancelled' => 'status-badge status-inactive',
        default => 'status-badge',
    };
}
