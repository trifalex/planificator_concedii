<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Disable caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Thu, 01 Jan 1970 00:00:00 GMT");

$year = isset($_GET['year']) ? (int)$_GET['year'] : (isset($_POST['year']) ? (int)$_POST['year'] : 2026);
if ($year < 2020 || $year > 2030) $year = 2026;

$dataFile = "concedii_{$year}.json";

// Load data
function loadData($file, $year) {
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        if ($data) return $data;
    }
    return [
        'year' => $year,
        'users' => [
            ['name' => 'Angajat 1', 'totalDays' => 21, 'vacations' => []],
            ['name' => 'Angajat 2', 'totalDays' => 21, 'vacations' => []]
        ]
    ];
}

// Save data
function saveData($file, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        error_log("JSON encode error: " . json_last_error_msg());
        return false;
    }
    
    $result = @file_put_contents($file, $json, LOCK_EX);
    if ($result === false) {
        error_log("Cannot write to file: $file - " . error_get_last()['message']);
        return false;
    }
    
    return true;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $year = isset($_POST['year']) ? (int)$_POST['year'] : 2026;
    $dataFile = "concedii_{$year}.json";
    $data = loadData($dataFile, $year);
    
    switch ($_POST['action']) {
        case 'toggle_vacation':
            $userIndex = (int)$_POST['userIndex'];
            $dateStr = $_POST['dateStr'];
            if (isset($data['users'][$userIndex])) {
                $vacations = &$data['users'][$userIndex]['vacations'];
                $vacIndex = array_search($dateStr, $vacations);
                if ($vacIndex !== false) {
                    array_splice($vacations, $vacIndex, 1);
                } else {
                    if (count($vacations) < $data['users'][$userIndex]['totalDays']) {
                        $vacations[] = $dateStr;
                        sort($vacations);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Ai atins limita de zile (' . $data['users'][$userIndex]['totalDays'] . ')']);
                        exit;
                    }
                }
                $saved = saveData($dataFile, $data);
                if (!$saved) {
                    echo json_encode(['success' => false, 'error' => 'Nu pot salva fisierul. Verifica permisiunile.', 'file' => $dataFile]);
                    exit;
                }
                echo json_encode(['success' => true, 'data' => $data, 'file' => $dataFile]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
            
        case 'add_user':
            $name = trim($_POST['name'] ?? '');
            $days = (int)($_POST['days'] ?? 21);
            $dept = trim($_POST['department'] ?? 'General');
            if ($days <= 0) $days = 21;
            if ($name) {
                $data['users'][] = ['name' => $name, 'department' => $dept, 'totalDays' => $days, 'vacations' => []];
                $saved = saveData($dataFile, $data);
                echo json_encode(['success' => $saved, 'data' => $data]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Name required']);
            exit;
            
        case 'remove_user':
            $index = (int)$_POST['index'];
            if (count($data['users']) > 1 && isset($data['users'][$index])) {
                array_splice($data['users'], $index, 1);
                $saved = saveData($dataFile, $data);
                echo json_encode(['success' => $saved, 'data' => $data]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'Cannot remove']);
            exit;
            
        case 'update_days':
            $index = (int)$_POST['index'];
            $days = max(0, min(365, (int)$_POST['days']));
            if (isset($data['users'][$index])) {
                $data['users'][$index]['totalDays'] = $days;
                $saved = saveData($dataFile, $data);
                echo json_encode(['success' => $saved, 'data' => $data]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'User not found']);
            exit;
            
        case 'bulk_import':
            $names = array_filter(array_map('trim', explode("\n", $_POST['names'] ?? '')));
            $dept = trim($_POST['department'] ?? 'General');
            $count = 0;
            foreach ($names as $name) {
                if ($name) {
                    $data['users'][] = ['name' => $name, 'department' => $dept, 'totalDays' => 21, 'vacations' => []];
                    $count++;
                }
            }
            if ($count > 0) {
                $saved = saveData($dataFile, $data);
                echo json_encode(['success' => $saved, 'data' => $data, 'imported' => $count]);
                exit;
            }
            echo json_encode(['success' => false, 'error' => 'No names']);
            exit;
            
        case 'get_data':
            echo json_encode(['success' => true, 'data' => $data, 'file' => $dataFile]);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}

$data = loadData($dataFile, $year);
$users = $data['users'];

// Sort users by department first, then alphabetically by name
$usersWithIndex = [];
foreach ($users as $index => $user) {
    $user['_originalIndex'] = $index;
    if (!isset($user['department'])) $user['department'] = 'General';
    $usersWithIndex[] = $user;
}
usort($usersWithIndex, function($a, $b) {
    $deptCmp = strcasecmp($a['department'], $b['department']);
    if ($deptCmp !== 0) return $deptCmp;
    return strcasecmp($a['name'], $b['name']);
});

// Holidays
function getHolidays($year) {
    $holidays = [
        "$year-01-01" => 'Anul Nou',
        "$year-01-02" => 'Anul Nou',
        "$year-01-06" => 'Boboteaza',
        "$year-01-07" => 'Sf. Ioan Botezatorul',
        "$year-01-24" => 'Ziua Unirii',
        "$year-05-01" => 'Ziua Muncii',
        "$year-06-01" => 'Ziua Copilului',
        "$year-08-15" => 'Adormirea Maicii Domnului',
        "$year-11-30" => 'Sfantul Andrei',
        "$year-12-01" => 'Ziua Nationala',
        "$year-12-25" => 'Craciunul',
        "$year-12-26" => 'Craciunul',
    ];
    
    // Orthodox Easter dates (pre-calculated)
    $easterDates = [
        2024 => '05-05',
        2025 => '04-20', 
        2026 => '04-12',
        2027 => '05-02',
        2028 => '04-16',
        2029 => '04-08',
        2030 => '04-28',
    ];
    
    if (isset($easterDates[$year])) {
        $easter = new DateTime("$year-{$easterDates[$year]}");
        
        // Vinerea Mare (2 days before)
        $vinereaM = clone $easter;
        $vinereaM->modify('-2 days');
        $holidays[$vinereaM->format('Y-m-d')] = 'Vinerea Mare';
        
        // Sambata Mare (1 day before)
        $sambataM = clone $easter;
        $sambataM->modify('-1 day');
        $holidays[$sambataM->format('Y-m-d')] = 'Sambata Mare';
        
        // Paste (Easter Sunday)
        $holidays[$easter->format('Y-m-d')] = 'Paste';
        
        // A doua zi de Paste
        $paste2 = clone $easter;
        $paste2->modify('+1 day');
        $holidays[$paste2->format('Y-m-d')] = 'Paste';
        
        // Rusalii (50 days after Easter)
        $rusalii = clone $easter;
        $rusalii->modify('+49 days');
        $holidays[$rusalii->format('Y-m-d')] = 'Rusalii';
        
        $rusalii2 = clone $easter;
        $rusalii2->modify('+50 days');
        $rusaliiDate = $rusalii2->format('Y-m-d');
        if (isset($holidays[$rusaliiDate])) {
            $holidays[$rusaliiDate] .= ' + Rusalii';
        } else {
            $holidays[$rusaliiDate] = 'Rusalii';
        }
    }
    
    return $holidays;
}

$holidays = getHolidays($year);
$monthsRo = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthsFull = ['Ianuarie', 'Februarie', 'Martie', 'Aprilie', 'Mai', 'Iunie', 'Iulie', 'August', 'Septembrie', 'Octombrie', 'Noiembrie', 'Decembrie'];

// Get all days
$allDays = [];
for ($month = 1; $month <= 12; $month++) {
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf('%d-%02d-%02d', $year, $month, $day);
        $allDays[] = ['month' => $month - 1, 'day' => $day, 'dateStr' => $dateStr];
    }
}

// Find overlaps - per department
$dateCounts = [];
foreach ($users as $user) {
    $dept = $user['department'] ?? 'General';
    foreach ($user['vacations'] as $date) {
        $key = $dept . '|' . $date;
        $dateCounts[$key] = ($dateCounts[$key] ?? 0) + 1;
    }
}
$overlaps = [];
foreach ($dateCounts as $key => $count) {
    if ($count > 1) {
        list($dept, $date) = explode('|', $key);
        $overlaps[$date][] = $dept;
    }
}

// Check if directory is writable
$isWritable = is_writable(dirname($dataFile) ?: '.');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Planificator Concedii <?= $year ?></title>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --bg-main: #f8f9fa;
            --bg-card: #ffffff;
            --bg-row: #ffffff;
            --bg-row-alt: #fafbfc;
            --text-primary: #2d3748;
            --text-secondary: #718096;
            --text-muted: #a0aec0;
            --accent-blue: #5a7bb5;
            --accent-green: #6b9080;
            --accent-orange: #c4956a;
            --accent-purple: #8b7bb5;
            --accent-red: #b57b7b;
            --weekend-bg: #f0eef5;
            --holiday-bg: #eef3f0;
            --vacation-bg: #fff3e6;
            --vacation-dot: #d4a574;
            --border: #e2e8f0;
            --border-light: #edf2f7;
            --header-bg: #f7fafc;
        }
        
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container { max-width: 100%; }
        
        .error-box {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 13px;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
            padding: 16px 20px;
            background: var(--bg-card);
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .header h1 {
            font-family: 'IBM Plex Mono', monospace;
            font-size: 18px;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .header h1 small {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 400;
            display: block;
        }
        
        .controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .input-field {
            background: var(--bg-main);
            border: 1px solid var(--border);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 13px;
            transition: border-color 0.2s;
        }
        
        .input-field:focus {
            outline: none;
            border-color: var(--accent-blue);
        }
        
        .user-input { width: 140px; }
        .days-input { width: 70px; }
        
        .btn {
            background: var(--accent-blue);
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            transition: all 0.2s;
            font-family: 'IBM Plex Sans', sans-serif;
        }
        
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        .btn-green { background: var(--accent-green); }
        .btn-muted { background: var(--text-secondary); }
        
        .info-bar {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .filter-section {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        
        .legend {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            padding: 10px 16px;
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 12px;
            color: var(--text-secondary);
        }
        
        .legend-item { display: flex; align-items: center; gap: 6px; }
        .legend-box { width: 14px; height: 14px; border-radius: 3px; }
        .legend-box.weekend { background: #e9d5ff; border: 1px solid #7c3aed; }
        .legend-box.holiday { background: #bbf7d0; border: 1px solid #15803d; }
        .legend-box.vacation { background: #3b82f6; border: 1px solid #1d4ed8; }
        .legend-box.overlap { background: #dc2626; border: 1px solid #991b1b; }
        
        .stats {
            display: flex;
            gap: 20px;
            padding: 10px 16px;
            background: var(--bg-card);
            border-radius: 6px;
            border: 1px solid var(--border);
            font-size: 12px;
            font-family: 'IBM Plex Mono', monospace;
        }
        
        .stat span:first-child { color: var(--text-secondary); }
        .stat span:last-child { font-weight: 500; color: var(--text-primary); }
        
        .table-wrapper {
            overflow-x: auto;
            background: var(--bg-card);
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        
        .excel-table {
            border-collapse: collapse;
            font-size: 11px;
            min-width: 100%;
        }
        
        .excel-table th, .excel-table td {
            border: 1px solid var(--border-light);
            text-align: center;
            min-width: 22px;
            height: 26px;
            padding: 0;
        }
        
        .excel-table thead { position: sticky; top: 0; z-index: 10; }
        
        .month-header {
            background: var(--header-bg);
            color: var(--accent-blue);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border) !important;
        }
        
        .day-header {
            background: var(--bg-card);
            color: var(--text-muted);
            font-weight: 500;
            font-size: 10px;
        }
        
        .day-header.weekend { background: var(--weekend-bg); color: var(--accent-purple); }
        .day-header.holiday { background: var(--holiday-bg); color: var(--accent-green); }
        
        .user-name-cell {
            background: var(--header-bg);
            color: var(--text-primary);
            font-weight: 500;
            text-align: left !important;
            padding: 0 12px !important;
            min-width: 200px !important;
            max-width: 200px !important;
            width: 200px !important;
            position: sticky;
            left: 0;
            z-index: 5;
            border-right: 2px solid var(--border) !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .user-dept {
            display: inline-block;
            background: #e0e7ff;
            color: #3730a3;
            font-size: 9px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 6px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .user-name-cell .remove-btn {
            color: var(--text-muted);
            cursor: pointer;
            margin-left: 8px;
            font-size: 14px;
            float: right;
        }
        
        .user-name-cell .remove-btn:hover { color: var(--accent-red); }
        
        .user-stats-cell {
            background: var(--header-bg);
            font-family: 'IBM Plex Mono', monospace;
            font-size: 10px;
            min-width: 90px !important;
            width: 90px !important;
            position: sticky;
            left: 200px;
            z-index: 5;
            padding: 0 8px !important;
        }
        
        .days-edit-input {
            width: 32px;
            border: 1px solid var(--border);
            border-radius: 3px;
            text-align: center;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 10px;
            padding: 2px;
            background: #fff;
            color: var(--accent-green);
        }
        
        .days-edit-input:focus { outline: none; border-color: var(--accent-blue); }
        
        .day-cell {
            cursor: pointer;
            transition: all 0.15s;
            position: relative;
        }
        
        .day-cell:hover { background: var(--border-light) !important; }
        .day-cell.weekend { background: #e9d5ff; cursor: default; }
        .day-cell.holiday { background: #bbf7d0; cursor: default; }
        .day-cell.vacation { 
            background: #3b82f6; 
        }
        
        .day-cell.overlap { 
            background: #dc2626 !important; 
        }
        
        .day-header.weekend { background: #e9d5ff; color: #7c3aed; }
        .day-header.holiday { background: #bbf7d0; color: #15803d; }
        
        .day-header.has-vacation {
            background: #3b82f6 !important;
            color: #fff !important;
            font-weight: 700;
        }
        
        .day-header.has-overlap {
            background: #dc2626 !important;
            color: #fff !important;
            font-weight: 700;
        }
        .day-cell.normal { background: var(--bg-row); }
        
        .excel-table tbody tr:nth-child(even) .day-cell.normal { background: var(--bg-row-alt); }
        .excel-table tbody tr:nth-child(even) .user-name-cell,
        .excel-table tbody tr:nth-child(even) .user-stats-cell { background: var(--bg-row-alt); }
        
        .bulk-import {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 15px;
            display: none;
        }
        
        .bulk-import.show { display: block; }
        
        .bulk-import-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .bulk-textarea {
            width: 100%;
            height: 120px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-family: 'IBM Plex Sans', sans-serif;
            font-size: 13px;
            resize: vertical;
            background: var(--bg-main);
            color: var(--text-primary);
        }
        
        .bulk-textarea:focus { outline: none; border-color: var(--accent-blue); }
        
        .overlap-warning {
            background: #fef8f8;
            border: 1px solid #e8d4d4;
            padding: 10px 14px;
            border-radius: 6px;
            margin-bottom: 15px;
            font-size: 12px;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .overlap-warning strong { color: var(--accent-red); }
        
        .json-output {
            margin-top: 20px;
            background: var(--bg-card);
            border-radius: 8px;
            padding: 16px;
            border: 1px solid var(--border);
            display: none;
        }
        
        .json-output.show { display: block; }
        
        .json-output h3 {
            margin-bottom: 12px;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 500;
        }
        
        .json-output pre {
            background: var(--bg-main);
            padding: 12px;
            border-radius: 6px;
            overflow-x: auto;
            font-family: 'IBM Plex Mono', monospace;
            font-size: 11px;
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border);
            color: var(--text-secondary);
        }
        
        .saved-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--accent-green);
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            font-size: 13px;
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 1000;
        }
        
        .saved-indicator.show { opacity: 1; }
        .saved-indicator.error { background: var(--accent-red); }
        
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.8);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .loading.show { display: flex; }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isWritable): ?>
        <div class="error-box">
            <strong>Eroare:</strong> Directorul nu are permisiuni de scriere. Ruleaza: <code>chmod 755 <?= dirname($dataFile) ?: '.' ?></code> sau <code>chmod 666 <?= $dataFile ?></code>
        </div>
        <?php endif; ?>
        
        <div class="header">
            <h1>
                Planificator Concedii <?= $year ?>
                <small>Fisier: <?= $dataFile ?> | Actualizat: <span id="lastUpdate"><?= date('H:i:s') ?></span></small>
            </h1>
            <div class="controls">
                <select class="input-field" id="yearSelect">
                    <?php for ($y = 2024; $y <= 2030; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
                <input class="input-field user-input" id="newUserName" placeholder="Nume angajat">
                <select class="input-field" id="newUserDept" style="width: 140px;">
                    <?php 
                    $existingDepts = [];
                    foreach ($users as $u) {
                        if (!empty($u['department'])) {
                            $existingDepts[$u['department']] = true;
                        }
                    }
                    $defaultDepts = ['General', 'IT', 'HR', 'Vanzari', 'Marketing', 'Financiar', 'Productie', 'Logistica'];
                    $allDepts = array_unique(array_merge($defaultDepts, array_keys($existingDepts)));
                    sort($allDepts);
                    foreach ($allDepts as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
                <input class="input-field days-input" id="newUserDays" type="number" placeholder="Zile" value="21">
                <button class="btn" onclick="addUser()">+ Adauga</button>
                <button class="btn btn-muted" onclick="toggleJson()">JSON</button>
                <button class="btn btn-green" onclick="downloadJson()">Download</button>
                <button class="btn btn-muted" onclick="toggleBulkImport()">Import</button>
            </div>
        </div>
        
        <div id="bulkImport" class="bulk-import">
            <div class="bulk-import-header">
                <span>Import angajati (unul pe linie)</span>
                <div>
                    <select class="input-field" id="bulkDept" style="width: 120px; margin-right: 10px;">
                        <?php foreach ($allDepts as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button class="btn" onclick="bulkImport()">Importa</button>
                </div>
            </div>
            <textarea class="bulk-textarea" id="bulkText" placeholder="Ion Popescu&#10;Maria Ionescu&#10;Gheorghe Popa&#10;..."></textarea>
        </div>
        
        <div class="info-bar">
            <div class="legend">
                <div class="legend-item"><div class="legend-box weekend"></div><span>Weekend</span></div>
                <div class="legend-item"><div class="legend-box holiday"></div><span>Sarbatoare</span></div>
                <div class="legend-item"><div class="legend-box vacation"></div><span>Concediu</span></div>
                <div class="legend-item"><div class="legend-box overlap"></div><span>Suprapunere</span></div>
            </div>
            
            <div class="filter-section">
                <label style="font-size: 12px; color: var(--text-secondary); margin-right: 8px;">Filtru:</label>
                <select class="input-field" id="deptFilter" onchange="filterByDept()" style="width: 150px;">
                    <option value="">Toate departamentele</option>
                    <?php foreach ($allDepts as $dept): ?>
                        <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="stats">
                <div class="stat"><span>Angajati: </span><span id="userCountDisplay"><?= count($usersWithIndex) ?></span></div>
                <div class="stat"><span>Sarbatori: </span><span><?= count($holidays) ?></span></div>
                <div class="stat"><span>Suprapuneri: </span><span id="overlapCountDisplay"><?= count($overlaps) ?></span></div>
            </div>
        </div>
        
        <?php if (count($overlaps) > 0): ?>
        <div class="overlap-warning" id="overlapWarning">
            <strong>Atentie:</strong>
            <span id="overlapText"><?= count($overlaps) ?> zile cu suprapunere (in acelasi departament)</span>
        </div>
        <?php else: ?>
        <div class="overlap-warning" id="overlapWarning" style="display: none;">
            <strong>Atentie:</strong>
            <span id="overlapText">0 zile cu suprapunere</span>
        </div>
        <?php endif; ?>
        
        <div class="table-wrapper">
            <table class="excel-table">
                <thead>
                    <tr>
                        <th class="user-name-cell">Angajat</th>
                        <th class="user-stats-cell">Folosite / Total</th>
                        <?php
                        $monthCounts = [];
                        foreach ($allDays as $day) {
                            $monthCounts[$day['month']] = ($monthCounts[$day['month']] ?? 0) + 1;
                        }
                        foreach ($monthCounts as $m => $count): ?>
                            <th class="month-header" colspan="<?= $count ?>"><?= $monthsRo[$m] ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th class="user-name-cell"></th>
                        <th class="user-stats-cell"></th>
                        <?php 
                        // Calculate which dates have vacations and overlaps for header highlighting (per department)
                        $datesWithVacation = [];
                        $headerOverlaps = [];
                        $deptDateCounts = [];
                        foreach ($users as $user) {
                            $dept = $user['department'] ?? 'General';
                            foreach ($user['vacations'] as $vDate) {
                                $datesWithVacation[$vDate] = true;
                                $key = $dept . '|' . $vDate;
                                $deptDateCounts[$key] = ($deptDateCounts[$key] ?? 0) + 1;
                            }
                        }
                        foreach ($deptDateCounts as $key => $count) {
                            if ($count > 1) {
                                list($dept, $date) = explode('|', $key);
                                $headerOverlaps[$date] = true;
                            }
                        }
                        ?>
                        <?php foreach ($allDays as $day):
                            $dateStr = $day['dateStr'];
                            $date = new DateTime($dateStr);
                            $dayOfWeek = $date->format('N');
                            $isWeekend = $dayOfWeek >= 6;
                            $isHoliday = isset($holidays[$dateStr]);
                            $hasVacation = isset($datesWithVacation[$dateStr]);
                            $hasOverlap = isset($headerOverlaps[$dateStr]);
                            $class = 'day-header';
                            // Only mark as overlap if same department overlap exists
                            if ($hasOverlap) $class .= ' has-overlap';
                            elseif ($hasVacation) $class .= ' has-vacation';
                            elseif ($isHoliday) $class .= ' holiday';
                            elseif ($isWeekend) $class .= ' weekend';
                        ?>
                            <th class="<?= $class ?>" data-date="<?= $dateStr ?>" data-base-class="<?= $isHoliday ? 'holiday' : ($isWeekend ? 'weekend' : '') ?>" title="<?= $day['day'] ?> <?= $monthsFull[$day['month']] ?><?= $isHoliday ? ' - ' . $holidays[$dateStr] : '' ?>"><?= $day['day'] ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usersWithIndex as $user): 
                        $userIndex = $user['_originalIndex'];
                        $userDept = $user['department'] ?? 'General';
                    ?>
                    <tr data-user="<?= $userIndex ?>" data-dept="<?= htmlspecialchars($userDept) ?>">
                        <td class="user-name-cell" title="<?= htmlspecialchars($user['name']) ?> - <?= htmlspecialchars($user['department'] ?? 'General') ?>">
                            <span class="user-dept"><?= htmlspecialchars($user['department'] ?? 'General') ?></span>
                            <?= htmlspecialchars($user['name']) ?>
                            <?php if (count($usersWithIndex) > 1): ?>
                                <span class="remove-btn" onclick="removeUser(<?= $userIndex ?>)">×</span>
                            <?php endif; ?>
                        </td>
                        <td class="user-stats-cell">
                            <span style="color: var(--accent-orange)"><?= count($user['vacations']) ?></span>
                            <span style="color: var(--text-muted)"> / </span>
                            <input type="number" class="days-edit-input" value="<?= $user['totalDays'] ?>" min="0" max="365" onchange="updateDays(<?= $userIndex ?>, this.value)">
                        </td>
                        <?php foreach ($allDays as $day):
                            $dateStr = $day['dateStr'];
                            $date = new DateTime($dateStr);
                            $dayOfWeek = $date->format('N');
                            $isWeekend = $dayOfWeek >= 6;
                            $isHoliday = isset($holidays[$dateStr]);
                            $isVacation = in_array($dateStr, $user['vacations']);
                            $userDept = $user['department'] ?? 'General';
                            $isOverlap = $isVacation && isset($overlaps[$dateStr]) && in_array($userDept, $overlaps[$dateStr]);
                            
                            $class = 'day-cell';
                            if ($isOverlap) $class .= ' overlap vacation';
                            elseif ($isVacation) $class .= ' vacation';
                            elseif ($isHoliday) $class .= ' holiday';
                            elseif ($isWeekend) $class .= ' weekend';
                            else $class .= ' normal';
                            
                            $clickable = !$isWeekend && !$isHoliday;
                        ?>
                            <td class="<?= $class ?>" data-date="<?= $dateStr ?>" data-day="<?= $day['day'] ?>" <?= $clickable ? "onclick=\"toggleVacation({$userIndex}, '{$dateStr}', this)\"" : '' ?>></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div id="jsonOutput" class="json-output">
            <h3>Export JSON - <?= $dataFile ?></h3>
            <pre><?= json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?></pre>
        </div>
    </div>
    
    <div class="saved-indicator" id="savedIndicator">Salvat!</div>
    <div class="loading" id="loading">Se incarca...</div>
    
    <script>
        const currentYear = <?= $year ?>;
        
        function updateTimestamp() {
            const now = new Date();
            const time = now.toLocaleTimeString('ro-RO');
            document.getElementById('lastUpdate').textContent = time;
            
            // Update URL bar with timestamp
            const url = new URL(window.location.href);
            url.searchParams.set('_t', Date.now());
            window.history.replaceState({}, '', url.toString());
        }
        
        function showMessage(text, isError = false) {
            const el = document.getElementById('savedIndicator');
            el.textContent = text;
            el.classList.toggle('error', isError);
            el.classList.add('show');
            setTimeout(() => el.classList.remove('show'), 2000);
        }
        
        function showLoading(show) {
            document.getElementById('loading').classList.toggle('show', show);
        }
        
        function apiCall(data) {
            showLoading(true);
            data.year = currentYear;
            data._t = Date.now(); // Cache buster
            
            return fetch(window.location.pathname + '?_t=' + Date.now(), {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Cache-Control': 'no-cache'
                },
                body: new URLSearchParams(data).toString(),
                cache: 'no-store'
            })
            .then(r => r.json())
            .then(res => {
                showLoading(false);
                if (!res.success) {
                    showMessage('Eroare: ' + (res.error || 'necunoscuta'), true);
                }
                return res;
            })
            .catch(err => {
                showLoading(false);
                showMessage('Eroare conexiune!', true);
                console.error(err);
                return { success: false };
            });
        }
        
        function toggleVacation(userIndex, dateStr, cellElement) {
            // Disable cell during request
            cellElement.style.pointerEvents = 'none';
            cellElement.style.opacity = '0.5';
            
            apiCall({ action: 'toggle_vacation', userIndex, dateStr })
                .then(res => {
                    if (res.success) {
                        // Update cell visually
                        const isNowVacation = res.data.users[userIndex].vacations.includes(dateStr);
                        
                        cellElement.classList.remove('vacation', 'overlap', 'normal');
                        if (isNowVacation) {
                            cellElement.classList.add('vacation');
                        } else {
                            cellElement.classList.add('normal');
                        }
                        
                        // Update used days count
                        const row = cellElement.closest('tr');
                        const usedSpan = row.querySelector('td.user-stats-cell span');
                        if (usedSpan) {
                            usedSpan.textContent = res.data.users[userIndex].vacations.length;
                        }
                        
                        // Check for overlaps and update all cells for this date
                        updateOverlaps(res.data.users, dateStr);
                        
                        // Update headers
                        updateHeaders(res.data.users);
                        
                        updateTimestamp();
                        showMessage('Salvat!');
                    }
                    cellElement.style.pointerEvents = '';
                    cellElement.style.opacity = '';
                })
                .catch(() => {
                    cellElement.style.pointerEvents = '';
                    cellElement.style.opacity = '';
                });
        }
        
        function updateOverlaps(users, changedDate) {
            // Count vacations per date per department
            const dateCounts = {};
            users.forEach(user => {
                const dept = user.department || 'General';
                user.vacations.forEach(date => {
                    const key = dept + '|' + date;
                    dateCounts[key] = (dateCounts[key] || 0) + 1;
                });
            });
            
            // Find all overlapping dates per department
            const overlaps = {};
            Object.keys(dateCounts).forEach(key => {
                if (dateCounts[key] > 1) {
                    const [dept, date] = key.split('|');
                    if (!overlaps[date]) overlaps[date] = [];
                    overlaps[date].push(dept);
                }
            });
            
            const overlapCount = Object.keys(overlaps).length;
            
            // Update overlap count in stats
            const overlapCountEl = document.querySelector('.stat:last-child span:last-child');
            if (overlapCountEl) {
                overlapCountEl.textContent = overlapCount;
            }
            
            // Update overlap warning
            const warningEl = document.getElementById('overlapWarning');
            const overlapText = document.getElementById('overlapText');
            if (warningEl && overlapText) {
                if (overlapCount > 0) {
                    warningEl.style.display = 'flex';
                    overlapText.textContent = overlapCount + ' zile cu suprapunere';
                } else {
                    warningEl.style.display = 'none';
                }
            }
            
            // Clear all overlap classes first
            document.querySelectorAll('td.overlap').forEach(cell => {
                cell.classList.remove('overlap');
                cell.classList.add('vacation');
            });
            
            // Add overlap class to cells that are now overlapping (same department)
            Object.keys(overlaps).forEach(overlapDate => {
                const depts = overlaps[overlapDate];
                users.forEach((user, idx) => {
                    const userDept = user.department || 'General';
                    if (user.vacations.includes(overlapDate) && depts.includes(userDept)) {
                        const cell = document.querySelector(`tr[data-user="${idx}"] td[data-date="${overlapDate}"]`);
                        if (cell) {
                            cell.classList.add('overlap');
                            cell.classList.remove('vacation');
                        }
                    }
                });
            });
        }
        
        function filterByDept() {
            const selectedDept = document.getElementById('deptFilter').value;
            const rows = document.querySelectorAll('tbody tr[data-dept]');
            let visibleCount = 0;
            
            rows.forEach(row => {
                const rowDept = row.getAttribute('data-dept');
                if (selectedDept === '' || rowDept === selectedDept) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Update user count
            document.getElementById('userCountDisplay').textContent = visibleCount;
            
            // Update headers based on visible rows only
            updateHeadersForFilter(selectedDept);
        }
        
        function updateHeadersForFilter(selectedDept) {
            // Get all users data from visible rows
            const allUsers = <?= json_encode($users) ?>;
            
            // Filter users by department if selected
            const filteredUsers = selectedDept 
                ? allUsers.filter(u => (u.department || 'General') === selectedDept)
                : allUsers;
            
            // Count vacations per date per department (only for filtered users)
            const dateCounts = {};
            const deptDateCounts = {};
            
            filteredUsers.forEach(user => {
                const dept = user.department || 'General';
                user.vacations.forEach(date => {
                    dateCounts[date] = (dateCounts[date] || 0) + 1;
                    const key = dept + '|' + date;
                    deptDateCounts[key] = (deptDateCounts[key] || 0) + 1;
                });
            });
            
            // Find dates with overlaps (same department)
            const overlaps = {};
            Object.keys(deptDateCounts).forEach(key => {
                if (deptDateCounts[key] > 1) {
                    const [dept, date] = key.split('|');
                    overlaps[date] = true;
                }
            });
            
            // Update overlap count
            const overlapCount = Object.keys(overlaps).length;
            document.getElementById('overlapCountDisplay').textContent = overlapCount;
            
            // Update warning
            const warningEl = document.getElementById('overlapWarning');
            const overlapText = document.getElementById('overlapText');
            if (warningEl && overlapText) {
                if (overlapCount > 0) {
                    warningEl.style.display = 'flex';
                    overlapText.textContent = overlapCount + ' zile cu suprapunere (in acelasi departament)';
                } else {
                    warningEl.style.display = 'none';
                }
            }
            
            // Reset all headers
            document.querySelectorAll('th.day-header').forEach(header => {
                header.classList.remove('has-vacation', 'has-overlap');
                const baseClass = header.getAttribute('data-base-class');
                if (baseClass) {
                    header.classList.add(baseClass);
                }
            });
            
            // Mark headers with vacations (only for filtered users)
            Object.keys(dateCounts).forEach(date => {
                const header = document.querySelector(`th.day-header[data-date="${date}"]`);
                if (header) {
                    header.classList.remove('holiday', 'weekend');
                    if (overlaps[date]) {
                        header.classList.add('has-overlap');
                        header.classList.remove('has-vacation');
                    } else {
                        header.classList.add('has-vacation');
                        header.classList.remove('has-overlap');
                    }
                }
            });
        }
        
        function updateHeaders(users) {
            // Count vacations per date per department
            const dateCounts = {};
            const deptDateCounts = {};
            
            users.forEach(user => {
                const dept = user.department || 'General';
                user.vacations.forEach(date => {
                    dateCounts[date] = (dateCounts[date] || 0) + 1;
                    const key = dept + '|' + date;
                    deptDateCounts[key] = (deptDateCounts[key] || 0) + 1;
                });
            });
            
            // Find dates with overlaps (same department only)
            const overlaps = {};
            Object.keys(deptDateCounts).forEach(key => {
                if (deptDateCounts[key] > 1) {
                    const [dept, date] = key.split('|');
                    overlaps[date] = true;
                }
            });
            
            // Reset all headers first
            document.querySelectorAll('th.day-header').forEach(header => {
                header.classList.remove('has-vacation', 'has-overlap');
                const baseClass = header.getAttribute('data-base-class');
                if (baseClass) {
                    header.classList.add(baseClass);
                }
            });
            
            // Mark headers with vacations
            Object.keys(dateCounts).forEach(date => {
                const header = document.querySelector(`th.day-header[data-date="${date}"]`);
                if (header) {
                    header.classList.remove('holiday', 'weekend');
                    if (overlaps[date]) {
                        header.classList.add('has-overlap');
                    } else {
                        header.classList.add('has-vacation');
                    }
                }
            });
        }
        
        function addUser() {
            const name = document.getElementById('newUserName').value.trim();
            const days = document.getElementById('newUserDays').value || 21;
            const dept = document.getElementById('newUserDept').value || 'General';
            if (!name) {
                showMessage('Introdu un nume!', true);
                return;
            }
            
            apiCall({ action: 'add_user', name, days, department: dept })
                .then(res => {
                    if (res.success) {
                        updateTimestamp();
                        showMessage('Angajat adaugat!');
                        setTimeout(() => location.reload(), 300);
                    }
                });
        }
        
        function removeUser(index) {
            if (!confirm('Stergi acest angajat?')) return;
            
            apiCall({ action: 'remove_user', index })
                .then(res => {
                    if (res.success) {
                        updateTimestamp();
                        showMessage('Angajat sters!');
                        setTimeout(() => location.reload(), 300);
                    }
                });
        }
        
        function updateDays(index, days) {
            apiCall({ action: 'update_days', index, days })
                .then(res => {
                    if (res.success) {
                        updateTimestamp();
                        showMessage('Salvat!');
                    }
                });
        }
        
        function toggleBulkImport() {
            document.getElementById('bulkImport').classList.toggle('show');
        }
        
        function bulkImport() {
            const names = document.getElementById('bulkText').value;
            const dept = document.getElementById('bulkDept').value || 'General';
            if (!names.trim()) {
                showMessage('Lista goala!', true);
                return;
            }
            
            apiCall({ action: 'bulk_import', names, department: dept })
                .then(res => {
                    if (res.success) {
                        updateTimestamp();
                        showMessage(res.imported + ' angajati importati!');
                        setTimeout(() => location.reload(), 500);
                    }
                });
        }
        
        function toggleJson() {
            document.getElementById('jsonOutput').classList.toggle('show');
        }
        
        function downloadJson() {
            apiCall({ action: 'get_data' })
                .then(res => {
                    if (res.success) {
                        const blob = new Blob([JSON.stringify(res.data, null, 2)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `concedii_${res.data.year}.json`;
                        a.click();
                        URL.revokeObjectURL(url);
                    }
                });
        }
        
        document.getElementById('yearSelect').addEventListener('change', function() {
            window.location.href = '?year=' + this.value + '&_t=' + Date.now();
        });
        
        document.getElementById('newUserName').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') addUser();
        });
    </script>
</body>
</html>
