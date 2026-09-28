<?php
declare(strict_types=1);

// Endpunkt fuer die FritzBox (Internet > Freigaben > DynDNS, "Benutzerdefiniert").
// Beispiel Update-URL:
// https://DEINE-DOMAIN/dyndns/update.php?secret=DEIN_SECRET&ip=<ipaddr>&ip6=<ip6addr>

require __DIR__ . '/lib/functions.php';
require __DIR__ . '/lib/Database.php';

$config = dyndns_config();
date_default_timezone_set($config['timezone'] ?? 'UTC');

dyndns_require_secret($config);

$ip4 = trim((string)($_GET['ip'] ?? $_GET['ipv4'] ?? ''));
$ip6 = trim((string)($_GET['ip6'] ?? $_GET['ipv6'] ?? ''));

if ($ip4 === '' && $ip6 === '') {
    dyndns_respond(400, 'MISSING IP PARAMETER');
}

$pdo = Database::connect($config);

$updated = [];

if ($ip4 !== '') {
    if (!dyndns_is_valid_ip($ip4, 4)) {
        dyndns_respond(400, 'INVALID IPV4 ADDRESS');
    }
    dyndns_record_ip($pdo, $ip4, 4);
    $updated[] = "IPv4={$ip4}";
}

if ($ip6 !== '') {
    if (!dyndns_is_valid_ip($ip6, 6)) {
        dyndns_respond(400, 'INVALID IPV6 ADDRESS');
    }
    dyndns_record_ip($pdo, $ip6, 6);
    $updated[] = "IPv6={$ip6}";
}

dyndns_respond(200, 'OK ' . implode(' ', $updated));
