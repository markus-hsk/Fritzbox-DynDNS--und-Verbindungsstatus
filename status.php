<?php
declare(strict_types=1);

// Einfache Uebersichtsseite: https://DEINE-DOMAIN/dyndns/status.php?secret=DEIN_SECRET

require __DIR__ . '/lib/functions.php';
require __DIR__ . '/lib/Database.php';

$config = dyndns_config();
date_default_timezone_set($config['timezone'] ?? 'UTC');

dyndns_require_secret($config);

$pdo = Database::connect($config);

function dyndns_format_duration(string $from, ?string $to): string
{
    $start = new DateTimeImmutable($from);
    $end = $to !== null ? new DateTimeImmutable($to) : new DateTimeImmutable('now');
    $diff = $start->diff($end);

    $parts = [];
    if ($diff->days > 0) {
        $parts[] = $diff->days . ' d';
    }
    if ($diff->h > 0) {
        $parts[] = $diff->h . ' h';
    }
    if ($diff->i > 0 || $parts === []) {
        $parts[] = $diff->i . ' min';
    }

    return implode(' ', $parts);
}

$ipHistory = $pdo->query(
    'SELECT * FROM ip_history ORDER BY id DESC LIMIT 100'
)->fetchAll();

$connectivityLog = $pdo->query(
    'SELECT * FROM connectivity_log ORDER BY id DESC LIMIT 200'
)->fetchAll();

// Rohdaten fuer die Statistik-Buttons und das Verlaufsdiagramm; die
// Zeitraum-Berechnung (heute/7 Tage/30 Tage/Monat/Jahr/gesamt) passiert im Browser via JS.
$statsRows = $pdo->query(
    'SELECT status, started_at, ended_at FROM connectivity_log ORDER BY id ASC'
)->fetchAll();

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>DynDNS Status</title>
<style>
    body { font-family: system-ui, sans-serif; margin: 2rem; color: #222; }
    h1 { font-size: 1.4rem; }
    h2 { font-size: 1.1rem; margin-top: 2rem; }
    table { border-collapse: collapse; width: 100%; margin-top: 0.5rem; }
    th, td { border: 1px solid #ddd; padding: 0.35rem 0.6rem; text-align: left; font-size: 0.9rem; }
    th { background: #f2f2f2; }
    .online { color: #167a30; font-weight: bold; }
    .offline { color: #b3261e; font-weight: bold; }
    .unknown { color: #8a6d00; font-weight: bold; }

    .period-controls { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.5rem; }
    .period-btn {
        border: 1px solid #ccc;
        background: #fff;
        border-radius: 999px;
        padding: 0.35rem 0.9rem;
        font-size: 0.85rem;
        cursor: pointer;
        color: #333;
    }
    .period-btn:hover { background: #f0f0f0; }
    .period-btn.active { background: #222; border-color: #222; color: #fff; }

    .stats { display: flex; gap: 1rem; flex-wrap: wrap; margin-top: 0.75rem; }
    .stat-box { background: #f7f7f7; border-radius: 6px; padding: 0.75rem 1rem; min-width: 140px; }
    .stat-box .value { font-size: 1.3rem; font-weight: bold; }
    .stat-box .label { font-size: 0.8rem; color: #555; }

    .stat-box-chart { flex: 2 1 320px; min-width: 260px; }
    .chart {
        display: flex;
        width: 100%;
        height: 28px;
        margin-top: 0.4rem;
        border-radius: 4px;
        overflow: hidden;
        background: #eee;
    }
    .chart.empty {
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
        color: #777;
    }
    .chart .seg { height: 100%; flex-basis: 0; }
    .chart .seg.online { background: #2e9e46; }
    .chart .seg.offline { background: #c62828; }
    .chart-legend { display: flex; gap: 1rem; margin-top: 0.35rem; font-size: 0.75rem; color: #555; }
    .chart-legend span { display: inline-flex; align-items: center; gap: 0.3rem; }
    .chart-legend i { width: 10px; height: 10px; border-radius: 2px; display: inline-block; }
    .chart-legend i.online { background: #2e9e46; }
    .chart-legend i.offline { background: #c62828; }
</style>
</head>
<body>

<h1>DynDNS &amp; Verbindungs-Status</h1>

<h2>Statistik</h2>
<div class="period-controls">
    <button type="button" class="period-btn" data-period="30days">Letzte 30 Tage</button>
    <button type="button" class="period-btn" data-period="7days">Letzte 7 Tage</button>
    <button type="button" class="period-btn" data-period="today">Heute</button>
    <button type="button" class="period-btn" data-period="month">Diesen Monat</button>
    <button type="button" class="period-btn" data-period="year">Dieses Jahr</button>
    <button type="button" class="period-btn active" data-period="all">Gesamt</button>
</div>
<div class="stats">
    <div class="stat-box">
        <div class="value" id="stat-uptime">-</div>
        <div class="label">Verfuegbarkeit</div>
    </div>
    <div class="stat-box">
        <div class="value" id="stat-online">-</div>
        <div class="label">Online</div>
    </div>
    <div class="stat-box">
        <div class="value" id="stat-offline">-</div>
        <div class="label">Offline</div>
    </div>
    <div class="stat-box">
        <div class="value" id="stat-outages">-</div>
        <div class="label">Anzahl Ausfaelle</div>
    </div>
    <div class="stat-box">
        <div class="value" id="stat-longest">-</div>
        <div class="label">Laengster Ausfall</div>
    </div>
    <div class="stat-box stat-box-chart">
        <div class="label">Verlauf</div>
        <div class="chart" id="stat-chart"></div>
        <div class="chart-legend">
            <span><i class="online"></i> Online</span>
            <span><i class="offline"></i> Offline</span>
        </div>
    </div>
</div>

<h2>Verbindungs-Historie (online/offline)</h2>
<table>
    <tr>
        <th>Status</th>
        <th>Gepruefte IP</th>
        <th>Von</th>
        <th>Bis</th>
        <th>Dauer</th>
        <th>Zuletzt gesehen</th>
    </tr>
    <?php foreach ($connectivityLog as $row): ?>
    <tr>
        <td class="<?= htmlspecialchars($row['status']) ?>"><?= htmlspecialchars(strtoupper($row['status'])) ?></td>
        <td><?= htmlspecialchars($row['checked_ip'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['started_at']) ?></td>
        <td><?= $row['ended_at'] !== null ? htmlspecialchars($row['ended_at']) : '<span class="online">laufend</span>' ?></td>
        <td><?= htmlspecialchars(dyndns_format_duration($row['started_at'], $row['ended_at'])) ?></td>
        <td><?= htmlspecialchars($row['last_seen_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($connectivityLog === []): ?>
    <tr><td colspan="6">Noch keine Daten. Warte auf ersten Aufruf von check.php durch Cron/UptimeRobot.</td></tr>
    <?php endif; ?>
</table>

<h2>IP-Adressen-Historie</h2>
<table>
    <tr>
        <th>IP-Adresse</th>
        <th>Version</th>
        <th>Von</th>
        <th>Bis</th>
        <th>Dauer</th>
        <th>Zuletzt bestaetigt</th>
    </tr>
    <?php foreach ($ipHistory as $row): ?>
    <tr>
        <td><?= htmlspecialchars($row['ip_address']) ?></td>
        <td>IPv<?= (int)$row['ip_version'] ?></td>
        <td><?= htmlspecialchars($row['started_at']) ?></td>
        <td><?= $row['ended_at'] !== null ? htmlspecialchars($row['ended_at']) : '<span class="online">aktuell</span>' ?></td>
        <td><?= htmlspecialchars(dyndns_format_duration($row['started_at'], $row['ended_at'])) ?></td>
        <td><?= htmlspecialchars($row['last_confirmed_at']) ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if ($ipHistory === []): ?>
    <tr><td colspan="6">Noch keine Daten. Warte auf ersten Aufruf von update.php durch die FritzBox.</td></tr>
    <?php endif; ?>
</table>

<script>
(function () {
    var rawRows = <?= json_encode($statsRows, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var NOW = Date.now();

    function parseServerDate(s) {
        return new Date(s.replace(' ', 'T')).getTime();
    }

    var rows = rawRows.map(function (r) {
        return {
            status: r.status,
            start: parseServerDate(r.started_at),
            end: r.ended_at !== null ? parseServerDate(r.ended_at) : NOW
        };
    });

    function periodRange(period) {
        var end = NOW;
        var now = new Date(NOW);
        var start;
        switch (period) {
            case 'today':
                start = new Date(now.getFullYear(), now.getMonth(), now.getDate()).getTime();
                break;
            case '7days':
                start = end - 7 * 86400000;
                break;
            case '30days':
                start = end - 30 * 86400000;
                break;
            case 'month':
                start = new Date(now.getFullYear(), now.getMonth(), 1).getTime();
                break;
            case 'year':
                start = new Date(now.getFullYear(), 0, 1).getTime();
                break;
            case 'all':
            default:
                start = rows.length ? Math.min.apply(null, rows.map(function (r) { return r.start; })) : end;
                break;
        }
        return { start: start, end: end };
    }

    function formatDuration(totalSeconds) {
        var seconds = Math.max(0, Math.round(totalSeconds));
        var days = Math.floor(seconds / 86400);
        seconds %= 86400;
        var hours = Math.floor(seconds / 3600);
        seconds %= 3600;
        var minutes = Math.floor(seconds / 60);

        var parts = [];
        if (days > 0) parts.push(days + ' d');
        if (hours > 0) parts.push(hours + ' h');
        if (minutes > 0 || parts.length === 0) parts.push(minutes + ' min');
        return parts.join(' ');
    }

    function formatClock(ms) {
        return new Date(ms).toLocaleString('de-DE');
    }

    function renderPeriod(period) {
        var range = periodRange(period);
        var start = range.start;
        var end = range.end;

        var onlineSeconds = 0;
        var offlineSeconds = 0;
        var outageCount = 0;
        var longestOutageSeconds = 0;
        var segments = [];

        rows.forEach(function (row) {
            var segStart = Math.max(row.start, start);
            var segEnd = Math.min(row.end, end);
            if (segEnd <= segStart) {
                return;
            }

            var seconds = (segEnd - segStart) / 1000;
            if (row.status === 'online') {
                onlineSeconds += seconds;
            } else if (row.status === 'offline') {
                offlineSeconds += seconds;
                outageCount++;
                longestOutageSeconds = Math.max(longestOutageSeconds, seconds);
            }
            segments.push({ status: row.status, start: segStart, end: segEnd, seconds: seconds });
        });

        var totalSeconds = onlineSeconds + offlineSeconds;
        var uptimePercent = totalSeconds > 0 ? Math.round((onlineSeconds / totalSeconds) * 10000) / 100 : null;

        document.getElementById('stat-uptime').textContent = uptimePercent !== null ? uptimePercent + '%' : 'n/a';
        document.getElementById('stat-online').textContent = formatDuration(onlineSeconds);
        document.getElementById('stat-offline').textContent = formatDuration(offlineSeconds);
        document.getElementById('stat-outages').textContent = String(outageCount);
        document.getElementById('stat-longest').textContent = outageCount > 0 ? formatDuration(longestOutageSeconds) : '-';

        var chart = document.getElementById('stat-chart');
        chart.innerHTML = '';
        if (segments.length === 0) {
            chart.classList.add('empty');
            chart.textContent = 'Keine Daten in diesem Zeitraum';
        } else {
            chart.classList.remove('empty');
            var rangeSeconds = Math.max((end - start) / 1000, 1);
            var minWeight = rangeSeconds * 0.003;
            segments.forEach(function (seg) {
                var el = document.createElement('div');
                el.className = 'seg ' + seg.status;
                el.style.flexGrow = String(Math.max(seg.seconds, minWeight));
                el.title = (seg.status === 'online' ? 'Online' : 'Offline') + ': '
                    + formatClock(seg.start) + ' bis ' + formatClock(seg.end)
                    + ' (' + formatDuration(seg.seconds) + ')';
                chart.appendChild(el);
            });
        }
    }

    var buttons = document.querySelectorAll('.period-btn');
    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            buttons.forEach(function (b) { b.classList.remove('active'); });
            btn.classList.add('active');
            renderPeriod(btn.getAttribute('data-period'));
        });
    });

    renderPeriod('all');
})();
</script>

</body>
</html>
