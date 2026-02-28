<?php
declare(strict_types=1);

function expiry_check_run(PDO $pdo, bool $force = false, int $intervalSeconds = 3600): array
{
    $flagPath = base_path('data/expiry_check.json');

    if (!$force && file_exists($flagPath)) {
        $contents = file_get_contents($flagPath);
        if ($contents !== false) {
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && !empty($decoded['checked_at'])) {
                $last = strtotime((string) $decoded['checked_at']) ?: 0;
                if ($last > 0 && (time() - $last) < $intervalSeconds) {
                    return $decoded;
                }
            }
        }
    }

    $overdue = get_domains_overdue($pdo);
    $within7 = get_domains_expiring_between($pdo, 0, 7);
    $within30 = get_domains_expiring_between($pdo, 8, 30);
    $within60 = get_domains_expiring_between($pdo, 31, 60);
    $within90 = get_domains_expiring_between($pdo, 61, 90);

    $payload = [
        'checked_at' => date('c'),
        'overdue' => $overdue,
        'within_7' => $within7,
        'within_30' => $within30,
        'within_60' => $within60,
        'within_90' => $within90,
        'count_overdue' => count($overdue),
        'count_7' => count($within7),
        'count_30' => count($within30),
        'count_60' => count($within60),
        'count_90' => count($within90),
        'count_30_total' => count($overdue) + count($within7) + count($within30),
    ];

    file_put_contents($flagPath, json_encode($payload, JSON_PRETTY_PRINT));

    return $payload;
}
