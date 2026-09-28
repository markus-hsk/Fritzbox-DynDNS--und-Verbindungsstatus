<?php
declare(strict_types=1);

function dyndns_config(): array
{
    static $config = null;
    if ($config === null) {
        $config = require __DIR__ . '/../config.php';
    }
    return $config;
}

function dyndns_require_secret(array $config): void
{
    $provided = $_GET['secret'] ?? '';
    if (!is_string($provided) || $provided === '' || !hash_equals((string)$config['secret'], $provided)) {
        dyndns_respond(403, 'FORBIDDEN');
    }
}

function dyndns_respond(int $code, string $text): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $text . "\n";
    exit;
}

function dyndns_now(): string
{
    return (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
}

function dyndns_is_valid_ip(string $ip, int $version): bool
{
    $flag = $version === 6 ? FILTER_FLAG_IPV6 : FILTER_FLAG_IPV4;
    return filter_var($ip, FILTER_VALIDATE_IP, $flag) !== false;
}

function dyndns_record_ip(PDO $pdo, string $ip, int $version, string $source = 'fritzbox'): void
{
    $now = dyndns_now();

    $stmt = $pdo->prepare(
        'SELECT id, ip_address FROM ip_history WHERE ip_version = :v AND ended_at IS NULL ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['v' => $version]);
    $current = $stmt->fetch();

    if ($current === false) {
        $insert = $pdo->prepare(
            'INSERT INTO ip_history (ip_address, ip_version, started_at, last_confirmed_at, source)
             VALUES (:ip, :v, :now, :now, :source)'
        );
        $insert->execute(['ip' => $ip, 'v' => $version, 'now' => $now, 'source' => $source]);
        return;
    }

    if ($current['ip_address'] === $ip) {
        $update = $pdo->prepare('UPDATE ip_history SET last_confirmed_at = :now WHERE id = :id');
        $update->execute(['now' => $now, 'id' => $current['id']]);
        return;
    }

    $close = $pdo->prepare('UPDATE ip_history SET ended_at = :now WHERE id = :id');
    $close->execute(['now' => $now, 'id' => $current['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO ip_history (ip_address, ip_version, started_at, last_confirmed_at, source)
         VALUES (:ip, :v, :now, :now, :source)'
    );
    $insert->execute(['ip' => $ip, 'v' => $version, 'now' => $now, 'source' => $source]);
}

function dyndns_record_connectivity(PDO $pdo, string $status, ?string $checkedIp): void
{
    $now = dyndns_now();

    $stmt = $pdo->prepare('SELECT id, status FROM connectivity_log WHERE ended_at IS NULL ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $current = $stmt->fetch();

    if ($current === false) {
        $insert = $pdo->prepare(
            'INSERT INTO connectivity_log (status, checked_ip, started_at, last_seen_at)
             VALUES (:status, :ip, :now, :now)'
        );
        $insert->execute(['status' => $status, 'ip' => $checkedIp, 'now' => $now]);
        return;
    }

    if ($current['status'] === $status) {
        $update = $pdo->prepare('UPDATE connectivity_log SET last_seen_at = :now, checked_ip = :ip WHERE id = :id');
        $update->execute(['now' => $now, 'ip' => $checkedIp, 'id' => $current['id']]);
        return;
    }

    $close = $pdo->prepare('UPDATE connectivity_log SET ended_at = :now WHERE id = :id');
    $close->execute(['now' => $now, 'id' => $current['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO connectivity_log (status, checked_ip, started_at, last_seen_at)
         VALUES (:status, :ip, :now, :now)'
    );
    $insert->execute(['status' => $status, 'ip' => $checkedIp, 'now' => $now]);
}
