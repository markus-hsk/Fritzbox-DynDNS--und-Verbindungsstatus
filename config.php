<?php
declare(strict_types=1);

// Kopiere diese Datei ggf. nach config.local.php ausserhalb der Versionskontrolle,
// falls du die echten Zugangsdaten nicht mit einchecken willst.

return [
    // Frei erfundenes, langes Zufalls-Token. Wird sowohl von der FritzBox
    // (update.php) als auch vom Cron/UptimeRobot (check.php) als ?secret=...
    // mitgeschickt, damit nicht jeder beliebige Nutzer die Skripte aufrufen kann.
    'secret' => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',

    'db' => [
        // 'sqlite' (Standard, dateibasiert, keine Einrichtung noetig)
        // oder 'mysql' (dann unten die mysql-Zugangsdaten eintragen)
        'driver' => 'sqlite',

        'sqlite_path' => __DIR__ . '/data/dyndns.sqlite',

        'mysql' => [
            'host' => 'localhost',
            'port' => 3306,
            'dbname' => 'dyndns',
            'user' => 'dyndns',
            'pass' => 'CHANGE_ME',
            'charset' => 'utf8mb4',
        ],
    ],

    // Einstellungen fuer die Erreichbarkeits-Pruefung (check.php).
    // Es wird versucht, eine TCP-Verbindung zur zuletzt gemeldeten IPv4-Adresse
    // aufzubauen. Das setzt voraus, dass auf dieser IP/Port etwas erreichbar ist
    // (z.B. ein Portforwarding auf die FritzBox-Weboberflaeche oder ein Geraet
    // im Heimnetz). Passe den Port an deine Situation an.
    'connectivity_check' => [
        'port' => 80,
        'timeout' => 5, // Sekunden
    ],

    'timezone' => 'Europe/Berlin',
];
