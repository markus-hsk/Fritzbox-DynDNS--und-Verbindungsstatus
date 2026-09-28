<?php
declare(strict_types=1);

// Endpunkt fuer CronJob / UptimeRobot / etc.
// Beispiel-URL: https://DEINE-DOMAIN/dyndns/check.php?secret=DEIN_SECRET
//
// Es wird versucht, eine TCP-Verbindung zur zuletzt von der FritzBox gemeldeten
// IPv4-Adresse aufzubauen (Port siehe config.php). Gelingt das, gilt die
// Internetverbindung als "online", sonst als "offline". Der jeweilige Zeitraum
// wird in der Tabelle connectivity_log fortgeschrieben.
//
// HTTP-Status: 200 = online, 503 = offline/unbekannt (damit z.B. UptimeRobot
// per einfachem HTTP(S)-Monitor auch ohne Keyword-Check einen Ausfall erkennt).

require __DIR__ . '/lib/functions.php';
require __DIR__ . '/lib/Database.php';

$config = dyndns_config();
date_default_timezone_set($config['timezone'] ?? 'UTC');

dyndns_require_secret($config);

$pdo = Database::connect($config);

$stmt = $pdo->prepare(
    'SELECT ip_address FROM ip_history WHERE ip_version = 4 AND ended_at IS NULL ORDER BY id DESC LIMIT 1'
);
$stmt->execute();
$row = $stmt->fetch();

if ($row === false) {
    dyndns_record_connectivity($pdo, 'unknown', null);
    dyndns_respond(503, 'NO IP KNOWN YET');
}

$targetIp = $row['ip_address'];
$port = (int)($config['connectivity_check']['port'] ?? 80);
$timeout = (float)($config['connectivity_check']['timeout'] ?? 5);

$errno = 0;
$errstr = '';
$socket = @fsockopen($targetIp, $port, $errno, $errstr, $timeout);

if ($socket !== false) {
    fclose($socket);
    dyndns_record_connectivity($pdo, 'online', $targetIp);
    dyndns_respond(200, "ONLINE {$targetIp}");
}

dyndns_record_connectivity($pdo, 'offline', $targetIp);
dyndns_respond(503, "OFFLINE {$targetIp} ({$errstr})");
