# DynDNS / Verbindungs-Monitor

Kleines PHP-Subscript mit zwei Funktionen:

1. **`update.php`** — Empfaengt von der FritzBox die aktuelle IP-Adresse und
   protokolliert eine Historie ("welche IP war wann wie lange vergeben").
2. **`check.php`** — Wird von einem CronJob oder einem externen Dienst wie
   UptimeRobot regelmaessig aufgerufen, prueft ob die Internetverbindung nach
   Hause noch steht, und protokolliert Online-/Offline-Zeitraeume.

Zusaetzlich zeigt **`status.php`** eine Uebersicht (Historie + Statistik) im
Browser an.

Datenhaltung: SQLite (dateibasiert, `data/dyndns.sqlite`), keine Einrichtung
noetig. Ueber `config.php` -> `db.driver` kann stattdessen `mysql` gewaehlt
werden (Zugangsdaten darunter eintragen); das Schema wird bei jedem Aufruf
automatisch angelegt, falls es noch nicht existiert.

## Einrichtung

1. `config.php` oeffnen und `secret` auf einen langen, zufaelligen Wert setzen
   (z. B. mit `bin2hex(random_bytes(24))` erzeugen). Dieses Secret schuetzt
   beide Endpunkte vor fremdem Zugriff.
2. Optional: `connectivity_check.port` anpassen (siehe Abschnitt
   "Wie funktioniert die Erreichbarkeitspruefung?").
3. Ordner ist unter `https://DEINE-DOMAIN/dyndns/` erreichbar.

## FritzBox einrichten

FritzBox-Oberflaeche: **Internet > Freigaben > DynDNS** > Anbieter
"Benutzerdefiniert" auswaehlen und folgende Werte eintragen:

- **Update-URL:**
  `https://DEINE-DOMAIN/dyndns/update.php?secret=DEIN_SECRET&ip=<ipaddr>&ip6=<ip6addr>`
- **Domainname:** ein beliebiger Wert, z. B. `heim` (wird nicht ausgewertet,
  die FritzBox verlangt aber einen Eintrag)
- **Benutzername / Kennwort:** koennen mit Platzhaltern/Dummy-Werten befuellt
  werden, da die Authentifizierung ueber das `secret` in der URL laeuft

Die FritzBox ersetzt `<ipaddr>` durch die aktuelle IPv4- und `<ip6addr>` durch
die aktuelle IPv6-Adresse und ruft die URL bei jeder Einwahl bzw. IP-Aenderung
auf. `update.php` legt bei jeder gemeldeten Aenderung einen neuen Eintrag in
der Tabelle `ip_history` an und schliesst den vorherigen Zeitraum
(`ended_at`). Bleibt die IP gleich, wird nur `last_confirmed_at`
aktualisiert.

## Cron / UptimeRobot einrichten

URL fuer beide Faelle:

`https://DEINE-DOMAIN/dyndns/check.php?secret=DEIN_SECRET`

- **CronJob** (z. B. alle 5 Minuten): einfach per `curl` oder `wget` aufrufen.
- **UptimeRobot** (oder aehnlicher Dienst): als HTTP(s)-Monitor auf obige URL
  anlegen, Intervall z. B. 5 Minuten. `check.php` liefert HTTP 200, solange
  die Verbindung steht, und HTTP 503, sobald sie ausfaellt bzw. keine IP
  bekannt ist — ein normaler HTTP(s)-Monitor erkennt das automatisch als
  Ausfall, ganz ohne Keyword-Konfiguration.

### Wie funktioniert die Erreichbarkeitspruefung?

`check.php` liest die zuletzt von der FritzBox gemeldete IPv4-Adresse aus
`ip_history` und versucht, eine TCP-Verbindung zu dieser IP auf dem in
`config.php` konfigurierten Port aufzubauen (Standard: 80). Gelingt das,
gilt die Verbindung als "online", sonst als "offline". Jeder Statuswechsel
wird als neuer Zeitraum in `connectivity_log` gespeichert (mit `started_at`,
`ended_at`, `last_seen_at`).

**Wichtig:** Das setzt voraus, dass auf der Heim-IP auf diesem Port
tatsaechlich etwas erreichbar ist (z. B. ein Portforwarding auf die
FritzBox-Weboberflaeche oder ein Geraet im Heimnetz). Falls nichts
erreichbar ist, meldet das Script dauerhaft "offline" — in dem Fall den Port
in `config.php` an ein tatsaechlich erreichbares Ziel anpassen.

## Statusseite

`https://DEINE-DOMAIN/dyndns/status.php?secret=DEIN_SECRET` zeigt:

- aktuelle und vergangene IP-Zuweisungen mit Dauer
- Online-/Offline-Historie mit Dauer
- Statistik: Verfuegbarkeit in %, Gesamt-Online-/Offline-Zeit, Anzahl und
  Dauer des laengsten Ausfalls

## Dateien

```
dyndns/
├── config.php          Zugangsdaten, DB-Wahl, Erreichbarkeits-Port
├── update.php           Endpunkt fuer die FritzBox
├── check.php             Endpunkt fuer Cron/UptimeRobot
├── status.php            HTML-Uebersicht
├── lib/
│   ├── Database.php       PDO-Verbindung + Schema-Erstellung (SQLite/MySQL)
│   └── functions.php       Hilfsfunktionen (Auth, IP-/Verbindungs-Logging)
└── data/
    ├── dyndns.sqlite        SQLite-Datenbank (wird automatisch angelegt)
    └── .htaccess             sperrt Web-Zugriff auf den Datenordner
```
