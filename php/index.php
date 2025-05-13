<?php
header('Content-Type: text/html; charset=utf-8');
require 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Ottieni dati per la mappa
$result_map = $conn->query("
    SELECT regione, AVG(affluenza) as affluenza, SUM(voti) as voti 
    FROM risultati 
    GROUP BY regione
");

// Ottieni dati per i partiti nazionali
$result_partiti = $conn->query("
    SELECT partito, SUM(voti) as totale 
    FROM risultati 
    GROUP BY partito 
    ORDER BY totale DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Analisi Elettorale</title>
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
        .chart-container { padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 6px rgba(0,0,0,0.1); }
        .region-selector { 
            grid-column: 1 / -1; 
            padding: 10px; 
            margin-bottom: 20px;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <select class="region-selector" onchange="updateChart(this.value)">
        <option value="">Tutte le regioni</option>
        <?php while($row = $conn->query("SELECT DISTINCT regione FROM risultati")->fetch_assoc()): ?>
            <option value="<?= htmlspecialchars($row['regione']) ?>"><?= $row['regione'] ?></option>
        <?php endwhile; ?>
    </select>

    <div id="container">
        <div id="map"></div>
        <div class="chart-container">
            <canvas id="partyChart"></canvas>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dati mappa
        const regionData = {
            <?php while($row = $result_map->fetch_assoc()): ?>
                "<?= $row['regione'] ?>": {
                    affluenza: <?= $row['affluenza'] ?>,
                    voti: <?= $row['voti'] ?>
                },
            <?php endwhile; ?>
        };

        // Configurazione mappa Italia
        const map = L.map('map').setView([41.8719, 12.5674], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

        // Aggiungi regioni colorate
        fetch('https://raw.githubusercontent.com/italia/anagrafica-istituzionale/main/risorse/regioni.geojson')
            .then(response => response.json())
            .then(data => {
                L.geoJSON(data, {
                    style: feature => ({
                        fillColor: getColor(regionData[feature.properties.nome].affluenza),
                        weight: 1,
                        opacity: 1,
                        color: 'white',
                        fillOpacity: 0.7
                    }),
                    onEachFeature: (feature, layer) => {
                        const info = regionData[feature.properties.nome];
                        layer.bindPopup(`
                            <b>${feature.properties.nome}</b><br>
                            Affluenza: ${info.affluenza.toFixed(1)}%<br>
                            Voti totali: ${info.voti.toLocaleString()}
                        `);
                    }
                }).addTo(map);
            });

        function getColor(affluenza) {
            return affluenza > 70 ? '#2c7bb6' :
                   affluenza > 60 ? '#abd9e9' :
                   affluenza > 50 ? '#ffffbf' :
                   affluenza > 40 ? '#fdae61' :
                                    '#d7191c';
        }

        // Configurazione grafico a barre
        const ctx = document.getElementById('partyChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php 
                    while($row = $result_partiti->fetch_assoc()) {
                        echo "'".$row['partito']."',";
                    }
                ?>],
                datasets: [{
                    label: 'Voti per Partito',
                    data: [<?php 
                        $result_partiti->data_seek(0);
                        while($row = $result_partiti->fetch_assoc()) {
                            echo $row['totale'].",";
                        }
                    ?>],
                    backgroundColor: [
                        '#1f77b4', '#ff7f0e', '#2ca02c', '#d62728', 
                        '#9467bd', '#8c564b', '#e377c2', '#7f7f7f',
                        '#bcbd22', '#17becf'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    title: { 
                        display: true,
                        text: 'Top 10 Partiti per Voti Totali'
                    }
                }
            }
        });

        // Funzione aggiornamento grafico
        function updateChart(region) {
            fetch(`api/risultati.php?region=${encodeURIComponent(region)}`)
                .then(response => response.json())
                .then(data => {
                    chart.data.labels = data.map(d => d.partito);
                    chart.data.datasets[0].data = data.map(d => d.totale);
                    chart.update();
                });
        }
    </script>
</body>
</html>