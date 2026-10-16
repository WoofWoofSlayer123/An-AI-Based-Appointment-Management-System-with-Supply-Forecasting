<?php
session_start();

require_once "connection.php";
require_once "auth_check.php";

if (!isset($_SESSION["username"]) || ($_SESSION["role"] ?? "") !== "staff") {
    header("Location: login.php");
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}

function displayDate($date): string
{
    if (empty($date)) {
        return "Not recorded";
    }

    $timestamp = strtotime($date);

    return $timestamp !== false
        ? date("M j, Y", $timestamp)
        : "Not recorded";
}

/*
|--------------------------------------------------------------------------
| Get staff member's assigned health center
|--------------------------------------------------------------------------
*/
$username = $_SESSION["username"];

$staffStmt = $conn->prepare(
    "SELECT health_center_id
     FROM patients
     WHERE username = ?
     LIMIT 1"
);

if (!$staffStmt) {
    die("Unable to prepare staff lookup.");
}

$staffStmt->bind_param("s", $username);
$staffStmt->execute();

$staffResult = $staffStmt->get_result();
$staff = $staffResult->fetch_assoc();
$staffStmt->close();

if (!$staff || empty($staff["health_center_id"])) {
    die("No health center is assigned to this staff account.");
}

$centerId = (int) $staff["health_center_id"];

/*
|--------------------------------------------------------------------------
| Report date range
|--------------------------------------------------------------------------
*/
$startDate = $_GET["start_date"] ?? date("Y-m-01");
$endDate = $_GET["end_date"] ?? date("Y-m-d");

$startObject = DateTimeImmutable::createFromFormat("!Y-m-d", $startDate);
$endObject = DateTimeImmutable::createFromFormat("!Y-m-d", $endDate);

if (
    !$startObject ||
    !$endObject ||
    $startObject->format("Y-m-d") !== $startDate ||
    $endObject->format("Y-m-d") !== $endDate ||
    $startDate > $endDate
) {
    die("Invalid report date range.");
}

$startDateTime = $startDate . " 00:00:00";
$endDateTime = $endDate . " 23:59:59";
$nowDateTime = date("Y-m-d H:i:s");

/*
|--------------------------------------------------------------------------
| Get health center information
|--------------------------------------------------------------------------
*/
$centerStmt = $conn->prepare(
    "SELECT name, address, contact_number
     FROM health_centers
     WHERE id = ?
     LIMIT 1"
);

$centerStmt->bind_param("i", $centerId);
$centerStmt->execute();

$centerResult = $centerStmt->get_result();
$center = $centerResult->fetch_assoc();
$centerStmt->close();

if (!$center) {
    die("Health center information could not be found.");
}

/*
|--------------------------------------------------------------------------
| Get medicines, including acquisition and expiration dates
|--------------------------------------------------------------------------
*/
$medicineStmt = $conn->prepare(
    "SELECT
        id,
        name,
        unit,
        stock_quantity,
        reorder_threshold,
        acquisition_date,
        expiration_date
     FROM medicines
     WHERE health_center_id = ?
     ORDER BY name ASC"
);

$medicineStmt->bind_param("i", $centerId);
$medicineStmt->execute();

$medicineResult = $medicineStmt->get_result();

$items = [];
$medicineIds = [];

while ($row = $medicineResult->fetch_assoc()) {
    $id = (int) $row["id"];

    $items[$id] = [
        "id" => $id,
        "name" => $row["name"],
        "unit" => $row["unit"],
        "current_stock" => (int) $row["stock_quantity"],
        "reorder_threshold" => (int) $row["reorder_threshold"],
        "acquisition_date" => $row["acquisition_date"],
        "expiration_date" => $row["expiration_date"],

        "opening_balance" => (int) $row["stock_quantity"],
        "received" => 0,
        "issued" => 0,
        "adjustments" => 0,
        "period_net_change" => 0,
        "ending_balance" => (int) $row["stock_quantity"],

        "expiration_status" => "Not Recorded",
        "days_until_expiry" => null,
        "stock_status" => "In Stock",
        "record_warning" => false
    ];

    $medicineIds[] = $id;
}

$medicineStmt->close();

/*
|--------------------------------------------------------------------------
| Reconstruct balances from medicine stock movements
|--------------------------------------------------------------------------
|
| RESTOCK: positive quantity
| ISSUE: positive quantity, deducted from stock
| INITIAL / ADJUSTMENT: stock_after - stock_before
|
| This calculation assumes stock movement records are complete and accurate.
|--------------------------------------------------------------------------
*/
if (!empty($medicineIds)) {
    $movementStmt = $conn->prepare(
        "SELECT
            medicine_id,
            movement_type,
            quantity,
            stock_before,
            stock_after,
            created_at
         FROM medicine_stock_movements
         WHERE health_center_id = ?
           AND created_at >= ?
           AND created_at <= ?
         ORDER BY created_at ASC, id ASC"
    );

    if (!$movementStmt) {
        die(
            "Unable to prepare inventory movement query. " .
            "Check that the medicine_stock_movements table exists."
        );
    }

    $movementStmt->bind_param(
        "iss",
        $centerId,
        $startDateTime,
        $nowDateTime
    );

    $movementStmt->execute();
    $movementResult = $movementStmt->get_result();

    while ($movement = $movementResult->fetch_assoc()) {
        $medicineId = (int) $movement["medicine_id"];

        if (!isset($items[$medicineId])) {
            continue;
        }

        $type = $movement["movement_type"];
        $quantity = (int) $movement["quantity"];
        $before = (int) $movement["stock_before"];
        $after = (int) $movement["stock_after"];

        if ($type === "RESTOCK") {
            $delta = $quantity;
        } elseif ($type === "ISSUE") {
            $delta = -$quantity;
        } else {
            $delta = $after - $before;
        }

        /*
         * Reverse all changes from the report start date through now
         * to estimate the opening balance at the start of the period.
         */
        $items[$medicineId]["opening_balance"] -= $delta;

        /*
         * Only include movements inside the requested reporting period
         * in the received, issued, and adjustment totals.
         */
        $movementDate = substr($movement["created_at"], 0, 10);

        if ($movementDate < $startDate || $movementDate > $endDate) {
            continue;
        }

        $items[$medicineId]["period_net_change"] += $delta;

        if ($type === "RESTOCK") {
            $items[$medicineId]["received"] += $quantity;
        } elseif ($type === "ISSUE") {
            $items[$medicineId]["issued"] += $quantity;
        } else {
            $items[$medicineId]["adjustments"] += $delta;
        }
    }

    $movementStmt->close();
}

/*
|--------------------------------------------------------------------------
| Calculate ending balances and statuses
|--------------------------------------------------------------------------
*/
$today = new DateTimeImmutable("today");

$totalMedicines = count($items);
$lowStockCount = 0;
$expiredCount = 0;
$expiringSoonCount = 0;
$recordWarningCount = 0;

foreach ($items as &$item) {
    $item["ending_balance"] =
        $item["opening_balance"] + $item["period_net_change"];

    /*
     * Compare reconstructed ending balance with the actual current
     * stock. Differences may indicate missing or inconsistent logs.
     */
    if ($item["ending_balance"] !== $item["current_stock"]) {
        $item["record_warning"] = true;
        $recordWarningCount++;
    }

    if (
        $item["opening_balance"] < 0 ||
        $item["ending_balance"] < 0
    ) {
        $item["record_warning"] = true;
    }

    /*
     * Stock status
     */
    if ($item["current_stock"] <= $item["reorder_threshold"]) {
        $item["stock_status"] = "Low Stock";
        $lowStockCount++;
    } else {
        $item["stock_status"] = "In Stock";
    }

    /*
     * Expiration status
     */
    if (empty($item["expiration_date"])) {
        $item["expiration_status"] = "Not Recorded";
    } else {
        $expiryDate = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $item["expiration_date"]
        );

        if (!$expiryDate) {
            $item["expiration_status"] = "Invalid Date";
        } else {
            $daysRemaining = (int) $today
                ->diff($expiryDate)
                ->format("%r%a");

            $item["days_until_expiry"] = $daysRemaining;

            if ($daysRemaining < 0) {
                $item["expiration_status"] = "Expired";
                $expiredCount++;
            } elseif ($daysRemaining === 0) {
                $item["expiration_status"] = "Expires Today";
                $expiringSoonCount++;
            } elseif ($daysRemaining <= 90) {
                $item["expiration_status"] = "Expiring Soon";
                $expiringSoonCount++;
            } else {
                $item["expiration_status"] = "Valid";
            }
        }
    }
}

unset($item);

/*
|--------------------------------------------------------------------------
| Summary totals
|--------------------------------------------------------------------------
*/
$totalReceived = 0;
$totalIssued = 0;

foreach ($items as $item) {
    $totalReceived += $item["received"];
    $totalIssued += $item["issued"];
}

$centerName = $center["name"] ?? "Health Center";
$centerAddress = $center["address"] ?? "";
$centerContact = $center["contact_number"] ?? "";
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Medicine Stockpile Report | <?= h($centerName) ?></title>

    <link rel="stylesheet" href="homepagestyle.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600&display=swap"
        rel="stylesheet">

    <style>
        :root {
            -teal: var(--teal-900, #174e47);
            -sage: var(--sage-100, #e8f0eb);
            -bg: var(--sage-50, #f4f7f4);
            -ink: var(--ink, #25332e);
            -muted: var(--ink-soft, #6b7973);
            -line: var(--line, #dce5df);
            -gold: var(--gold, #edc56b);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px 16px;
            background: var(--sage-100, #e7eee7);
            color: var(--ink, #263432);
            font-family: "Inter", sans-serif;
        }

        .report-wrap {
            max-width: 1450px;
            margin: 0 auto;
        }

        .report-card {
            background: #fff;
            border-radius: 18px;
            overflow: hidden;
            box-shadow: 0 12px 36px rgba(20, 50, 45, 0.10);
        }

        .report-header {
            background: var(--teal-900, #164e4a);
            color: #fff;
            padding: 32px;
            border-bottom: 5px solid var(--gold, #d6ad58);
        }

        .report-header h1 {
            font-family: "Fraunces", serif;
            font-size: clamp(25px, 3vw, 36px);
            line-height: 1.2;
            margin: 0 0 10px;
        }

        .report-header p {
            margin: 5px 0;
            color: rgba(255, 255, 255, 0.88);
            overflow-wrap: anywhere;
        }

        .report-body {
            padding: 28px;
        }


        .report-toolbar {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .date-form {
            display: flex;
            align-items: flex-end;
            gap: 12px;
            flex-wrap: wrap;
            margin: 0;
        }

        .report-actions {
            display: flex;
            align-items: flex-end;
            gap: 10px;
            flex-wrap: wrap;
        }

        .date-form .field {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .date-form input {
            min-height: 42px;
        }

        .report-toolbar .button {
            min-height: 42px;
            white-space: nowrap;
        }

        @media (max-width: 900px) {
            .report-toolbar {
                align-items: flex-start;
            }

            .date-form,
            .report-actions {
                width: 100%;
            }
        }

        @media (max-width: 520px) {
            .date-form {
                display: grid;
                grid-template-columns: 1fr 1fr;
                width: 100%;
            }

            .date-form .button {
                grid-column: 1 / -1;
            }

            .report-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .report-actions .button {
                width: 100%;
            }
        }


        .date-form {
            display: flex;
            align-items: end;
            gap: 12px;
            flex-wrap: wrap;
        }

        .field {
            display: grid;
            gap: 6px;
        }

        .field label {
            font-size: 12px;
            font-weight: 700;
            color: var(--ink-soft, #64736f);
        }

        .field input {
            border: 1px solid var(--line, #dce5df);
            border-radius: 9px;
            padding: 10px 12px;
            font: inherit;
            color: var(--ink, #263432);
            background: #fff;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 0;
            border-radius: 9px;
            padding: 11px 16px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }

        .button-primary {
            background: var(--teal-900, #164e4a);
            color: #fff;
        }

        .button-gold {
            background: var(--gold, #d6ad58);
            color: #243431;
        }

        .button-light {
            background: var(--sage-50, #f5f8f4);
            color: var(--teal-900, #164e4a);
            border: 1px solid var(--line, #dce5df);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 28px;
        }

        .summary-card {
            background: var(--sage-50, #f5f8f4);
            border: 1px solid var(--line, #dce5df);
            border-radius: 12px;
            padding: 18px;
            min-width: 0;
        }

        .summary-label {
            font-size: 12px;
            color: var(--ink-soft, #64736f);
            font-weight: 600;
            margin-bottom: 8px;
        }

        .summary-value {
            font-size: 25px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .section-title {
            font-family: "Fraunces", serif;
            font-size: 23px;
            margin: 0 0 8px;
        }

        .section-subtitle {
            color: var(--ink-soft, #64736f);
            font-size: 13px;
            line-height: 1.6;
            margin: 0 0 18px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--line, #dce5df);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            min-width: 1250px;
        }

        thead {
            background: var(--sage-100, #e7eee7);
        }

        th,
        td {
            padding: 12px 10px;
            text-align: left;
            border-bottom: 1px solid var(--line, #dce5df);
            vertical-align: top;
        }

        th {
            color: var(--teal-900, #164e4a);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.035em;
            white-space: nowrap;
        }

        td {
            overflow-wrap: anywhere;
        }

        tbody tr:last-child td {
            border-bottom: 0;
        }

        .status {
            display: inline-block;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 700;
            white-space: nowrap;
        }

        .status-good {
            color: var(--success, #26734d);
            background: #e4f4ea;
        }

        .status-warning {
            color: var(--warning, #9a6500);
            background: #fff1cf;
        }

        .status-danger {
            color: var(--danger, #a83232);
            background: #fbe7e7;
        }

        .status-neutral {
            color: #5b6561;
            background: #edf0ee;
        }

        .notes {
            margin-top: 24px;
            padding: 18px;
            border: 1px solid var(--line, #dce5df);
            border-radius: 12px;
            background: var(--sage-50, #f5f8f4);
            font-size: 12px;
            line-height: 1.7;
        }

        .notes h3 {
            margin: 0 0 8px;
            font-size: 14px;
        }

        .notes ul {
            padding-left: 20px;
            margin-bottom: 0;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 50px;
            margin-top: 55px;
        }

        .signature-line {
            padding-top: 8px;
            border-top: 1px solid #6d7974;
            font-size: 12px;
            color: var(--ink-soft, #64736f);
        }

        .report-footer {
            text-align: center;
            padding: 20px;
            font-size: 11px;
            color: var(--ink-soft, #64736f);
            border-top: 1px solid var(--line, #dce5df);
        }

        @media (max-width: 850px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .report-header,
            .report-body {
                padding: 20px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 12px 8px;
            }

            .summary-grid {
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .summary-card {
                padding: 12px;
            }

            .summary-value {
                font-size: 20px;
            }

            .signature-grid {
                grid-template-columns: 1fr;
                gap: 35px;
            }
        }

        @media print {
            @page {
                size: landscape;
                margin: 12mm;
            }

            body {
                background: #fff !important;
                padding: 0;
                color: #222;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }

            .report-wrap {
                max-width: none;
            }

            .report-card {
                box-shadow: none;
                border-radius: 0;
            }

            .no-print {
                display: none !important;
            }

            .report-header {
                padding: 18px 22px;
            }

            .report-body {
                padding: 18px 0;
            }

            .summary-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 8px;
            }

            .summary-card {
                padding: 10px;
                break-inside: avoid;
            }

            .summary-value {
                font-size: 18px;
            }

            .table-wrap {
                overflow: visible;
                border-radius: 0;
            }

            table {
                min-width: 0;
                width: 100%;
                table-layout: auto;
                font-size: 8px;
            }

            th,
            td {
                padding: 6px 4px;
                overflow-wrap: anywhere;
            }

            th {
                white-space: normal;
                font-size: 7px;
            }

            tr {
                break-inside: avoid;
            }

            .status {
                white-space: normal;
                padding: 3px 4px;
                font-size: 7px;
            }

            .notes {
                break-inside: avoid;
            }

            .signature-grid {
                break-inside: avoid;
            }

            .report-footer {
                padding: 12px 0;
            }
        }
    </style>
</head>

<body>
    <div class="report-wrap">
        <div class="report-card">

            <header class="report-header">
                <h1>Medicine Stockpile Report</h1>

                <p><strong><?= h($centerName) ?></strong></p>

                <?php if ($centerAddress !== ""): ?>
                    <p><?= h($centerAddress) ?></p>
                <?php endif; ?>

                <?php if ($centerContact !== ""): ?>
                    <p>Contact: <?= h($centerContact) ?></p>
                <?php endif; ?>

                <p>
                    Reporting Period:
                    <?= h(displayDate($startDate)) ?>
                    to
                    <?= h(displayDate($endDate)) ?>
                </p>

                <p>
                    Generated on <?= h(date("F j, Y, g:i A")) ?>
                </p>
            </header>

            <main class="report-body">

                <div class="report-toolbar no-print">
                    <form method="GET" class="date-form">
                        <div class="field">
                            <label for="start_date">Start Date</label>
                            <input type="date" id="start_date" name="start_date" value="<?= h($startDate) ?>"
                                max="<?= h($endDate) ?>" required>
                        </div>

                        <div class="field">
                            <label for="end_date">End Date</label>
                            <input type="date" id="end_date" name="end_date" value="<?= h($endDate) ?>"
                                max="<?= h(date("Y-m-d")) ?>" required>
                        </div>

                        <button type="submit" class="button button-primary">
                            Generate Report
                        </button>
                    </form>

                    <div style="display:flex; gap:10px; flex-wrap:wrap;">
                        <button type="button" class="button button-gold" onclick="window.print()">
                            Print / Save as PDF
                        </button>

                        <a href="staff_dashboard.php?tab=inventory" class="button button-light">
                            Back to Inventory
                        </a>
                    </div>
                </div>

                <section class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total Medicine Types</div>
                        <div class="summary-value"><?= $totalMedicines ?></div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Total Received</div>
                        <div class="summary-value"><?= number_format($totalReceived) ?></div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Total Issued</div>
                        <div class="summary-value"><?= number_format($totalIssued) ?></div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-label">Low Stock Items</div>
                        <div class="summary-value"><?= $lowStockCount ?></div>
                    </div>
                </section>

                <section>
                    <h2 class="section-title">Inventory Details</h2>

                    <p class="section-subtitle">
                        This report includes medicine balances, stock movements,
                        acquisition dates, expiration dates, and current stock
                        and expiration status.
                    </p>

                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Medicine</th>
                                    <th>Unit</th>
                                    <th>Acquisition Date</th>
                                    <th>Expiration Date</th>
                                    <th>Expiration Status</th>
                                    <th>Opening Balance</th>
                                    <th>Received</th>
                                    <th>Issued</th>
                                    <th>Adjustments</th>
                                    <th>Ending Balance</th>
                                    <th>Current Stock</th>
                                    <th>Reorder Threshold</th>
                                    <th>Stock Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="13">
                                            No medicines were found for this health center.
                                        </td>
                                    </tr>
                                <?php else: ?>

                                    <?php foreach ($items as $item): ?>
                                        <?php
                                        $expiryStatus = $item["expiration_status"];

                                        if ($expiryStatus === "Expired") {
                                            $expiryClass = "status-danger";
                                        } elseif (
                                            $expiryStatus === "Expires Today" ||
                                            $expiryStatus === "Expiring Soon"
                                        ) {
                                            $expiryClass = "status-warning";
                                        } elseif ($expiryStatus === "Valid") {
                                            $expiryClass = "status-good";
                                        } else {
                                            $expiryClass = "status-neutral";
                                        }

                                        $stockClass =
                                            $item["stock_status"] === "Low Stock"
                                            ? "status-warning"
                                            : "status-good";
                                        ?>

                                        <tr>
                                            <td><strong><?= h($item["name"]) ?></strong></td>

                                            <td><?= h($item["unit"]) ?></td>

                                            <td>
                                                <?= h(displayDate($item["acquisition_date"])) ?>
                                            </td>

                                            <td>
                                                <?= h(displayDate($item["expiration_date"])) ?>
                                            </td>

                                            <td>
                                                <span class="status <?= h($expiryClass) ?>">
                                                    <?= h($expiryStatus) ?>
                                                </span>

                                                <?php if ($item["days_until_expiry"] !== null): ?>
                                                    <br>
                                                    <small>
                                                        <?php if ($item["days_until_expiry"] < 0): ?>
                                                            Expired
                                                            <?= abs($item["days_until_expiry"]) ?>
                                                            day(s) ago
                                                        <?php elseif ($item["days_until_expiry"] === 0): ?>
                                                            Expires today
                                                        <?php else: ?>
                                                            <?= $item["days_until_expiry"] ?>
                                                            day(s) remaining
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>

                                            <td><?= number_format($item["opening_balance"]) ?></td>

                                            <td><?= number_format($item["received"]) ?></td>

                                            <td><?= number_format($item["issued"]) ?></td>

                                            <td><?= number_format($item["adjustments"]) ?></td>

                                            <td><?= number_format($item["ending_balance"]) ?></td>

                                            <td><?= number_format($item["current_stock"]) ?></td>

                                            <td><?= number_format($item["reorder_threshold"]) ?></td>

                                            <td>
                                                <span class="status <?= h($stockClass) ?>">
                                                    <?= h($item["stock_status"]) ?>
                                                </span>

                                                <?php if ($item["record_warning"]): ?>
                                                    <br>
                                                    <span class="status status-danger">
                                                        Review Records
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="notes">
                    <h3>Report Notes</h3>

                    <ul>
                        <li>
                            <strong>Acquisition Date:</strong>
                            The recorded date the medicine was acquired.
                        </li>
                        <li>
                            <strong>Expiration Date:</strong>
                            The recorded date after which the medicine should not
                            be used, following applicable health center policies.
                        </li>
                        <li>
                            <strong>Expiring Soon:</strong>
                            Medicines with 90 days or fewer remaining before expiration.
                        </li>
                        <li>
                            <strong>Received:</strong>
                            Total quantities recorded as restocked during the
                            selected reporting period.
                        </li>
                        <li>
                            <strong>Issued:</strong>
                            Total quantities recorded as issued during the
                            selected reporting period.
                        </li>
                        <li>
                            <strong>Ending Balance:</strong>
                            Estimated stock at the end of the reporting period,
                            calculated from the opening balance and recorded
                            movements during that period.
                        </li>
                        <li>
                            <strong>Review Records:</strong>
                            The reconstructed balance does not match the current
                            stock or a negative balance was detected. Review the
                            movement history and inventory records.
                        </li>
                        <li>
                            Missing acquisition or expiration dates are shown as
                            “Not recorded.” Verify these details against the
                            medicine packaging and acquisition documents.
                        </li>
                    </ul>
                </section>

                <div class="signature-grid">
                    <div class="signature-line">
                        Prepared by: Health Center Staff
                    </div>

                    <div class="signature-line">
                        Reviewed by: Authorized Personnel
                    </div>
                </div>

            </main>

            <footer class="report-footer">
                Medicine Inventory and Supply Forecasting System
                <br>
                <?= h($centerName) ?>
            </footer>
        </div>
    </div>
</body>

</html>