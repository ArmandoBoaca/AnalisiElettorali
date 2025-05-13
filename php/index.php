<?php
// php/index.php
header('Content-Type: text/html; charset=utf-8');
require 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Dati per la mappa (affluenza e risultati per regione)
$result_map = $conn->query("
    SELECT 
        REGIONE,
        SUM(ELETTORI) AS elettori,
        SUM(VOTANTI) AS votanti,
        SUM(VOTI_SI) AS si,
        SUM(VOTI_NO) AS no,
        (SUM(VOTANTI)/SUM(ELETTORI))*100 AS affluenza
    FROM `12062022_referendumitalia__scrutini_quesito1`
    GROUP BY REGIONE
");

// Dati nazionali aggregati
$result_nazionale = $conn->query("
    SELECT 
        SUM(VOTI_SI) AS totale_si,
        SUM(VOTI_NO) AS totale_no,
        SUM(VOTI_SI + VOTI_NO) AS totale_validi,
        (SUM(VOTI_SI)/SUM(VOTI_SI + VOTI_NO))*100 AS perc_si,
        (SUM(VOTI_NO)/SUM(VOTI_SI + VOTI_NO))*100 AS perc_no
    FROM `12062022_referendumitalia__scrutini_quesito1`
");
$nazionale = $result_nazionale->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Referendum 2022 - Analisi Risultati</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <style>
        body { margin: 0; padding: 20px; font-family: Arial, sans-serif; }
        #container { 
            display: grid; 
            grid-template-columns: 1fr 1fr; 
            gap: 20px; 
            max-width: 1200px; 
            margin: 0 auto;
        }
        #map { height: 600px; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.3); }
        .stats-box { 
            padding: 20px; 
            background: #f5f5f5; 
            border-radius: 8px; 
            margin-bottom: 20px;
        }
        .chart-container { 
            padding: 15px; 
            background: #fff; 
            border-radius: 8px; 
            box-shadow: 0 2px 6px rgba(0,0,0,0.1); 
        }
    </style>
</head>
<body>
    <div class="stats-box">
        <h2>Risultati Nazionali</h2>
        <p>Affluenza totale: <?= number_format($nazionale['affluenza'] ?? 0, 2) ?>%</p>
        <p>Voti validi: <?= number_format($nazionale['totale_validi']) ?></p>
        <p>SI: <?= number_format($nazionale['totale_si']) ?> (<?= number_format($nazionale['perc_si'], 2) ?>%)</p>
        <p>NO: <?= number_format($nazionale['totale_no']) ?> (<?= number_format($nazionale['perc_no'], 2) ?>%)</p>
    </div>

    <div id="container">
        <div id="map"></div>
        <div class="chart-container">
            <canvas id="referendumChart"></canvas>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dati mappa
        const regionData = {
            <?php while($row = $result_map->fetch_assoc()): ?>
                "<?= $row['REGIONE'] ?>": {
                    affluenza: <?= $row['affluenza'] ?>,
                    si: <?= $row['si'] ?>,
                    no: <?= $row['no'] ?>,
                    totale: <?= $row['si'] + $row['no'] ?>
                },
            <?php endwhile; ?>
        };

        // Mappa Italia
        const map = L.map('map').setView([41.8719, 12.5674], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // Aggiungi regioni
       