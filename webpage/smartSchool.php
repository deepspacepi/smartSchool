<?php
$ss1TempData = [];
$ss1HumData = [];
$ss1LumData = [];
$ss1TimeData = [];
$ss1CurrentTemp = 18.7;
$ss1CurrentHum = 54;
$ss1CurrentLum = 0;
$ss1Status = "Δροσερά";
$debugSs1Rows = [];

// Generate mock data points (every 10 mins for 1 day) for fallbacks
$dummyTemp = [];
$dummyHum = [];
$dummyLum = [];
$dummyTime = [];
$now = time();
$startTime = $now - (24 * 60 * 60);
$startTime = floor($startTime / 600) * 600; // align to nearest 10 min

for ($t = $startTime; $t <= $now; $t += 600) {
    $h = (int)date('H', $t);
    $m = (int)date('i', $t);
    $hourFloat = $h + ($m / 60);
    $dummyTime[] = date('d/m H:i', $t);
    // Ομαλότερη καμπύλη θερμοκρασίας: min ~20C, max ~24C
    $dummyTemp[] = round(22 + 2 * sin(($hourFloat - 8) * M_PI / 12), 1);
    // Ομαλότερη καμπύλη υγρασίας: max ~55%, min ~45%
    $dummyHum[] = round(50 - 5 * sin(($hourFloat - 8) * M_PI / 12));
    // Ομαλότερη καμπύλη φωτεινότητας: μέγιστο ~400
    $lum = ($hourFloat >= 6 && $hourFloat <= 19) ? round(400 * sin(($hourFloat - 6) * M_PI / 13)) : 0;
    $dummyLum[] = $lum;
}

try {
    $db = new PDO('sqlite:' . __DIR__ . '/smartSchool.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all packets from the last 3 days for ss-1, ordered chronologically
    $stmt = $db->query("
        SELECT id, device_id, packet, received_at 
        FROM packets 
        WHERE device_id = 'ss-1' 
          AND received_at >= datetime('now', '-3 days')
        ORDER BY id ASC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $debugSs1Rows[] = $row;
        
        $packet = json_decode($row['packet'], true);
        
        $devId = $row['device_id'] ?? '';
        if (empty($devId) && isset($packet['end_device_ids']['device_id'])) {
            $devId = $packet['end_device_ids']['device_id'];
        }
        
        if ($devId !== 'ss-1') {
            continue; // Only extract data if it matches the SS-1 sensor
        }

        
        $temp = null;
        if (isset($packet['uplink_message']['decoded_payload']['temperature'])) {
            $temp = $packet['uplink_message']['decoded_payload']['temperature'];
        } elseif (isset($packet['uplink_message']['decoded_payload']['temp'])) {
            $temp = $packet['uplink_message']['decoded_payload']['temp'];
        } elseif (isset($packet['temperature'])) {
            $temp = $packet['temperature'];
        } elseif (isset($packet['temp'])) {
            $temp = $packet['temp'];
        }

        if (is_numeric($temp)) {
            $ss1TempData[] = (float) $temp;
            $timestampStr = !empty($row['received_at']) ? $row['received_at'] . ' UTC' : 'now';
            $ss1TimeData[] = date('d/m H:i', strtotime($timestampStr));
        }

        $hum = null;
        if (isset($packet['uplink_message']['decoded_payload']['humidity'])) {
            $hum = $packet['uplink_message']['decoded_payload']['humidity'];
        } elseif (isset($packet['uplink_message']['decoded_payload']['hum'])) {
            $hum = $packet['uplink_message']['decoded_payload']['hum'];
        } elseif (isset($packet['humidity'])) {
            $hum = $packet['humidity'];
        } elseif (isset($packet['hum'])) {
            $hum = $packet['hum'];
        }

        if (is_numeric($hum)) {
            $ss1HumData[] = (float) $hum;
        }

        $lum = null;
        if (isset($packet['uplink_message']['decoded_payload']['luminosity'])) {
            $lum = $packet['uplink_message']['decoded_payload']['luminosity'];
        } elseif (isset($packet['uplink_message']['decoded_payload']['lux'])) {
            $lum = $packet['uplink_message']['decoded_payload']['lux'];
        } elseif (isset($packet['uplink_message']['decoded_payload']['light'])) {
            $lum = $packet['uplink_message']['decoded_payload']['light'];
        } elseif (isset($packet['luminosity'])) {
            $lum = $packet['luminosity'];
        } elseif (isset($packet['lux'])) {
            $lum = $packet['lux'];
        } elseif (isset($packet['light'])) {
            $lum = $packet['light'];
        }

        if (is_numeric($lum)) {
            $ss1LumData[] = (float) $lum;
        }
    }

    if (count($ss1TempData) > 1) {
        $ss1CurrentTemp = end($ss1TempData);
        if ($ss1CurrentTemp < 20) {
            $ss1Status = "Δροσερά";
        } elseif ($ss1CurrentTemp > 24) {
            $ss1Status = "Ζεστά";
        } elseif ($ss1CurrentTemp > 22.5) {
            $ss1Status = "Ελαφρώς Ζεστά";
        } else {
            $ss1Status = "Άνετα";
        }
    } else {
        // Fallback to dummy data if DB has no valid temperatures yet
        $ss1TempData = $dummyTemp;
        $ss1TimeData = $dummyTime;
        $ss1CurrentTemp = end($ss1TempData);
        $ss1Status = "Δροσερά";
    }

    if (count($ss1HumData) > 1) {
        $ss1CurrentHum = end($ss1HumData);
    } else {
        $ss1HumData = $dummyHum;
        $ss1CurrentHum = end($ss1HumData);
    }

    if (count($ss1LumData) > 1) {
        $ss1CurrentLum = end($ss1LumData);
    } else {
        $ss1LumData = $dummyLum;
        $ss1CurrentLum = end($ss1LumData);
    }
} catch (Exception $e) {
    // If database doesn't exist yet, fallback
    $ss1TempData = $dummyTemp;
    $ss1HumData  = $dummyHum;
    $ss1LumData  = $dummyLum;
    $ss1TimeData = $dummyTime;
    $ss1CurrentTemp = end($ss1TempData);
    $ss1CurrentHum  = end($ss1HumData);
    $ss1CurrentLum  = end($ss1LumData);
    $ss1Status = "Δροσερά";
}

// Generate random data for the other rooms based on SS-1 (ss-1)
$otherRooms = ['SS-2', 'SS-3', 'SS-4', 'SS-5', 'SS-6', 'SS-7', 'SS-8'];
$randomData = [];
foreach ($otherRooms as $room) {
    $tempData = [];
    $humData = [];
    $lumData = [];
    $timeData = $ss1TimeData;
    
    // Add a slight fixed offset per room, plus a tiny bit of random noise per reading
    $roomTempOffset = (lcg_value() * 2) - 1; // -1 to +1 degrees
    $roomHumOffset = rand(-5, 5);
    $roomLumOffset = rand(-20, 20);

    foreach ($ss1TempData as $val) {
        $tempData[] = round($val + $roomTempOffset + (lcg_value() * 0.4 - 0.2), 1);
    }
    foreach ($ss1HumData as $val) {
        $humData[] = max(0, min(100, round($val + $roomHumOffset + rand(-1, 1))));
    }
    foreach ($ss1LumData as $val) {
        $lumData[] = max(0, round($val + $roomLumOffset + rand(-5, 5)));
    }

    $currTemp = empty($tempData) ? end($dummyTemp) : end($tempData);
    $currHum = empty($humData) ? end($dummyHum) : end($humData);
    $currLum = empty($lumData) ? end($dummyLum) : end($lumData);

    $status = "Άνετα";
    if ($currTemp < 20) $status = "Δροσερά";
    elseif ($currTemp > 24) $status = "Ζεστά";
    elseif ($currTemp > 22.5) $status = "Ελαφρώς Ζεστά";

    $randomData[$room] = [
        'tempData' => $tempData,
        'humData' => $humData,
        'lumData' => $lumData,
        'timeData' => $timeData,
        'currTemp' => $currTemp,
        'currHum' => $currHum,
        'currLum' => $currLum,
        'status' => $status
    ];
}
?>
<!DOCTYPE html>
<html lang="el">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Χάρτης Θερμοκρασίας Σχολικών Αιθουσών</title>
  <style>
    * {
      box-sizing: border-box;
    }

    html {
      background: #dbeafe;
    }

    body {
      margin: 0 auto;
      width: 80%;
      min-height: 100vh;
      font-family: Arial, Helvetica, sans-serif;
      background: #f3f6fb;
      color: #1f2937;
      box-shadow: 0 0 20px rgba(15, 23, 42, 0.05);
    }

    .page {
      display: grid;
      grid-template-columns: 100px 4fr 5fr;
      gap: 24px;
      padding: 24px;
      min-height: calc(100vh - 86px);
    }

    .panel {
      background: #ffffff;
      border: 1px solid #dbe3ef;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
      padding: 20px;
      min-width: 0;
    }

    .chart-panel {
      display: flex;
      flex-direction: column;
    }

    .map-panel {
      overflow-x: auto;
    }

    .map-panel h2,
    .chart-panel h2 {
      margin: 0 0 16px;
      font-size: 24px;
    }

    .school-map {
      position: relative;
      width: 100%;
      min-width: 520px;
      height: 850px;
      background: #eef4fb;
      border: 4px solid #334155;
      border-radius: 14px;
      overflow: hidden;
    }

    .corridor {
      position: absolute;
      background: #d7dde8;
      border: 2px dashed #94a3b8;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      font-weight: bold;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }

    .corridor.main {
      left: 40px;
      right: 40px;
      top: 395px;
      height: 60px;
    }

    .corridor.vertical {
      top: 40px;
      bottom: 40px;
      left: calc(50% - 30px);
      width: 60px;
    }

    .classroom {
      position: absolute;
      border: 3px solid #475569;
      border-radius: 12px;
      background: #cbd5e1;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      transition: background 180ms ease, transform 180ms ease, box-shadow 180ms ease;
      z-index: 2;
    }

    .classroom.active {
      cursor: pointer;
      background: #86efac;
    }

    .classroom.active:hover {
      background: #60a5fa;
      transform: translateY(-3px);
      box-shadow: 0 12px 22px rgba(96, 165, 250, 0.35);
    }

    .classroom.selected {
      background: #3b82f6 !important;
      transform: translateY(-3px);
      box-shadow: 0 12px 22px rgba(59, 130, 246, 0.45);
      border-color: #1e3a8a;
    }

    .classroom.selected .room-name,
    .classroom.selected .room-meta {
      color: #ffffff !important;
    }

    .classroom .room-name {
      font-size: 18px;
      font-weight: 700;
      margin-bottom: 6px;
      color: #1f2937;
      transition: color 180ms ease;
    }

    .classroom .room-meta {
      font-size: 13px;
      color: #475569;
      transition: color 180ms ease;
    }

    .room-ss-1 { left: 40px; top: 40px; width: 190px; height: 150px; }
    .room-ss-2 { right: 40px; top: 40px; width: 190px; height: 150px; }
    .room-ss-3 { left: 40px; top: 210px; width: 190px; height: 150px; }
    .room-ss-4 { right: 40px; top: 210px; width: 190px; height: 150px; }

    .room-ss-5 { left: 40px; top: 490px; width: 190px; height: 150px; }
    .room-ss-6 { right: 40px; top: 490px; width: 190px; height: 150px; }
    .room-ss-7 { left: 40px; top: 660px; width: 190px; height: 150px; }
    .room-ss-8 { right: 40px; top: 660px; width: 190px; height: 150px; }

    .entrance {
      position: absolute;
      left: calc(50% - 80px);
      bottom: -4px;
      width: 160px;
      height: 36px;
      background: #ffffff;
      border: 4px solid #334155;
      border-bottom: 0;
      border-radius: 12px 12px 0 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      z-index: 3;
    }

    .chart-card {
      border-radius: 14px;
      background: #f8fafc;
      border: 1px solid #dbeafe;
      padding: 16px;
      flex: 1;
      display: flex;
      flex-direction: row;
      align-items: stretch;
      gap: 16px;
      justify-content: center;
    }

    .chart-title-vertical {
      writing-mode: vertical-rl;
      transform: rotate(180deg);
      text-align: center;
      font-size: 18px;
      font-weight: bold;
      color: #334155;
      margin: 0;
    }

    .chart-content {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      min-width: 0;
    }

    svg {
      width: 100%;
      flex: 1;
      min-height: 0;
      display: block;
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #dbeafe;
    }

    .axis {
      stroke: #94a3b8;
      stroke-width: 1;
    }

    .grid-line {
      stroke: #dbeafe;
      stroke-width: 1;
    }

    .line {
      fill: none;
      stroke-width: 4;
      stroke-linecap: round;
      stroke-linejoin: round;
    }

    .dot {
      stroke: none;
      cursor: crosshair;
      transition: fill 0.1s ease;
    }

    /* Χρώματα Γραφημάτων (Γραμμές & Bullets) */
    /* Θερμοκρασία (Κόκκινο) */
    .line.temp { stroke: #ef4444; }
    .dot.temp { fill: #ef4444; }
    .dot.temp:hover { fill: #b91c1c; }

    /* Υγρασία (Μπλε) */
    .line.hum { stroke: #3b82f6; }
    .dot.hum { fill: #3b82f6; }
    .dot.hum:hover { fill: #1d4ed8; }

    /* Φωτεινότητα (Πορτοκαλί) */
    .line.lum { stroke: #f59e0b; }
    .dot.lum { fill: #f59e0b; }
    .dot.lum:hover { fill: #b45309; }

    .label {
      font-size: 14px;
      font-weight: 500;
      fill: #64748b;
    }

    .legend {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 14px;
      color: #64748b;
      font-size: 14px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .legend-color {
      width: 14px;
      height: 14px;
      border-radius: 4px;
      border: 1px solid #94a3b8;
    }

    .normal { background: #cbd5e1; }
    .active-room { background: #86efac; }
    .selected-legend { background: #3b82f6; }

    .custom-tooltip {
      position: absolute;
      background: rgba(15, 23, 42, 0.85);
      color: #f8fafc;
      padding: 6px 10px;
      border-radius: 6px;
      font-size: 12px;
      pointer-events: none;
      opacity: 0;
      transition: opacity 0.1s ease;
      z-index: 1000;
      white-space: nowrap;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .vertical-banner {
      background: #1e293b;
      border-radius: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08);
      width: 70px;
    }

    .vertical-banner-text {
      writing-mode: vertical-rl;
      transform: rotate(180deg);
      font-size: 26px;
      font-weight: bold;
      color: #ffffff;
      letter-spacing: 2px;
      white-space: nowrap;
    }

    @media (max-width: 1600px) {
      body {
        width: 100%;
      }
    }

    @media (max-width: 1100px) {

      .page {
        grid-template-columns: 1fr;
      }

      .vertical-banner {
        width: auto;
        padding: 20px;
      }
      .vertical-banner-text {
        writing-mode: horizontal-tb;
        transform: none;
      }

      .map-panel {
        overflow-x: auto;
      }
    }
  </style>
</head>
<body>
  <div id="chartTooltip" class="custom-tooltip"></div>
  <main class="page">
    <aside class="vertical-banner">
      <div class="vertical-banner-text">Εσπερινό ΕΠΑ.Λ. Γρεβενών</div>
    </aside>
    <section class="panel map-panel">
      <h2>Διάγραμμα Σχολείου</h2>

      <div class="school-map" aria-label="Χάρτης κάτοψης σχολείου">
        <div class="corridor vertical"></div>
        <div class="corridor main"></div>

        <div class="classroom active room-ss-1" data-id="SS-1" data-room="Αίθουσα 11" data-temp="<?php echo htmlspecialchars($ss1CurrentTemp); ?>" data-hum="<?php echo htmlspecialchars($ss1CurrentHum); ?>" data-lum="<?php echo htmlspecialchars($ss1CurrentLum); ?>" data-status="<?php echo htmlspecialchars($ss1Status); ?>">
          <div class="room-name">Αίθουσα 11</div>
        </div>

        <div class="classroom room-ss-2" data-id="SS-2" data-room="Αίθουσα 12" data-temp="<?php echo htmlspecialchars($randomData['SS-2']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-2']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-2']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-2']['status']); ?>">
          <div class="room-name">Αίθουσα 12</div>
        </div>

        <div class="classroom room-ss-3" data-id="SS-3" data-room="Αίθουσα 13" data-temp="<?php echo htmlspecialchars($randomData['SS-3']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-3']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-3']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-3']['status']); ?>">
          <div class="room-name">Αίθουσα 13</div>
        </div>

        <div class="classroom active room-ss-4" data-id="SS-4" data-room="Αίθουσα 14" data-temp="<?php echo htmlspecialchars($randomData['SS-4']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-4']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-4']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-4']['status']); ?>">
          <div class="room-name">Αίθουσα 14</div>
        </div>

        <div class="classroom room-ss-5" data-id="SS-5" data-room="Αίθουσα 15" data-temp="<?php echo htmlspecialchars($randomData['SS-5']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-5']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-5']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-5']['status']); ?>">
          <div class="room-name">Αίθουσα 15</div>
        </div>

        <div class="classroom active room-ss-6" data-id="SS-6" data-room="Αίθουσα 16" data-temp="<?php echo htmlspecialchars($randomData['SS-6']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-6']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-6']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-6']['status']); ?>">
          <div class="room-name">Αίθουσα 16</div>
        </div>

        <div class="classroom room-ss-7" data-id="SS-7" data-room="Αίθουσα 17" data-temp="<?php echo htmlspecialchars($randomData['SS-7']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-7']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-7']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-7']['status']); ?>">
          <div class="room-name">Αίθουσα 17</div>
        </div>

        <div class="classroom room-ss-8" data-id="SS-8" data-room="Αίθουσα 18" data-temp="<?php echo htmlspecialchars($randomData['SS-8']['currTemp']); ?>" data-hum="<?php echo htmlspecialchars($randomData['SS-8']['currHum']); ?>" data-lum="<?php echo htmlspecialchars($randomData['SS-8']['currLum']); ?>" data-status="<?php echo htmlspecialchars($randomData['SS-8']['status']); ?>">
          <div class="room-name">Αίθουσα 18</div>
        </div>

        <div class="entrance">Είσοδος</div>
      </div>

      <div class="legend">
        <div class="legend-item"><span class="legend-color normal"></span> Αίθουσα</div>
        <div class="legend-item"><span class="legend-color active-room"></span> Ενεργή Αίθουσα</div>
        <div class="legend-item"><span class="legend-color selected-legend"></span> Επιλεγμένη Αίθουσα</div>
      </div>
    </section>

    <aside class="panel chart-panel">
      <h2 id="roomPanelTitle">Αίθουσα 11</h2>

      <div class="chart-card" style="margin-bottom: 16px;">
        <h3 class="chart-title-vertical">Θερμοκρασία (°C)</h3>
        <div class="chart-content">
          <svg viewBox="0 0 1000 400" role="img" aria-label="Γράφημα θερμοκρασίας">
            <line class="grid-line" x1="40" y1="40" x2="980" y2="40" />
            <line class="grid-line" x1="40" y1="140" x2="980" y2="140" />
            <line class="grid-line" x1="40" y1="240" x2="980" y2="240" />
            <line class="grid-line" x1="40" y1="340" x2="980" y2="340" />

            <line class="axis" x1="40" y1="20" x2="40" y2="350" />
            <line class="axis" x1="40" y1="340" x2="980" y2="340" />

            <text id="tempY4" class="label" x="8" y="44">30°</text>
            <text id="tempY3" class="label" x="8" y="144">23°</text>
            <text id="tempY2" class="label" x="8" y="244">17°</text>
            <text id="tempY1" class="label" x="8" y="344">10°</text>

            <polyline id="tempLine" class="line" points="" />
            <g id="tempDots"></g>

            <g id="tempTimeLabels"></g>
          </svg>
        </div>
      </div>

      <div class="chart-card" style="margin-bottom: 16px;">
        <h3 class="chart-title-vertical">Υγρασία (%)</h3>
        <div class="chart-content">
          <svg viewBox="0 0 1000 400" role="img" aria-label="Γράφημα υγρασίας">
            <line class="grid-line" x1="40" y1="40" x2="980" y2="40" />
            <line class="grid-line" x1="40" y1="140" x2="980" y2="140" />
            <line class="grid-line" x1="40" y1="240" x2="980" y2="240" />
            <line class="grid-line" x1="40" y1="340" x2="980" y2="340" />

            <line class="axis" x1="40" y1="20" x2="40" y2="350" />
            <line class="axis" x1="40" y1="340" x2="980" y2="340" />

            <text class="label" x="8" y="44">100%</text>
            <text class="label" x="8" y="144">75%</text>
            <text class="label" x="8" y="244">50%</text>
            <text class="label" x="8" y="344">25%</text>

            <polyline id="humLine" class="line" points="" />

            <g id="humDots"></g>

            <g id="humTimeLabels"></g>
          </svg>
        </div>
      </div>

      <div class="chart-card" style="margin-bottom: 16px;">
        <h3 class="chart-title-vertical">Φωτεινότητα (lx)</h3>
        <div class="chart-content">
          <svg viewBox="0 0 1000 400" role="img" aria-label="Γράφημα φωτεινότητας">
            <line class="grid-line" x1="40" y1="40" x2="980" y2="40" />
            <line class="grid-line" x1="40" y1="140" x2="980" y2="140" />
            <line class="grid-line" x1="40" y1="240" x2="980" y2="240" />
            <line class="grid-line" x1="40" y1="340" x2="980" y2="340" />

            <line class="axis" x1="40" y1="20" x2="40" y2="350" />
            <line class="axis" x1="40" y1="340" x2="980" y2="340" />

            <text class="label" x="8" y="44">1000</text>
            <text class="label" x="8" y="144">750</text>
            <text class="label" x="8" y="244">500</text>
            <text class="label" x="8" y="344">250</text>

            <polyline id="lumLine" class="line" points="" />

            <g id="lumDots"></g>

            <g id="lumTimeLabels"></g>
          </svg>
        </div>
      </div>
    </aside>
	  <hr>

<h3>Τελευταία Πακέτα JSON</h3>

<div id="latestPackets" style="
    max-height:400px;
    overflow-y:auto;
    font-size:12px;
">
<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/smartSchool.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->query("
        SELECT
            id,
            received_at,
            device_id,
            packet
        FROM packets
        ORDER BY id DESC
        LIMIT 20
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {

        echo '<div style="
            margin-bottom:10px;
            padding:8px;
            background:#fff;
            border:1px solid #ddd;
            border-radius:6px;
        ">';

        echo '<strong>#' . htmlspecialchars($row['id']) . '</strong><br>';
        echo '<strong>Ώρα:</strong> ' . htmlspecialchars($row['received_at']) . '<br>';
        echo '<strong>Συσκευή:</strong> ' . htmlspecialchars($row['device_id']) . '<br>';

        echo '<pre style="
            white-space:pre-wrap;
            margin-top:5px;
            font-size:11px;
            max-height:120px;
            overflow:auto;
        ">';

        echo htmlspecialchars(
            json_encode(
                json_decode($row['packet'], true),
                JSON_PRETTY_PRINT
            )
        );

        echo '</pre>';

        echo '</div>';
    }
} catch (Exception $e) {
    echo '<div style="color: red; padding: 10px;">Σφάλμα κατά τη φόρτωση δεδομένων πακέτων.</div>';
}
?>
</div>

<hr>
<h3>Αποσφαλμάτωση: Εξαγόμενα Δεδομένα SS-1 (ss-1)</h3>
<div style="max-height:400px; overflow-y:auto; font-size:12px; background:#fff; padding:8px; border:1px solid #ddd; border-radius:6px; margin-bottom: 20px;">
    <strong>Συνολικές γραμμές βάσης που διαβάστηκαν για το ss-1:</strong> <?php echo count($debugSs1Rows); ?><br><br>
    <strong>Αναλυμένες Θερμοκρασίες:</strong><br>
    <pre style="white-space:pre-wrap; margin-top:5px;"><?php echo htmlspecialchars(json_encode($ss1TempData)); ?></pre>
    <strong>Αναλυμένες Υγρασίες:</strong><br>
    <pre style="white-space:pre-wrap; margin-top:5px;"><?php echo htmlspecialchars(json_encode($ss1HumData)); ?></pre>
    <strong>Αναλυμένες Φωτεινότητες:</strong><br>
    <pre style="white-space:pre-wrap; margin-top:5px;"><?php echo htmlspecialchars(json_encode($ss1LumData)); ?></pre>
    <strong>Αναλυμένες Ώρες:</strong><br>
    <pre style="white-space:pre-wrap; margin-top:5px;"><?php echo htmlspecialchars(json_encode($ss1TimeData)); ?></pre>
    <br><strong>Ανεπεξέργαστες Γραμμές Βάσης (Δείγμα των τελευταίων 5 στοιχείων):</strong><br>
    <pre style="white-space:pre-wrap; margin-top:5px;"><?php echo htmlspecialchars(json_encode(array_slice($debugSs1Rows, -5), JSON_PRETTY_PRINT)); ?></pre>
</div>

</main>

  <script>
    const rooms = document.querySelectorAll('.classroom.active');
    const tempLine = document.getElementById('tempLine');
    const tempDots = document.getElementById('tempDots');
    const humLine = document.getElementById('humLine');
    const humDots = document.getElementById('humDots');
    const lumLine = document.getElementById('lumLine');
    const lumDots = document.getElementById('lumDots');
    const tempTimeLabels = document.getElementById('tempTimeLabels');
    const humTimeLabels = document.getElementById('humTimeLabels');
    const lumTimeLabels = document.getElementById('lumTimeLabels');
    const roomPanelTitle = document.getElementById('roomPanelTitle');

    const chartDataTemp = {
      'SS-1': <?php echo json_encode($ss1TempData); ?>,
      'SS-2': <?php echo json_encode($randomData['SS-2']['tempData']); ?>,
      'SS-3': <?php echo json_encode($randomData['SS-3']['tempData']); ?>,
      'SS-4': <?php echo json_encode($randomData['SS-4']['tempData']); ?>,
      'SS-5': <?php echo json_encode($randomData['SS-5']['tempData']); ?>,
      'SS-6': <?php echo json_encode($randomData['SS-6']['tempData']); ?>,
      'SS-7': <?php echo json_encode($randomData['SS-7']['tempData']); ?>,
      'SS-8': <?php echo json_encode($randomData['SS-8']['tempData']); ?>
    };

    const chartDataHum = {
      'SS-1': <?php echo json_encode($ss1HumData); ?>,
      'SS-2': <?php echo json_encode($randomData['SS-2']['humData']); ?>,
      'SS-3': <?php echo json_encode($randomData['SS-3']['humData']); ?>,
      'SS-4': <?php echo json_encode($randomData['SS-4']['humData']); ?>,
      'SS-5': <?php echo json_encode($randomData['SS-5']['humData']); ?>,
      'SS-6': <?php echo json_encode($randomData['SS-6']['humData']); ?>,
      'SS-7': <?php echo json_encode($randomData['SS-7']['humData']); ?>,
      'SS-8': <?php echo json_encode($randomData['SS-8']['humData']); ?>
    };

    const chartDataLum = {
      'SS-1': <?php echo json_encode($ss1LumData); ?>,
      'SS-2': <?php echo json_encode($randomData['SS-2']['lumData']); ?>,
      'SS-3': <?php echo json_encode($randomData['SS-3']['lumData']); ?>,
      'SS-4': <?php echo json_encode($randomData['SS-4']['lumData']); ?>,
      'SS-5': <?php echo json_encode($randomData['SS-5']['lumData']); ?>,
      'SS-6': <?php echo json_encode($randomData['SS-6']['lumData']); ?>,
      'SS-7': <?php echo json_encode($randomData['SS-7']['lumData']); ?>,
      'SS-8': <?php echo json_encode($randomData['SS-8']['lumData']); ?>
    };

    const chartDataTime = {
      'SS-1': <?php echo json_encode($ss1TimeData); ?>,
      'SS-2': <?php echo json_encode($randomData['SS-2']['timeData']); ?>,
      'SS-3': <?php echo json_encode($randomData['SS-3']['timeData']); ?>,
      'SS-4': <?php echo json_encode($randomData['SS-4']['timeData']); ?>,
      'SS-5': <?php echo json_encode($randomData['SS-5']['timeData']); ?>,
      'SS-6': <?php echo json_encode($randomData['SS-6']['timeData']); ?>,
      'SS-7': <?php echo json_encode($randomData['SS-7']['timeData']); ?>,
      'SS-8': <?php echo json_encode($randomData['SS-8']['timeData']); ?>
    };

    function valueToY(value, min, max) {
      const chartTop = 40;
      const chartBottom = 340;
      const ratio = Math.max(0, Math.min(1, (value - min) / (max - min)));
      return chartBottom - ratio * (chartBottom - chartTop);
    }

    function updateChart(roomId, roomName, temp, hum, lum, status) {
      const tempValues = chartDataTemp[roomId];
      const humValues = chartDataHum[roomId];
      const lumValues = chartDataLum[roomId];
      const timeValues = chartDataTime[roomId];
      
      const chartLeft = 40;
      const chartRight = 980;
      
      function renderGraph(values, lineEl, dotsEl, min, max, times, themeClass) {
        let points = '';
        let dotsHtml = '';

        if (values && values.length > 0) {
          const numPoints = values.length;

          points = values.map((value, index) => {
            const x = numPoints === 1 ? (chartLeft + chartRight) / 2 : chartLeft + (chartRight - chartLeft) * (index / (numPoints - 1));
            return `${x},${valueToY(value, min, max).toFixed(1)}`;
          }).join(' ');

          // Always draw dots so exact values can be inspected
          dotsHtml = values.map((value, index) => {
            const x = numPoints === 1 ? (chartLeft + chartRight) / 2 : chartLeft + (chartRight - chartLeft) * (index / (numPoints - 1));
            const y = valueToY(value, min, max).toFixed(1);
            const timeStr = (times && times[index]) ? ` στις ${times[index]}` : '';
            const r = numPoints > 200 ? 1.5 : (numPoints > 50 ? 2.5 : 5); // Smaller dots if there are many points
              return `<circle class="dot ${themeClass}" cx="${x}" cy="${y}" r="${r}" data-info="${value}${timeStr}"></circle>`;
          }).join('');
        }

        lineEl.setAttribute('points', points);
        lineEl.setAttribute('class', `line ${themeClass}`);
        dotsEl.innerHTML = dotsHtml;
      }

      // Υπολογισμός δυναμικού εύρους για τη θερμοκρασία (τουλάχιστον 10 έως 30)
      let tMin = 10;
      let tMax = 30;
      if (tempValues && tempValues.length > 0) {
        const dMin = Math.min(...tempValues);
        const dMax = Math.max(...tempValues);
        if (dMin < tMin) tMin = Math.floor(dMin);
        if (dMax > tMax) tMax = Math.ceil(dMax);
      }
      // Εξασφαλίζουμε ότι το εύρος (span) διαιρείται ακριβώς με το 3 για ομοιόμορφες ετικέτες στον άξονα Y
      let span = tMax - tMin;
      if (span % 3 !== 0) {
        span = Math.ceil(span / 3) * 3;
        tMax = tMin + span;
      }
      document.getElementById('tempY4').textContent = tMax + '°';
      document.getElementById('tempY3').textContent = (tMax - span / 3) + '°';
      document.getElementById('tempY2').textContent = (tMin + span / 3) + '°';
      document.getElementById('tempY1').textContent = tMin + '°';

      renderGraph(tempValues, tempLine, tempDots, tMin, tMax, timeValues, 'temp');
      renderGraph(humValues, humLine, humDots, 0, 100, timeValues, 'hum');
      renderGraph(lumValues, lumLine, lumDots, 0, 1000, timeValues, 'lum');

      function renderXLabels(times, container) {
        if (!times || times.length === 0) {
          container.innerHTML = '';
          return;
        }
        let html = '';
        const numPoints = times.length;
        let lastPrintedHourGroup = -1;
        let lastPrintedDate = '';
        let lastLabelSpan = -1;
        for (let i = 0; i < numPoints; i++) {
          const t = times[i];
          if (t) {
            const parts = t.split(' ');
            if (parts.length === 2) {
              const datePart = parts[0];
              const timePart = parts[1];
              const hour = parseInt(timePart.split(':')[0], 10);
              const hourGroup = Math.floor(hour / 6); // Group into 4 labels a day (every 6 hours)
              
              if ((datePart !== lastPrintedDate || hourGroup !== lastPrintedHourGroup) && (lastLabelSpan === -1 || i - lastLabelSpan > Math.max(3, numPoints / 30))) {
                const x = numPoints === 1 ? (chartLeft + chartRight) / 2 : chartLeft + (chartRight - chartLeft) * (i / (numPoints - 1));
                html += `<line class="axis" x1="${x}" y1="340" x2="${x}" y2="345" />`;
                html += `<text class="label" x="${x}" y="360" text-anchor="middle" style="font-size: 11px;">${timePart}</text>`;
                if (datePart !== lastPrintedDate || hourGroup === 0) { // print date on first of day or first seen
                    html += `<text class="label" x="${x}" y="375" text-anchor="middle" style="font-size: 10px; fill: #94a3b8;">${datePart}</text>`;
                }
                lastPrintedDate = datePart;
                lastPrintedHourGroup = hourGroup;
                lastLabelSpan = i;
              }
            }
          }
        }
        container.innerHTML = html;
      }

      renderXLabels(timeValues, tempTimeLabels);
      renderXLabels(timeValues, humTimeLabels);
      renderXLabels(timeValues, lumTimeLabels);

      roomPanelTitle.textContent = roomName;
    }

    rooms.forEach((roomElement) => {
      roomElement.addEventListener('mouseenter', () => {
        rooms.forEach(r => r.classList.remove('selected'));
        roomElement.classList.add('selected');

        const roomId = roomElement.dataset.id;
        const roomName = roomElement.dataset.room;
        const temp = roomElement.dataset.temp;
        const hum = roomElement.dataset.hum;
        const lum = roomElement.dataset.lum;
        const status = roomElement.dataset.status;
        updateChart(roomId, roomName, temp, hum, lum, status);
      });
    });

    // Initialize the chart with the first room's data on load
    if (rooms.length > 0) {
      const firstRoom = rooms[0];
      firstRoom.classList.add('selected');
      updateChart(
        firstRoom.dataset.id,
        firstRoom.dataset.room, 
        firstRoom.dataset.temp, 
        firstRoom.dataset.hum, 
        firstRoom.dataset.lum, 
        firstRoom.dataset.status
      );
    }

    const tooltip = document.getElementById('chartTooltip');

    document.addEventListener('mouseover', (e) => {
      if (e.target && e.target.classList && e.target.classList.contains('dot')) {
        tooltip.innerHTML = e.target.getAttribute('data-info');
        tooltip.style.opacity = 1;
      }
    });

    document.addEventListener('mousemove', (e) => {
      if (e.target && e.target.classList && e.target.classList.contains('dot')) {
        tooltip.style.left = (e.pageX + 15) + 'px';
        tooltip.style.top = (e.pageY + 15) + 'px';
      }
    });

    document.addEventListener('mouseout', (e) => {
      if (e.target && e.target.classList && e.target.classList.contains('dot')) {
        tooltip.style.opacity = 0;
      }
    });
  </script>
</body>
</html>
