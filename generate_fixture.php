<?php
// ======================================================
// 2025 NCL & WEL SEASON SCHEDULER
// ======================================================

// 1. TEAM LISTS
$welTeams = [
    'Nibs Pacers','Ravens','Pack','Greyknights','Savio Queens',
    'Lady Bucks Shauri','Buru Buru Girls','Raila Academy','Lady Rebels',
    'Soweto Academy','Vikapu Academy','Nuru Basketball Academy',
    'Stingers','Lekina Cranes','Full40','Feba Academy'
];
$nclTeams = [
    'Nibs Panthers','Mambas','Neighbourhood','Rebels','Redhill Storms',
    'Vikapu Academy','Feba Genesis','Kayole South','Waylight Academy',
    'Lekina','Greyknights','Full40','Future Ballers','Blazers',
    'Soweto Academy','Spread Truth','Panthers','RRBA'
];
$allTeams = array_merge($welTeams, $nclTeams);

// 2. DATE UTILITIES
function datesOf($start, $end, $dayName) {
    $out = [];
    $period = new DatePeriod(
        new DateTime($start),
        new DateInterval('P1D'),
        (new DateTime($end))->modify('+1 day')
    );
    foreach ($period as $d) {
        if ($d->format('l') === $dayName) {
            $out[] = $d->format('Y-m-d');
        }
    }
    return $out;
}
$fridays    = datesOf('2025-07-05','2025-11-30','Friday');
$saturdays  = datesOf('2025-07-05','2025-11-30','Saturday');
$sundays    = datesOf('2025-07-05','2025-11-30','Sunday');
$wednesdays = datesOf('2025-07-05','2025-11-30','Wednesday');
$holiday    = '2025-10-10';  // Public Holiday

// 3. ROUND-ROBIN FIXTURE GENERATOR
function generateFixtures(array $teams, string $league) {
    $fx = [];
    $n = count($teams);
    for ($i = 0; $i < $n-1; $i++) {
        for ($j = $i+1; $j < $n; $j++) {
            $fx[] = [
                'league' => $league,
                'home'   => $teams[$i],
                'away'   => $teams[$j]
            ];
        }
    }
    return $fx;
}
$welFixtures = generateFixtures($welTeams, 'WEL');  //120
$nclFixtures = generateFixtures($nclTeams, 'NCL');  //153

// 4. FRIDAY-ELIGIBLE POOL FOR NCL (exclude restricted teams)
$ineligible = [
    'Vikapu Academy','Rebels','Waylight Academy',
    'Kayole South','Nibs Panthers','RRBA'
];
$friPool = array_filter($nclFixtures, function($g) use ($ineligible) {
    return !in_array($g['home'], $ineligible)
        && !in_array($g['away'], $ineligible);
});
shuffle($friPool);
// remove them from main NCL pool
foreach ($friPool as $g) {
    foreach ($nclFixtures as $i => $orig) {
        if ($orig === $g) { unset($nclFixtures[$i]); break; }
    }
}
$nclFixtures = array_values($nclFixtures);

// 5. SCHEDULING INITIALIZATION
$scheduled      = [];      // [date][time] = match
$gameNo         = 1;
$lastFriday     = [];      // teams played last Fri
$lastWeekPlayed = array_fill_keys($allTeams, 2);  // initial idle = week 2

// TIME SLOTS
$friSlots  = ['18:00','19:30'];
$satSlots  = ['10:00','11:30','13:00','14:30','16:00'];
$sunSlots  = ['10:00','11:30','13:00','14:30','16:00'];
$wedSlots  = ['18:00','19:30','21:00'];

// WEEKEND PATTERNS
$satPattern = ['WEL','NCL','WEL','NCL','WEL'];
$sunPattern = ['NCL','WEL','NCL','WEL','NCL'];

// HELPER: pick next fixture maximizing idle time
function pickBest(&$pool, &$usedTeams, $week, &$lastWeekPlayed) {
    $bestIdx   = null;
    $bestScore = -1;
    foreach ($pool as $i => $g) {
        if (in_array($g['home'], $usedTeams) 
         || in_array($g['away'], $usedTeams)) {
            continue;
        }
        $idle = min(
            $week - $lastWeekPlayed[$g['home']],
            $week - $lastWeekPlayed[$g['away']]
        );
        if ($idle > $bestScore) {
            $bestScore = $idle;
            $bestIdx   = $i;
        }
    }
    if ($bestIdx === null && count($pool) > 0) {
        $bestIdx = 0;
    }
    if ($bestIdx === null) {
        return null;
    }
    return array_splice($pool, $bestIdx, 1)[0];
}

// 6a. FRIDAY SLOTS (2 per week)
foreach ($fridays as $w => $date) {
    $week = $w + 3;
    $today = [];
    foreach ($friSlots as $t) {
        $match = pickBest($friPool, $lastFriday, $week, $lastWeekPlayed);
        if (!$match) break;
        $scheduled[$date][$t] = [
            'league' => 'NCL',
            'home'   => $match['home'],
            'away'   => $match['away'],
            'game'   => $gameNo++
        ];
        $today[] = $match['home'];
        $today[] = $match['away'];
        $lastWeekPlayed[$match['home']] = $week;
        $lastWeekPlayed[$match['away']] = $week;
    }
    $lastFriday = $today;
}

// 6b. WEEKEND SLOTS (5 Sat & 5 Sun per week)
foreach ($saturdays as $w => $satDate) {
    $week = $w + 3;
    $sunDate = $sundays[$w] ?? null;

    // ---- Saturday ----
    $usedSat = [];
    for ($i = 0; $i < 5; $i++) {
        $time = $satSlots[$i];
        $L    = $satPattern[$i];

        // pick from primary league pool
        if ($L === 'WEL') {
            $m = pickBest($welFixtures, $usedSat, $week, $lastWeekPlayed);
            if (!$m) {
                $m = pickBest($nclFixtures, $usedSat, $week, $lastWeekPlayed);
                $L = 'NCL';
            }
        } else {
            $m = pickBest($nclFixtures, $usedSat, $week, $lastWeekPlayed);
            if (!$m) {
                $m = pickBest($welFixtures, $usedSat, $week, $lastWeekPlayed);
                $L = 'WEL';
            }
        }

        if (!$m) continue;
        $scheduled[$satDate][$time] = [
            'league' => $L, 
            'home'   => $m['home'], 
            'away'   => $m['away'], 
            'game'   => $gameNo++
        ];
        $usedSat[] = $m['home'];
        $usedSat[] = $m['away'];
        $lastWeekPlayed[$m['home']] = $week;
        $lastWeekPlayed[$m['away']] = $week;
    }

    // ---- Holiday Oct 10 (treat as Saturday) ----
    if ($satDate === $holiday) {
        $usedH = [];
        for ($i = 0; $i < 5; $i++) {
            $time = $satSlots[$i];
            // fallback to largest pool
            $m = pickBest($welFixtures, $usedH, $week, $lastWeekPlayed)
              ?? pickBest($nclFixtures, $usedH, $week, $lastWeekPlayed);
            if (!$m) continue;
            $scheduled[$satDate][$time] = [
                'league' => $m['league'], 
                'home'   => $m['home'], 
                'away'   => $m['away'], 
                'game'   => $gameNo++
            ];
            $usedH[] = $m['home'];
            $usedH[] = $m['away'];
            $lastWeekPlayed[$m['home']] = $week;
            $lastWeekPlayed[$m['away']] = $week;
        }
    }

    // ---- Sunday ----
    if ($sunDate && $sunDate !== '2025-10-20') {
        $usedSun = [];
        for ($i = 0; $i < 5; $i++) {
            $time = $sunSlots[$i];
            $L    = $sunPattern[$i];

            if ($L === 'WEL') {
                $m = pickBest($welFixtures, $usedSun, $week, $lastWeekPlayed);
                if (!$m) {
                    $m = pickBest($nclFixtures, $usedSun, $week, $lastWeekPlayed);
                    $L = 'NCL';
                }
            } else {
                $m = pickBest($nclFixtures, $usedSun, $week, $lastWeekPlayed);
                if (!$m) {
                    $m = pickBest($welFixtures, $usedSun, $week, $lastWeekPlayed);
                    $L = 'WEL';
                }
            }

            if (!$m) continue;
            $scheduled[$sunDate][$time] = [
                'league' => $L, 
                'home'   => $m['home'], 
                'away'   => $m['away'], 
                'game'   => $gameNo++
            ];
            $usedSun[] = $m['home'];
            $usedSun[] = $m['away'];
            $lastWeekPlayed[$m['home']] = $week;
            $lastWeekPlayed[$m['away']] = $week;
        }
    }
}

// 6c. WEDNESDAY MAKE-UPS for any leftovers
foreach ($wednesdays as $wedDate) {
    $week = 22;
    $usedW = [];
    foreach ($wedSlots as $time) {
        if (empty($welFixtures) && empty($nclFixtures)) break 2;
        // pick pool with more remaining
        if (count($welFixtures) >= count($nclFixtures)) {
            $m = pickBest($welFixtures, $usedW, $week, $lastWeekPlayed);
            $L = 'WEL';
            if (!$m) {
                $m = pickBest($nclFixtures, $usedW, $week, $lastWeekPlayed);
                $L = 'NCL';
            }
        } else {
            $m = pickBest($nclFixtures, $usedW, $week, $lastWeekPlayed);
            $L = 'NCL';
            if (!$m) {
                $m = pickBest($welFixtures, $usedW, $week, $lastWeekPlayed);
                $L = 'WEL';
            }
        }
        if (!$m) continue;
        $scheduled[$wedDate][$time] = [
            'league' => $L,
            'home'   => $m['home'],
            'away'   => $m['away'],
            'game'   => $gameNo++
        ];
        $usedW[] = $m['home'];
        $usedW[] = $m['away'];
        $lastWeekPlayed[$m['home']] = $week;
        $lastWeekPlayed[$m['away']] = $week;
    }
}

// 7. FINAL COUNTS & UNSCHEDULED
$totalScheduled = array_sum(array_map('count', $scheduled));
$unscheduledWEL = $welFixtures;
$unscheduledNCL = $nclFixtures;

// 8. TEAM FILTER
$filter     = trim($_GET['team'] ?? '');
$filtered   = [];
if ($filter) {
    foreach ($scheduled as $date => $slots) {
        foreach ($slots as $time => $m) {
            if (strcasecmp($m['home'], $filter)===0 
             || strcasecmp($m['away'], $filter)===0) {
                $opp = strcasecmp($m['home'], $filter)===0 
                     ? $m['away'] : $m['home'];
                $filtered[] = [
                    'date' => $date,
                    'time' => $time,
                    'league'=> $m['league'],
                    'homeAway'=> strcasecmp($m['home'],$filter)===0 ? 'Home':'Away',
                    'opponent'=> $opp,
                    'game'=> $m['game']
                ];
            }
        }
    }
    usort($filtered, fn($a,$b)=>
        $a['date']===$b['date']
        ? strcmp($a['time'],$b['time'])
        : strcmp($a['date'],$b['date'])
    );
}

// 9. RENDER HTML OUTPUT
require_once __DIR__ . '/vendor/tecnickcom/tcpdf/tcpdf.php';
// Function to generate PDF
function generatePDF($scheduled, $unscheduledWEL, $unscheduledNCL) {
    $pdf = new TCPDF();
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('NCL League Platform');
    $pdf->SetTitle('2025 NCL & WEL Season Fixtures');
    $pdf->SetSubject('Generated Fixtures');
    $pdf->SetKeywords('Basketball, Fixtures, NCL, WEL');

    // Add a page
    $pdf->AddPage();

    // Title
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, '2025 NCL & WEL Season Fixtures', 0, 1, 'C');
    $pdf->Ln(5);

    // Scheduled Fixtures Table
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Scheduled Fixtures:', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);

    foreach ($scheduled as $date => $slots) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, "Date: $date", 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);

        // Table Header
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell(40, 10, 'Time', 1, 0, 'C', 1);
        $pdf->Cell(40, 10, 'League', 1, 0, 'C', 1);
        $pdf->Cell(100, 10, 'Matchup', 1, 1, 'C', 1);

        // Table Rows
        foreach ($slots as $time => $match) {
            $pdf->Cell(40, 10, $time, 1);
            $pdf->Cell(40, 10, $match['league'], 1);
            $pdf->Cell(100, 10, "{$match['home']} vs {$match['away']}", 1, 1);
        }
        $pdf->Ln(5);
    }

    // Unscheduled Fixtures Table
    if (count($unscheduledWEL) || count($unscheduledNCL)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 10, 'Unscheduled Fixtures:', 0, 1, 'L');

        if (count($unscheduledWEL)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'WEL:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);

            // Table Header
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(90, 10, 'Home', 1, 0, 'C', 1);
            $pdf->Cell(90, 10, 'Away', 1, 1, 'C', 1);

            // Table Rows
            foreach ($unscheduledWEL as $match) {
                $pdf->Cell(90, 10, $match['home'], 1);
                $pdf->Cell(90, 10, $match['away'], 1, 1);
            }
        }

        if (count($unscheduledNCL)) {
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 10, 'NCL:', 0, 1, 'L');
            $pdf->SetFont('helvetica', '', 10);

            // Table Header
            $pdf->SetFillColor(200, 200, 200);
            $pdf->Cell(90, 10, 'Home', 1, 0, 'C', 1);
            $pdf->Cell(90, 10, 'Away', 1, 1, 'C', 1);

            // Table Rows
            foreach ($unscheduledNCL as $match) {
                $pdf->Cell(90, 10, $match['home'], 1);
                $pdf->Cell(90, 10, $match['away'], 1, 1);
            }
        }
    }

    // Output PDF
    $pdf->Output('2025_NCL_WEL_Fixtures.pdf', 'D');
}

// Check if the user requested a PDF download
if (isset($_GET['download_pdf'])) {
    generatePDF($scheduled, $unscheduledWEL, $unscheduledNCL);
    exit;
}
?><!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>2025 NCL & WEL Schedule</title>
<style>
  body { font-family:Arial,sans-serif; margin:20px; }
  h1,h2,h3 { margin-top:1em; }
  table { border-collapse:collapse; width:100%; margin-bottom:1.5em; }
  th,td { border:1px solid #333; padding:0.5em; text-align:left; }
  ul { margin:0 0 1em 1.2em; }
  .box { display:inline-block; width:30px; height:14px; border:1px solid #000; margin:0 5px; }
  .warning { color:red; } .ok { color:green; }
  @media print { .no-print { display:none; } }
</style>
</head>
<body>

<h1>2025 NCL & WEL Season Schedule</h1>

<div class="no-print">
  <h2>📋 Season Summary</h2>
  <ul>
    <li>Duration: July 5 – Nov 30 2025 (22 weeks)</li>
    <li>Playoffs Start: Dec 5 2025</li>
    <li>Venue: KFC Court</li>
    <li>Daily Format: 5 games/day (90′ slots)</li>
    <li>Oct 10 Holiday: 5 extra games</li>
    <li>Oct 20 All-Star Day: no regular games</li>
  </ul>

  <div>
    <strong>Total Scheduled:</strong> <?=$totalScheduled?> / 273
    <?php if($totalScheduled<273): ?>
      <span class="warning">(<?=273-$totalScheduled?> unscheduled)</span>
    <?php else: ?>
      <span class="ok">(All 273 games scheduled)</span>
    <?php endif; ?>
  </div>

  <form method="get" class="no-print">
    <label>Filter by team:</label>
    <input list="teams" name="team" value="<?=htmlspecialchars($filter)?>" required>
    <datalist id="teams">
      <?php foreach($allTeams as $t): ?>
        <option value="<?=htmlspecialchars($t)?>">
      <?php endforeach; ?>
    </datalist>
    <button>Search</button>
    <?php if($filter):?><a href="?">Reset</a><?php endif;?>
  </form>

  <a href="?download_pdf=1" class="no-print">
    <button>Download Full Fixtures as PDF</button>
  </a>
</div>

<?php
// 10. WEEKLY VIEW
$startWeek = 3;
foreach ($fridays as $w => $fDate):
  $week = $startWeek + $w;
  $sDate = $saturdays[$w] ?? '';
  $uDate = $sundays[$w]   ?? '';
?>
  <h2>Week <?=$week?></h2>

  <h3>Friday <?=$fDate?></h3>
  <table>
    <tr><th>Time</th><th>Match</th><th>Score</th></tr>
    <?php foreach($friSlots as $t):
      $m = $scheduled[$fDate][$t] ?? null; if(!$m) continue;
    ?>
      <tr>
        <td><?=$t?></td>
        <td>[<?=$m['league']?>] <?=$m['home']?> vs <?=$m['away']?></td>
        <td><span class="box"></span>–<span class="box"></span></td>
      </tr>
    <?php endforeach;?>
  </table>

  <h3>Saturday <?=$sDate?></h3>
  <table>
    <tr><th>Time</th><th>Match</th><th>Score</th></tr>
    <?php foreach($satSlots as $i => $t):
      $m = $scheduled[$sDate][$t] ?? null; if(!$m) continue;
    ?>
      <tr>
        <td><?=$t?></td>
        <td>[<?=$m['league']?>] <?=$m['home']?> vs <?=$m['away']?></td>
        <td><span class="box"></span>–<span class="box"></span></td>
      </tr>
    <?php endforeach;?>
  </table>

  <?php if($sDate === $holiday): ?>
    <h3>Oct 10 Holiday Special</h3>
  <?php endif; ?>

  <?php if($uDate && $uDate!=='2025-10-20'): ?>
    <h3>Sunday <?=$uDate?></h3>
    <table>
      <tr><th>Time</th><th>Match</th><th>Score</th></tr>
      <?php foreach($sunSlots as $i => $t):
        $m = $scheduled[$uDate][$t] ?? null; if(!$m) continue;
      ?>
        <tr>
          <td><?=$t?></td>
          <td>[<?=$m['league']?>] <?=$m['home']?> vs <?=$m['away']?></td>
          <td><span class="box"></span>–<span class="box"></span></td>
        </tr>
      <?php endforeach;?>
    </table>
  <?php endif; ?>

<?php endforeach; ?>

<?php if(!empty($wednesdays)): ?>
  <h2>Wednesday Make-Ups</h2>
  <table>
    <tr><th>Date</th><th>Time</th><th>Match</th><th>Score</th></tr>
    <?php foreach($wednesdays as $wed):
      foreach($wedSlots as $t):
        $m = $scheduled[$wed][$t] ?? null; if(!$m) continue;
    ?>
      <tr>
        <td><?=$wed?></td>
        <td><?=$t?></td>
        <td>[<?=$m['league']?>] <?=$m['home']?> vs <?=$m['away']?></td>
        <td><span class="box"></span>–<span class="box"></span></td>
      </tr>
    <?php endforeach; endforeach;?>
  </table>
<?php endif; ?>

<?php if(count($unscheduledWEL) || count($unscheduledNCL)): ?>
  <h2>Unscheduled Games (<?=count($unscheduledWEL)+count($unscheduledNCL)?>)</h2>
  <?php if(count($unscheduledWEL)): ?>
    <h3>WEL (<?=count($unscheduledWEL)?>)</h3><ul>
    <?php foreach($unscheduledWEL as $g): ?>
      <li><?=$g['home']?> vs <?=$g['away']?></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <?php if(count($unscheduledNCL)): ?>
    <h3>NCL (<?=count($unscheduledNCL)?>)</h3><ul>
    <?php foreach($unscheduledNCL as $g): ?>
      <li><?=$g['home']?> vs <?=$g['away']?></li>
    <?php endforeach; ?>
    </ul>
  <?php endif; ?>
<?php endif; ?>

</body>
</html>
