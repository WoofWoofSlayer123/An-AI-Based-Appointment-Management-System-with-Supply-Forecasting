<?php
session_start();
include "connection.php";
include "auth_check.php";
include "forecast_helper.php";

// Front desk staff only — doctors have their own dashboard now.
if ($_SESSION["role"] !== "staff") {
  if ($_SESSION["role"] === "patient") {
    header("Location: dashboard.php");
  } elseif ($_SESSION["role"] === "admin") {
    header("Location: admin_dashboard.php");
  } elseif ($_SESSION["role"] === "doctor") {
    header("Location: doctor_dashboard.php");
  }
  exit;
}

$username = $_SESSION["username"];

$stmt = $conn->prepare("SELECT * FROM patients WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$staff = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmtUser = $conn->prepare("SELECT email FROM users WHERE username = ?");
$stmtUser->bind_param("s", $username);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

$centerId = $staff["health_center_id"];
if (!$centerId) {
  die("Your account has no assigned health center yet. Please contact your system administrator.");
}

$stmt = $conn->prepare("SELECT name FROM health_centers WHERE id = ?");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$centerName = $stmt->get_result()->fetch_assoc()["name"] ?? "Unknown center";
$stmt->close();

// Doctors at this center — used to populate the "assign doctor" dropdown
// whenever staff confirms a pending appointment.
$stmt = $conn->prepare("
    SELECT users.username, patients.full_name
    FROM users
    JOIN patients ON patients.username = users.username
    WHERE users.role = 'doctor' AND patients.health_center_id = ?
    ORDER BY patients.full_name
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$doctorsResult = $stmt->get_result();
$doctors = [];
while ($d = $doctorsResult->fetch_assoc())
  $doctors[] = $d;

// Pending requests — need a decision (confirm + assign doctor, or reject)
$stmt = $conn->prepare("
    SELECT appointments.*, patients.full_name
    FROM appointments
    JOIN patients ON patients.username = appointments.username
    WHERE appointments.health_center_id = ? AND appointments.status = 'Pending'
    ORDER BY appointments.appointment_date, appointments.appointment_time
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$pending = $stmt->get_result();

// Today's confirmed schedule
$stmt = $conn->prepare("
    SELECT appointments.*, patients.full_name
    FROM appointments
    JOIN patients ON patients.username = appointments.username
    WHERE appointments.health_center_id = ? AND appointments.status = 'Confirmed'
          AND appointments.appointment_date = CURDATE()
    ORDER BY appointments.appointment_time
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$today = $stmt->get_result();

// Full schedule for the Schedule tab (everything not yet completed/cancelled/rejected)
$stmt = $conn->prepare("
    SELECT appointments.*, patients.full_name
    FROM appointments
    JOIN patients ON patients.username = appointments.username
    WHERE appointments.health_center_id = ?
          AND appointments.status IN ('Pending','Confirmed')
    ORDER BY appointments.appointment_date, appointments.appointment_time
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$schedule = $stmt->get_result();

// History for this center
$stmt = $conn->prepare("
    SELECT appointments.*, patients.full_name
    FROM appointments
    JOIN patients ON patients.username = appointments.username
    WHERE appointments.health_center_id = ?
          AND appointments.status IN ('Completed','Cancelled','Rejected')
    ORDER BY appointments.appointment_date DESC, appointments.appointment_time DESC
    LIMIT 30
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$history = $stmt->get_result();

// Medicine inventory for this center
$stmt = $conn->prepare("SELECT * FROM medicines WHERE health_center_id = ? ORDER BY name");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$medicines = $stmt->get_result();

$stmt = $conn->prepare("SELECT COUNT(*) AS low FROM medicines WHERE health_center_id = ? AND stock_quantity <= reorder_threshold");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$lowStockCount = $stmt->get_result()->fetch_assoc()["low"];
$stmt->close();

// Medicine names for the Home dashboard alert panel.
$stmt = $conn->prepare("
    SELECT name, stock_quantity, unit, reorder_threshold
    FROM medicines
    WHERE health_center_id = ? AND stock_quantity <= reorder_threshold
    ORDER BY stock_quantity ASC, name ASC
    LIMIT 5
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$lowStockMedicinesResult = $stmt->get_result();
$lowStockMedicines = [];
while ($medicineAlert = $lowStockMedicinesResult->fetch_assoc()) {
    $lowStockMedicines[] = $medicineAlert;
}
$stmt->close();

$forecast = get_medicine_forecast($conn, $centerId);
$criticalCount = count(array_filter($forecast, fn($f) => $f["status"] === "critical"));

// ---------------- REPORTS DATA ----------------

// Appointments per day, last 7 days (filled with 0 for days with none).
$apptsByDay = [];
for ($i = 6; $i >= 0; $i--) {
  $d = date("Y-m-d", strtotime("-$i days"));
  $apptsByDay[$d] = 0;
}
$stmt = $conn->prepare("
    SELECT appointment_date, COUNT(*) AS cnt FROM appointments
    WHERE health_center_id = ? AND appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
          AND status NOT IN ('Cancelled','Rejected')
    GROUP BY appointment_date
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc())
  $apptsByDay[$row["appointment_date"]] = (int) $row["cnt"];

// Medicine stock snapshot for the chart
$stmt = $conn->prepare("SELECT name, stock_quantity FROM medicines WHERE health_center_id = ? ORDER BY stock_quantity DESC LIMIT 10");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$stockChartResult = $stmt->get_result();
$stockLabels = [];
$stockValues = [];
while ($row = $stockChartResult->fetch_assoc()) {
  $stockLabels[] = $row["name"];
  $stockValues[] = (int) $row["stock_quantity"];
}

// Most utilized service, top 5, all-time
$stmt = $conn->prepare("
    SELECT service, COUNT(*) AS cnt FROM appointments
    WHERE health_center_id = ? AND status NOT IN ('Cancelled','Rejected')
    GROUP BY service ORDER BY cnt DESC LIMIT 5
");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$serviceResult = $stmt->get_result();
$serviceLabels = [];
$serviceValues = [];
while ($row = $serviceResult->fetch_assoc()) {
  $serviceLabels[] = $row["service"];
  $serviceValues[] = (int) $row["cnt"];
}

$statusMsg = $_GET["msg"] ?? null;
$statusType = $_GET["type"] ?? "info";
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Staff Dashboard — Malabon District II Health Centers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="homepagestyle.css">
  <link rel="stylesheet" href="patient_style.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>

<body class="dashboard-body">

  <div class="dash-shell">

    <aside class="sidebar">
      <div class="sidebar-brand"><span class="dot"></span> MALABON DISTRICT II</div>
      <div class="sidebar-user">
        <div class="sidebar-name"><?= htmlspecialchars($staff["full_name"] ?: $username) ?></div>
        <div class="sidebar-id">Front Desk Staff — <?= htmlspecialchars($centerName) ?></div>
      </div>
      <nav class="sidebar-nav">
        <a href="#" class="nav-link" data-tab="home">
          Home
          <?php if ($pending->num_rows > 0): ?><span class="nav-badge"><?= $pending->num_rows ?></span><?php endif; ?>
        </a>
        <a href="#" class="nav-link" data-tab="schedule">Schedule</a>
        <a href="#" class="nav-link" data-tab="inventory">
          Medicine Inventory
          <?php if ($lowStockCount > 0): ?><span class="nav-badge"><?= $lowStockCount ?></span><?php endif; ?>
        </a>
        <a href="#" class="nav-link" data-tab="reports">Reports</a>
        <a href="#" class="nav-link" data-tab="account">My Account</a>
      </nav>
      <a href="logout.php" class="sidebar-logout">Sign out</a>
    </aside>

    <main class="dash-main">

      <?php if ($statusMsg): ?>
        <div class="msg <?= htmlspecialchars($statusType) ?>"><?= htmlspecialchars($statusMsg) ?></div>
      <?php endif; ?>

      
<!-- HOME -->
<section class="dash-section staff-home" id="tab-home">

    <!-- Welcome Header -->
    <div class="home-welcome">
        <div class="welcome-text">
            <p class="welcome-date">
                <?= date("l, F j, Y") ?>
            </p>

            <h1 class="dash-title">
                Welcome back,
                <?= htmlspecialchars(
                    explode(" ", $staff["full_name"] ?: $username)[0],
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>
            </h1>

            <p class="welcome-center">
                <?= htmlspecialchars($centerName, ENT_QUOTES, "UTF-8") ?>
            </p>

            <p class="welcome-description">
                Here is your health center's daily overview.
            </p>
        </div>

        <div class="welcome-icon" aria-hidden="true">
            <span>+</span>
        </div>
    </div>

    <!-- Statistics -->
    <div class="home-stats">

        <a href="#tab-appointments" class="home-stat stat-pending">
            <div class="stat-icon" aria-hidden="true">
                <i class="fa-regular fa-calendar"></i>
            </div>

            <div class="stat-content">
                <span class="stat-label">Pending Requests</span>
                <strong class="stat-number">
                    <?= (int)$pending->num_rows ?>
                </strong>
                <span class="stat-caption">Awaiting decision</span>
            </div>

            <span class="stat-arrow" aria-hidden="true">&rsaquo;</span>
        </a>
        <a href="#tab-appointments" class="home-stat stat-visits" data-tab="schedule">
            <div class="stat-icon" aria-hidden="true">
                <i class="fa-regular fa-calendar-check"></i>
            </div>

            <div class="stat-content">
                <span class="stat-label">Today's Confirmed Visits</span>
                <strong class="stat-number">
                    <?= (int)$today->num_rows ?>
                </strong>
                <span class="stat-caption">Scheduled for today</span>
            </div>

            <span class="stat-arrow" aria-hidden="true">&rsaquo;</span>
        </a>
        <a href="#tab-inventory" class="home-stat stat-stock" data-tab="inventory">
            <div class="stat-icon" aria-hidden="true">
                <i class="fa-solid fa-pills"></i>
            </div>

            <div class="stat-content">
                <span class="stat-label">Low Stock Medicines</span>
                <strong class="stat-number">
                    <?= (int)$lowStockCount ?>
                </strong>
                <span class="stat-caption">Check inventory</span>
            </div>

            <span class="stat-arrow" aria-hidden="true">&rsaquo;</span>
        </a>

    </div>

    <!-- Dashboard content: schedule on the left, requests and medicine alerts on the right -->
    <div class="home-dashboard-grid">

        <!-- LEFT SIDE: Today's Schedule -->
        <div class="home-dashboard-left">
    <!-- Today's Schedule -->
    <div class="dash-card home-panel home-schedule-panel">

        <div class="home-panel-header">
            <div>
                <h2 class="card-heading">Today's Schedule</h2>
                <p class="home-panel-subtitle">
                    Confirmed appointments for <?= date("F j, Y") ?>.
                </p>
            </div>

            <span class="home-count home-count-teal">
                <?= (int)$today->num_rows ?> visits
            </span>
        </div>

        <div class="home-table-wrap">
            <table class="dash-table home-table">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Service</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($today->num_rows > 0): ?>
                        <?php while ($row = $today->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="home-time">
                                        <?= date(
                                            "g:i A",
                                            strtotime($row["appointment_time"])
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="home-patient-name">
                                        <?= htmlspecialchars(
                                            $row["full_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["service"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="3">
                                <div class="home-empty home-empty-compact">
                                    <i
                                        class="fa-regular fa-calendar"
                                        aria-hidden="true"
                                    ></i>
                                    <strong>No confirmed visits today</strong>
                                    <span>Your schedule is clear for now.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>


        </div>

        <!-- RIGHT SIDE: Pending Requests and Medicine Alerts -->
        <div class="home-dashboard-right">
    <!-- Pending Appointment Requests -->
    <div class="dash-card home-panel">

        <div class="home-panel-header">
            <div>
                <h2 class="card-heading">
                    Pending Appointment Requests
                </h2>
                <p class="home-panel-subtitle">
                    Review requests and assign a doctor before confirming.
                </p>
            </div>

            <span class="home-count">
                <?= (int)$pending->num_rows ?> pending
            </span>
        </div>

        <?php if (empty($doctors)): ?>
            <div class="home-notice">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>
                    No doctors are assigned to this center yet.
                    An administrator needs to assign doctors before staff
                    can confirm appointment requests.
                </span>
            </div>
        <?php endif; ?>

        <div class="home-table-wrap">
            <table class="dash-table home-table">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Service</th>
                        <th class="home-action-heading">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($pending->num_rows > 0): ?>
                        <?php while ($row = $pending->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <span class="home-patient-name">
                                        <?= htmlspecialchars(
                                            $row["full_name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= date(
                                        "M j, Y",
                                        strtotime($row["appointment_date"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= date(
                                        "g:i A",
                                        strtotime($row["appointment_time"])
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["service"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td class="home-actions-cell">
                                    <div class="home-action-group">

                                        <form
                                            action="update_appointment_status.php"
                                            method="POST"
                                            class="home-confirm-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="Confirmed"
                                            >

                                            <select
                                                name="assigned_doctor"
                                                aria-label="Assign doctor"
                                                required
                                                <?= empty($doctors) ? "disabled" : "" ?>
                                            >
                                                <option value="">
                                                    Assign doctor
                                                </option>

                                                <?php foreach ($doctors as $d): ?>
                                                    <option
                                                        value="<?= htmlspecialchars(
                                                            $d["username"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>"
                                                    >
                                                        <?= htmlspecialchars(
                                                            $d["full_name"],
                                                            ENT_QUOTES,
                                                            "UTF-8"
                                                        ) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button
                                                type="submit"
                                                class="home-btn home-btn-confirm"
                                                <?= empty($doctors) ? "disabled" : "" ?>
                                            >
                                                Confirm
                                            </button>
                                        </form>

                                        <form
                                            action="update_appointment_status.php"
                                            method="POST"
                                            class="home-reject-form"
                                            onsubmit="return confirm('Reject this appointment request?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int)$row["id"] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="status"
                                                value="Rejected"
                                            >

                                            <button
                                                type="submit"
                                                class="home-btn home-btn-reject"
                                            >
                                                Reject
                                            </button>
                                        </form>

                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="5">
                                <div class="home-empty">
                                    <i
                                        class="fa-regular fa-calendar-check"
                                        aria-hidden="true"
                                    ></i>
                                    <strong>No pending requests</strong>
                                    <span>New appointment requests will appear here.</span>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

        <!-- Medicine Alerts -->
        <div class="dash-card home-panel home-medicine-alerts">
            <?php
            $homeForecastAlerts = array_values(array_filter(
                $forecast,
                fn($f) => in_array($f["status"], ["critical", "soon"], true)
            ));
            ?>
            <div class="home-panel-header">
                <div>
                    <h2 class="card-heading">Medicine Alerts</h2>
                    <p class="home-panel-subtitle">Low stock and predicted shortages.</p>
                </div>
                <span class="home-count <?= (!empty($lowStockMedicines) || !empty($homeForecastAlerts)) ? '' : 'home-count-teal' ?>">
                    <?= (int)(count($lowStockMedicines) + count($homeForecastAlerts)) ?> alerts
                </span>
            </div>

            <?php if (empty($lowStockMedicines) && empty($homeForecastAlerts)): ?>
                <div class="home-empty home-empty-compact">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <strong>No medicine alerts</strong>
                    <span>There are no low-stock medicines or predicted shortages right now.</span>
                </div>
            <?php else: ?>
                <div class="home-alert-list">
                    <?php foreach ($lowStockMedicines as $medicine): ?>
                        <div class="home-alert-item">
                            <span class="home-alert-icon home-alert-icon-low">
                                <i class="fa-solid fa-pills" aria-hidden="true"></i>
                            </span>
                            <div class="home-alert-content">
                                <strong><?= htmlspecialchars($medicine["name"], ENT_QUOTES, "UTF-8") ?></strong>
                                <span class="home-alert-status home-alert-status-low">Low stock</span>
                                <small>
                                    <?= (int)$medicine["stock_quantity"] ?>
                                    <?= htmlspecialchars($medicine["unit"], ENT_QUOTES, "UTF-8") ?> remaining
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($homeForecastAlerts as $alert): ?>
                        <?php
                        // Avoid listing a medicine twice if it is both low-stock and forecast-critical.
                        $alreadyListedAsLow = false;
                        foreach ($lowStockMedicines as $lowMedicine) {
                            if (strcasecmp($lowMedicine["name"], $alert["name"]) === 0) {
                                $alreadyListedAsLow = true;
                                break;
                            }
                        }
                        ?>
                        <?php if (!$alreadyListedAsLow): ?>
                            <div class="home-alert-item">
                                <span class="home-alert-icon <?= $alert["status"] === "critical" ? "home-alert-icon-low" : "home-alert-icon-forecast" ?>">
                                    <i class="fa-solid fa-chart-line" aria-hidden="true"></i>
                                </span>
                                <div class="home-alert-content">
                                    <strong><?= htmlspecialchars($alert["name"], ENT_QUOTES, "UTF-8") ?></strong>
                                    <span class="home-alert-status <?= $alert["status"] === "critical" ? "home-alert-status-low" : "home-alert-status-forecast" ?>">
                                        <?= $alert["status"] === "critical" ? "Critical forecast" : "Forecast shortage" ?>
                                    </span>
                                    <small>
                                        <?= $alert["days_remaining"] !== null
                                            ? (int)$alert["days_remaining"] . " estimated days remaining"
                                            : "Review supply forecast" ?>
                                    </small>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a href="#tab-inventory" class="home-alert-link" data-tab="inventory">
                View medicine inventory <span aria-hidden="true">&rsaquo;</span>
            </a>
        </div>

        </div>

    </div>

</section>


      <!-- SCHEDULE -->
      <section class="dash-section" id="tab-schedule">
        <div class="schedule-page-header">
          <h1 class="dash-title">Appointment Schedule</h1>
          <p class="dash-sub">Browse pending and confirmed appointments, then review previous appointment records for <?= htmlspecialchars($centerName, ENT_QUOTES, "UTF-8") ?>.</p>
        </div>
        <div class="dash-card schedule-card">
          <div class="schedule-card-header">
            <div><h2 class="card-heading">Upcoming Appointments</h2><p class="hint">Pending requests and confirmed visits at this health center.</p></div>
            <label class="schedule-search-wrap"><span class="sr-only">Search upcoming appointments</span><input type="search" class="schedule-search" data-table-search="schedule-table" placeholder="Search patient or service..." autocomplete="off"></label>
          </div>
          <div class="schedule-table-wrap">
            <table class="dash-table" id="schedule-table">
              <thead><tr><th>Date</th><th>Time</th><th>Patient</th><th>Service</th><th>Status</th></tr></thead>
              <tbody>
                <?php if ($schedule->num_rows > 0): ?>
                  <?php while ($row = $schedule->fetch_assoc()): ?>
                    <tr><td><?= date("M j, Y", strtotime($row["appointment_date"])) ?></td><td><?= date("g:i A", strtotime($row["appointment_time"])) ?></td><td><?= htmlspecialchars($row["full_name"], ENT_QUOTES, "UTF-8") ?></td><td><?= htmlspecialchars($row["service"], ENT_QUOTES, "UTF-8") ?></td><td><span class="status-pill status-<?= strtolower(htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8")) ?>"><?= htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8") ?></span></td></tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr class="schedule-empty-row"><td colspan="5" class="schedule-empty-state"><span class="schedule-empty-icon" aria-hidden="true">&#128197;</span><strong>No upcoming appointments</strong><span>There are currently no pending or confirmed appointments to display.</span></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
            <p class="schedule-no-results" hidden>No appointments match your search.</p>
          </div>
          <p class="hint schedule-note">To confirm or reject a pending request, open the Home tab.</p>
        </div>
        
<div class="dash-card schedule-card" id="history-card">

  <div class="schedule-card-header">
    <div>
      <h2 class="card-heading">Recent Appointment History</h2>
      <p class="hint">
        Review previously processed appointments and their recorded statuses.
      </p>
    </div>

    <button
      type="button"
      class="history-toggle-btn"
      id="history-toggle"
      aria-expanded="false"
      aria-controls="history-content"
    >
      <span id="history-toggle-text">Show history</span>
      <span class="history-toggle-icon" id="history-toggle-icon" aria-hidden="true">
        &#9662;
      </span>
    </button>
  </div>

  <div id="history-content" hidden>
    <div class="schedule-card-header history-search-header">
      <p class="hint">Showing the 30 most recent processed appointments.</p>

      <label class="schedule-search-wrap">
        <span class="sr-only">Search appointment history</span>
        <input
          type="search"
          class="schedule-search"
          data-table-search="history-table"
          placeholder="Search patient or service..."
          autocomplete="off"
        >
      </label>
    </div>

    <div class="schedule-table-wrap">
      <table class="dash-table" id="history-table">
        <thead>
          <tr>
            <th>Date</th>
            <th>Patient</th>
            <th>Service</th>
            <th>Status</th>
          </tr>
        </thead>

        <tbody>
          <?php if ($history->num_rows > 0): ?>
            <?php while ($row = $history->fetch_assoc()): ?>
              <tr>
                <td>
                  <?= date("M j, Y", strtotime($row["appointment_date"])) ?>
                </td>
                <td>
                  <?= htmlspecialchars($row["full_name"], ENT_QUOTES, "UTF-8") ?>
                </td>
                <td>
                  <?= htmlspecialchars($row["service"], ENT_QUOTES, "UTF-8") ?>
                </td>
                <td>
                  <span class="status-pill status-<?= strtolower(htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8")) ?>">
                    <?= htmlspecialchars($row["status"], ENT_QUOTES, "UTF-8") ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr class="schedule-empty-row">
              <td colspan="4" class="schedule-empty-state">
                <span class="schedule-empty-icon" aria-hidden="true">&#128203;</span>
                <strong>No appointment history yet</strong>
                <span>Processed appointments will appear here when records are available.</span>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>

      <p class="schedule-no-results" hidden>
        No history records match your search.
      </p>
    </div>
  </div>

</div>

      </section>

      <!-- MEDICINE INVENTORY -->
<section class="dash-section" id="tab-inventory">
    <div class="inventory-page-header">
        <h1 class="dash-title">Medicine Inventory</h1>
        <p class="dash-sub">
            Manage medicine stocks, monitor expiration dates, and review
            supply forecasts for <?= htmlspecialchars($centerName, ENT_QUOTES, "UTF-8") ?>.
        </p>
    </div>

    <?php
    // Inventory summary
    $totalMedicines = 0;
    $expiredCount = 0;
    $expiringSoonCount = 0;
    $totalStockUnits = 0;

    $medicineSummaryStmt = $conn->prepare("
        SELECT
            COUNT(*) AS total_medicines,
            COALESCE(SUM(stock_quantity), 0) AS total_stock
        FROM medicines
        WHERE health_center_id = ?
    ");
    $medicineSummaryStmt->bind_param("i", $centerId);
    $medicineSummaryStmt->execute();
    $medicineSummary = $medicineSummaryStmt->get_result()->fetch_assoc();
    $medicineSummaryStmt->close();

    $totalMedicines = (int)($medicineSummary["total_medicines"] ?? 0);
    $totalStockUnits = (int)($medicineSummary["total_stock"] ?? 0);

    $todayDate = new DateTimeImmutable("today");
    $expirationWarningDate = $todayDate->modify("+30 days");

    $expirationStmt = $conn->prepare("
        SELECT expiration_date
        FROM medicines
        WHERE health_center_id = ?
          AND expiration_date IS NOT NULL
          AND expiration_date <> '0000-00-00'
    ");
    $expirationStmt->bind_param("i", $centerId);
    $expirationStmt->execute();
    $expirationResult = $expirationStmt->get_result();

    while ($expirationRow = $expirationResult->fetch_assoc()) {
        $expirationDate = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $expirationRow["expiration_date"]
        );

        if ($expirationDate) {
            if ($expirationDate < $todayDate) {
                $expiredCount++;
            } elseif ($expirationDate <= $expirationWarningDate) {
                $expiringSoonCount++;
            }
        }
    }
    $expirationStmt->close();
    ?>

    <!-- Inventory Summary Cards -->
    <div class="inventory-summary-grid">
        <div class="dash-card inventory-summary-card">
            <div class="inventory-summary-icon">💊</div>
            <div>
                <p class="inventory-summary-label">Total Medicines</p>
                <h2 class="inventory-summary-value">
                    <?= $totalMedicines ?>
                </h2>
                <span class="inventory-summary-note">Registered medicines</span>
            </div>
        </div>

        <div class="dash-card inventory-summary-card">
            <div class="inventory-summary-icon">📦</div>
            <div>
                <p class="inventory-summary-label">Total Stock</p>
                <h2 class="inventory-summary-value">
                    <?= number_format($totalStockUnits) ?>
                </h2>
                <span class="inventory-summary-note">Units on hand</span>
            </div>
        </div>

        <div class="dash-card inventory-summary-card">
            <div class="inventory-summary-icon inventory-icon-warning">⚠</div>
            <div>
                <p class="inventory-summary-label">Low Stock</p>
                <h2 class="inventory-summary-value">
                    <?= (int)$lowStockCount ?>
                </h2>
                <span class="inventory-summary-note">At or below reorder threshold</span>
            </div>
        </div>

        <div class="dash-card inventory-summary-card">
            <div class="inventory-summary-icon inventory-icon-danger">!</div>
            <div>
                <p class="inventory-summary-label">Expiration Alerts</p>
                <h2 class="inventory-summary-value">
                    <?= $expiredCount + $expiringSoonCount ?>
                </h2>
                <span class="inventory-summary-note">
                    <?= $expiredCount ?> expired,
                    <?= $expiringSoonCount ?> expiring within 30 days
                </span>
            </div>
        </div>
    </div>

    <!-- Add Medicine -->
    <div class="dash-card inventory-add-card">
        <div class="card-heading-row">
            <div>
                <h2 class="card-heading">Add a Medicine</h2>
                <p class="hint">
                    Register a medicine and record its stock and expiration details.
                </p>
            </div>

            <button
                type="button"
                class="history-toggle-btn"
                id="medicine-form-toggle"
                aria-expanded="false"
                aria-controls="medicine-form-content"
            >
                <span id="medicine-form-toggle-text">Add medicine</span>
                <span id="medicine-form-toggle-icon" aria-hidden="true">&#9662;</span>
            </button>
        </div>

        <div id="medicine-form-content" hidden>
            <form action="process_medicine.php" method="POST" class="appt-form">
                <input type="hidden" name="action" value="add">

                <div class="field-row">
                    <div class="field">
                        <label for="med-name">Medicine Name</label>
                        <input
                            type="text"
                            id="med-name"
                            name="name"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="med-unit">Unit</label>
                        <input
                            type="text"
                            id="med-unit"
                            name="unit"
                            placeholder="Tablet, bottle, vial"
                            maxlength="50"
                            required
                        >
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="med-stock">Starting Stock</label>
                        <input
                            type="number"
                            id="med-stock"
                            name="stock_quantity"
                            min="0"
                            step="1"
                            required
                        >
                    </div>

                    <div class="field">
                        <label for="med-threshold">Low-Stock Threshold</label>
                        <input
                            type="number"
                            id="med-threshold"
                            name="reorder_threshold"
                            min="0"
                            step="1"
                            value="10"
                            required
                        >
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="med-acquisition-date">Acquisition Date</label>
                        <input
                            type="date"
                            id="med-acquisition-date"
                            name="acquisition_date"
                        >
                    </div>

                    <div class="field">
                        <label for="med-expiration-date">Expiration Date</label>
                        <input
                            type="date"
                            id="med-expiration-date"
                            name="expiration_date"
                        >
                    </div>
                </div>

                <button type="submit" class="btn-primary">
                    Add Medicine
                </button>
            </form>
        </div>
    </div>

    <!-- Current Stock -->
    <div class="dash-card inventory-stock-card">
        <div class="card-heading-row">
            <div>
                <h2 class="card-heading">Current Stock</h2>
                <p class="hint">
                    Monitor quantities, expiration dates, and restocking needs.
                </p>
            </div>

            <label class="schedule-search-wrap">
                <span class="sr-only">Search medicines</span>
                <input
                    type="search"
                    class="schedule-search"
                    id="medicine-search"
                    placeholder="Search medicines..."
                    autocomplete="off"
                >
            </label>
        </div>

        <div class="schedule-table-wrap">
            <table class="dash-table" id="medicine-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Acquisition Date</th>
                        <th>Expiration Date</th>
                        <th>Expiration Status</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Stock Status</th>
                        <th>Restock</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($medicines->num_rows > 0): ?>
                        <?php while ($row = $medicines->fetch_assoc()): ?>
                            <?php
                            $isLow = (int)$row["stock_quantity"]
                                <= (int)$row["reorder_threshold"];

                            $acquisitionDate = $row["acquisition_date"] ?? null;
                            $expirationDateValue = $row["expiration_date"] ?? null;

                            $formattedAcquisitionDate = "—";
                            $formattedExpirationDate = "—";
                            $expirationStatus = "No date recorded";
                            $expirationClass = "status-completed";

                            if (
                                !empty($acquisitionDate) &&
                                $acquisitionDate !== "0000-00-00"
                            ) {
                                $acquisitionObject = DateTimeImmutable::createFromFormat(
                                    "!Y-m-d",
                                    $acquisitionDate
                                );

                                if ($acquisitionObject) {
                                    $formattedAcquisitionDate =
                                        $acquisitionObject->format("M j, Y");
                                }
                            }

                            if (
                                !empty($expirationDateValue) &&
                                $expirationDateValue !== "0000-00-00"
                            ) {
                                $expirationObject = DateTimeImmutable::createFromFormat(
                                    "!Y-m-d",
                                    $expirationDateValue
                                );

                                if ($expirationObject) {
                                    $formattedExpirationDate =
                                        $expirationObject->format("M j, Y");

                                    if ($expirationObject < $todayDate) {
                                        $expirationStatus = "Expired";
                                        $expirationClass = "status-rejected";
                                    } elseif ($expirationObject <= $expirationWarningDate) {
                                        $expirationStatus = "Expiring soon";
                                        $expirationClass = "status-pending";
                                    } else {
                                        $expirationStatus = "Valid";
                                        $expirationClass = "status-confirmed";
                                    }
                                }
                            }
                            ?>

                            <tr>
                                <td>
                                    <strong>
                                        <?= htmlspecialchars(
                                            $row["name"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </strong>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $formattedAcquisitionDate,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $formattedExpirationDate,
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <span class="status-pill <?= $expirationClass ?>">
                                        <?= htmlspecialchars(
                                            $expirationStatus,
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $row["unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= (int)$row["stock_quantity"] ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php if ($isLow): ?>
                                        <span class="status-pill status-rejected">
                                            Low stock
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill status-confirmed">
                                            In stock
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <form
                                        action="process_medicine.php"
                                        method="POST"
                                        class="restock-form"
                                    >
                                        <input
                                            type="hidden"
                                            name="action"
                                            value="restock"
                                        >

                                        <input
                                            type="hidden"
                                            name="medicine_id"
                                            value="<?= (int)$row["id"] ?>"
                                        >

                                        <input
                                            type="number"
                                            name="add_quantity"
                                            min="1"
                                            step="1"
                                            placeholder="+ Qty"
                                            aria-label="Quantity to add for <?= htmlspecialchars(
                                                $row["name"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>"
                                            required
                                        >

                                        <button type="submit" class="link-btn">
                                            Add
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr class="schedule-empty-row">
                            <td colspan="8" class="schedule-empty-state">
                                <strong>No medicines registered yet</strong>
                                <span>
                                    Add a medicine to begin tracking your center's stock.
                                </span>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <p id="medicine-no-results" class="schedule-no-results" hidden>
                No medicines match your search.
            </p>
        </div>
    </div>

    <!-- Supply Forecast -->
    <?php
    $forecastChartable = array_values(
        array_filter(
            $forecast,
            fn($f) => $f["days_remaining"] !== null
        )
    );

    $forecastColorMap = [
        "critical" => "#C0392B",
        "soon" => "#E8A33D",
        "fine" => "#1E7A4C"
    ];
    ?>

    <div class="dash-card inventory-forecast-card">
        <div class="card-heading-row">
            <div>
                <h2 class="card-heading">Medicine Supply Forecast</h2>
                <p class="hint">
                    Review projected days remaining based on recent medicine
                    consumption and prescription history.
                </p>
            </div>

            <?php if ($criticalCount > 0): ?>
                <span class="status-pill status-rejected">
                    <?= (int)$criticalCount ?> critical
                </span>
            <?php endif; ?>
        </div>

        <div class="forecast-information">
            <strong>About this forecast</strong>
            <p>
                The forecasting module analyzes the recent 14-day consumption
                trend to estimate how many days the current stock may last.
                Results depend on the available prescription and inventory data.
                Medicines with insufficient history are marked accordingly.
            </p>
        </div>

        <?php if (!empty($forecastChartable)): ?>
            <div class="inventory-chart-wrap">
                <canvas
                    id="forecastChart"
                    height="<?= max(100, count($forecastChartable) * 28) ?>"
                ></canvas>
            </div>

            <?php if (count($forecastChartable) < count($forecast)): ?>
                <p class="hint">
                    Some medicines do not have enough data for a reliable
                    chart projection. Their statuses remain available in the table.
                </p>
            <?php endif; ?>
        <?php else: ?>
            <p class="hint">
                No reliable supply projections are available yet.
                Ensure medicine consumption and prescription records are available.
            </p>
        <?php endif; ?>

        <div class="schedule-table-wrap">
            <table class="dash-table">
                <thead>
                    <tr>
                        <th>Medicine</th>
                        <th>Current Stock</th>
                        <th>Estimated Daily Use</th>
                        <th>Estimated Days Remaining</th>
                        <th>Forecast Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (!empty($forecast)): ?>
                        <?php foreach ($forecast as $f): ?>
                            <?php
                            $forecastPill = [
                                "critical" => ["status-rejected", "Critical"],
                                "soon" => ["status-pending", "Reorder soon"],
                                "fine" => ["status-confirmed", "Fine"],
                                "insufficient" => ["status-completed", "Not enough data"]
                            ][$f["status"]] ?? ["status-completed", "Unknown"];
                            ?>

                            <tr>
                                <td>
                                    <?= htmlspecialchars(
                                        $f["name"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <?= (int)$f["stock_quantity"] ?>
                                    <?= htmlspecialchars(
                                        $f["unit"],
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($f["daily_rate"] !== null): ?>
                                        <?= number_format($f["daily_rate"], 1) ?>
                                        <?= htmlspecialchars(
                                            $f["unit"],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>/day
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($f["days_remaining"] !== null): ?>
                                        <?= (int)$f["days_remaining"] ?> days
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="status-pill <?= $forecastPill[0] ?>">
                                        <?= htmlspecialchars(
                                            $forecastPill[1],
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">No medicine forecasting data available.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

      <!-- REPORTS -->
      <section class="dash-section" id="tab-reports">
        <h1 class="dash-title">Reports</h1>
        <p class="dash-sub"><?= htmlspecialchars($centerName) ?> — appointment volume, medicine stock, and most-used
          services.</p>

        <div class="dash-card">
          <div class="card-heading-row">
            <h2 class="card-heading">Appointments, last 7 days</h2>
            <a href="export_appointments_csv.php" class="link-btn">Download CSV</a>
          </div>
          <canvas id="apptsChart" height="90"></canvas>
        </div>

        <div class="card-heading-row">
          <a href="medicine_stockpile_report.php" class="btn btn-primary">
            Monthly Stockpile Report
          </a>
        </div>
        <div class="dash-card">
          <h2 class="card-heading">Medicine stock</h2>
          <?php if (empty($stockLabels)): ?>
            <p class="hint">No medicines added yet.</p>
          <?php else: ?>
            <canvas id="stockChart" height="100"></canvas>
          <?php endif; ?>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Most utilized services (all time)</h2>
          <?php if (empty($serviceLabels)): ?>
            <p class="hint">No appointments recorded yet.</p>
          <?php else: ?>
            <canvas id="serviceChart" height="100"></canvas>
          <?php endif; ?>
        </div>
      </section>

      <!-- ACCOUNT -->
      <section class="dash-section" id="tab-account">
        <h1 class="dash-title">My Account</h1>
        <p class="dash-sub">Update your contact details or change your password.</p>

        <div class="dash-card">
          <h2 class="card-heading">Contact details</h2>
          <form action="update_account.php" method="POST" class="appt-form">
            <input type="hidden" name="action" value="contact">
            <div class="field-row">
              <div class="field">
                <label for="acc-email">Email</label>
                <input type="email" id="acc-email" name="email" value="<?= htmlspecialchars($userRow["email"]) ?>"
                  required>
              </div>
              <div class="field">
                <label for="acc-mobile">Mobile number</label>
                <input type="tel" id="acc-mobile" name="mobile" value="<?= htmlspecialchars($staff["mobile"]) ?>"
                  required>
              </div>
            </div>
            <button type="submit" class="btn-primary">Save changes</button>
          </form>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Change password</h2>
          <form action="update_account.php" method="POST" class="appt-form">
            <input type="hidden" name="action" value="password">
            <div class="field">
              <label for="acc-current">Current password</label>
              <div class="password-wrapper">
                <input type="password" id="acc-current" name="current_password" required>
                <button type="button" class="toggle-password" data-target="acc-current" aria-label="Show password">
                  <svg class="icon-eye" viewBox="0 0 24 24">
                    <path
                      d="M12 5c-5.5 0-9.5 4.2-11 7 1.5 2.8 5.5 7 11 7s9.5-4.2 11-7c-1.5-2.8-5.5-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" />
                  </svg>
                  <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;">
                    <path
                      d="M3.3 2.3 2 3.6l3.3 3.3C3.5 8.4 2 10 1 12c1.5 2.8 5.5 7 11 7 1.9 0 3.6-.5 5.1-1.3l3.6 3.6 1.3-1.3L3.3 2.3zM12 16.5a4.5 4.5 0 0 1-4.5-4.5c0-.7.2-1.4.5-2l1.6 1.6a2.5 2.5 0 0 0 2.9 2.9l1.6 1.6c-.6.3-1.3.5-2.1.5zm.2-9c5.1.1 8.8 4 10.3 6.5-.6 1-1.5 2.2-2.7 3.3l-1.5-1.5c.8-.8 1.5-1.6 2-2.3-1.4-2.1-4.4-5-8.3-5-.5 0-1 0-1.4.1L9 8.9c1-.3 2-.4 3.2-.4z" />
                  </svg>
                </button>
              </div>
            </div>
            <div class="field">
              <label for="acc-newpass">New password</label>
              <div class="password-wrapper">
                <input type="password" id="acc-newpass" name="new_password" required>
                <button type="button" class="toggle-password" data-target="acc-newpass" aria-label="Show password">
                  <svg class="icon-eye" viewBox="0 0 24 24">
                    <path
                      d="M12 5c-5.5 0-9.5 4.2-11 7 1.5 2.8 5.5 7 11 7s9.5-4.2 11-7c-1.5-2.8-5.5-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z" />
                  </svg>
                  <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;">
                    <path
                      d="M3.3 2.3 2 3.6l3.3 3.3C3.5 8.4 2 10 1 12c1.5 2.8 5.5 7 11 7 1.9 0 3.6-.5 5.1-1.3l3.6 3.6 1.3-1.3L3.3 2.3zM12 16.5a4.5 4.5 0 0 1-4.5-4.5c0-.7.2-1.4.5-2l1.6 1.6a2.5 2.5 0 0 0 2.9 2.9l1.6 1.6c-.6.3-1.3.5-2.1.5zm.2-9c5.1.1 8.8 4 10.3 6.5-.6 1-1.5 2.2-2.7 3.3l-1.5-1.5c.8-.8 1.5-1.6 2-2.3-1.4-2.1-4.4-5-8.3-5-.5 0-1 0-1.4.1L9 8.9c1-.3 2-.4 3.2-.4z" />
                  </svg>
                </button>
              </div>
              <ul class="pw-requirements" id="pwRequirements">
                <li data-rule="length">At least 8 characters</li>
                <li data-rule="number">Contains a number</li>
                <li data-rule="special">Contains a special character</li>
              </ul>
            </div>
            <button type="submit" class="btn-primary">Update password</button>
          </form>
        </div>
      </section>

    </main>
  </div>

  <script src="patient_logic.js"></script>
  <script>
    // Filter Schedule tables by any visible cell value.
    document.querySelectorAll("[data-table-search]").forEach(function (searchInput) {
      searchInput.addEventListener("input", function () {
        const table = document.getElementById(searchInput.dataset.tableSearch);
        if (!table) return;
        const query = searchInput.value.trim().toLocaleLowerCase();
        const rows = Array.from(table.querySelectorAll("tbody tr:not(.schedule-empty-row)"));
        let visibleCount = 0;
        rows.forEach(function (row) {
          const matches = row.textContent.toLocaleLowerCase().includes(query);
          row.hidden = !matches;
          if (matches) visibleCount++;
        });
        const noResults = table.parentElement.querySelector(".schedule-no-results");
        if (noResults) noResults.hidden = query.length === 0 || visibleCount > 0 || rows.length === 0;
      });
    });

    <?php if (!empty($forecastChartable)): ?>
      new Chart(document.getElementById("forecastChart"), {
        type: "bar",
        data: {
          labels: <?= json_encode(array_column($forecastChartable, "name")) ?>,
          datasets: [{
            label: "Est. days remaining",
            data: <?= json_encode(array_column($forecastChartable, "days_remaining")) ?>,
            backgroundColor: <?= json_encode(array_map(fn($f) => $forecastColorMap[$f["status"]], $forecastChartable)) ?>,
          }]
        },
        options: {
          indexAxis: "y",
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                afterLabel: function (context) {
                  const rates = <?= json_encode(array_map(fn($f) => number_format($f["daily_rate"], 1), $forecastChartable)) ?>;
                  const units = <?= json_encode(array_column($forecastChartable, "unit")) ?>;
                  return "Avg use: " + rates[context.dataIndex] + " " + units[context.dataIndex] + "/day";
                }
              }
            }
          },
          scales: { x: { beginAtZero: true, title: { display: true, text: "Days remaining" } } }
        }
      });
    <?php endif; ?>

    const apptsByDay = <?= json_encode($apptsByDay) ?>;
    new Chart(document.getElementById("apptsChart"), {
      type: "bar",
      data: {
        labels: Object.keys(apptsByDay).map(d => new Date(d + "T00:00:00").toLocaleDateString(undefined, { month: "short", day: "numeric" })),
        datasets: [{ label: "Appointments", data: Object.values(apptsByDay), backgroundColor: "#146B61" }]
      },
      options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
    });

    <?php if (!empty($stockLabels)): ?>
      new Chart(document.getElementById("stockChart"), {
        type: "bar",
        data: {
          labels: <?= json_encode($stockLabels) ?>,
          datasets: [{ label: "Stock on hand", data: <?= json_encode($stockValues) ?>, backgroundColor: "#E8A33D" }]
        },
        options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
      });
    <?php endif; ?>

    <?php if (!empty($serviceLabels)): ?>
      new Chart(document.getElementById("serviceChart"), {
        type: "bar",
        data: {
          labels: <?= json_encode($serviceLabels) ?>,
          datasets: [{ label: "Times booked", data: <?= json_encode($serviceValues) ?>, backgroundColor: "#0E4D46" }]
        },
        options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
      });
    <?php endif; ?>
  </script>
</body>

</html>