<?php
session_start();
include "connection.php";
include "auth_check.php";
include "forecast_helper.php";

if ($_SESSION["role"] !== "admin") {
    if ($_SESSION["role"] === "patient") { header("Location: dashboard.php"); }
    elseif ($_SESSION["role"] === "doctor") { header("Location: doctor_dashboard.php"); }
    else { header("Location: staff_dashboard.php"); }
    exit;
}

$username = $_SESSION["username"];

$stmt = $conn->prepare("SELECT * FROM patients WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmtUser = $conn->prepare("SELECT email FROM users WHERE username = ?");
$stmtUser->bind_param("s", $username);
$stmtUser->execute();
$userRow = $stmtUser->get_result()->fetch_assoc();
$stmtUser->close();

// All health centers, for dropdowns everywhere
$centersResult = $conn->query("SELECT * FROM health_centers ORDER BY name");
$centers = [];
while ($c = $centersResult->fetch_assoc()) $centers[] = $c;
function center_name_admin($centers, $id) {
    foreach ($centers as $c) if ($c["id"] == $id) return $c["name"];
    return "Unassigned";
}

// Pending staff/doctor requests
$stmt = $conn->query("
    SELECT users.username, patients.full_name, patients.staff_position,
           patients.staff_claimed_center_id, patients.staff_employee_id,
           health_centers.name AS claimed_center_name
    FROM users
    JOIN patients ON patients.username = users.username
    LEFT JOIN health_centers ON health_centers.id = patients.staff_claimed_center_id
    WHERE patients.pending_staff = 1 AND users.role = 'patient'
    ORDER BY patients.id
");
$pendingRequests = $stmt;

// All currently approved staff/doctor accounts
$staffList = $conn->query("
    SELECT users.username, users.role, patients.full_name, patients.health_center_id,
           health_centers.name AS center_name
    FROM users
    JOIN patients ON patients.username = users.username
    LEFT JOIN health_centers ON health_centers.id = patients.health_center_id
    WHERE users.role IN ('staff','doctor')
    ORDER BY health_centers.name, patients.full_name
");

// District-wide quick stats
$pendingCount = $pendingRequests->num_rows;
$centerCount = count($centers);
$lowStockDistrict = $conn->query("SELECT COUNT(*) AS c FROM medicines WHERE stock_quantity <= reorder_threshold")->fetch_assoc()["c"];
$totalPatients = $conn->query("SELECT COUNT(*) AS c FROM users WHERE role = 'patient'")->fetch_assoc()["c"];

// Upcoming status overrides (today and future), for the Health Centers tab list
$statusOverrides = $conn->query("
    SELECT health_center_status.*, health_centers.name AS center_name
    FROM health_center_status
    JOIN health_centers ON health_centers.id = health_center_status.health_center_id
    WHERE status_date >= CURDATE()
    ORDER BY status_date, center_name
");

// ---------------- DISTRICT-WIDE REPORTS ----------------

// Appointments per day, last 7 days, all centers combined
$apptsByDay = [];
for ($i = 6; $i >= 0; $i--) $apptsByDay[date("Y-m-d", strtotime("-$i days"))] = 0;
$res = $conn->query("
    SELECT appointment_date, COUNT(*) AS cnt FROM appointments
    WHERE appointment_date BETWEEN DATE_SUB(CURDATE(), INTERVAL 6 DAY) AND CURDATE()
          AND status NOT IN ('Cancelled','Rejected')
    GROUP BY appointment_date
");
while ($row = $res->fetch_assoc()) $apptsByDay[$row["appointment_date"]] = (int) $row["cnt"];

// Appointments by center (all-time, not cancelled/rejected) — which center is busiest
$res = $conn->query("
    SELECT health_centers.name, COUNT(appointments.id) AS cnt
    FROM health_centers
    LEFT JOIN appointments ON appointments.health_center_id = health_centers.id
          AND appointments.status NOT IN ('Cancelled','Rejected')
    GROUP BY health_centers.id, health_centers.name
    ORDER BY cnt DESC
");
$centerApptLabels = []; $centerApptValues = [];
while ($row = $res->fetch_assoc()) { $centerApptLabels[] = $row["name"]; $centerApptValues[] = (int) $row["cnt"]; }

// Most utilized service, district-wide, top 5
$res = $conn->query("
    SELECT service, COUNT(*) AS cnt FROM appointments
    WHERE status NOT IN ('Cancelled','Rejected')
    GROUP BY service ORDER BY cnt DESC LIMIT 5
");
$serviceLabels = []; $serviceValues = [];
while ($row = $res->fetch_assoc()) { $serviceLabels[] = $row["service"]; $serviceValues[] = (int) $row["cnt"]; }

// Low-stock medicine count by center — which center is chronically short
$res = $conn->query("
    SELECT health_centers.name, COUNT(medicines.id) AS low_count
    FROM health_centers
    LEFT JOIN medicines ON medicines.health_center_id = health_centers.id
          AND medicines.stock_quantity <= medicines.reorder_threshold
    GROUP BY health_centers.id, health_centers.name
    ORDER BY low_count DESC
");
$lowStockLabels = []; $lowStockValues = [];
while ($row = $res->fetch_assoc()) { $lowStockLabels[] = $row["name"]; $lowStockValues[] = (int) $row["low_count"]; }

// District-wide supply forecast — no center filter, so this naturally
// surfaces a shortage at one center sitting right next to surplus at
// another once sorted by urgency.
$districtForecast = get_medicine_forecast($conn);
$districtCriticalCount = count(array_filter($districtForecast, fn($f) => $f["status"] === "critical"));

$statusMsg = $_GET["msg"] ?? null;
$statusType = $_GET["type"] ?? "info";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Dashboard — Malabon District II Health Centers</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,400;9..144,500;9..144,600&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="homepagestyle.css">
<link rel="stylesheet" href="patient_style.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
</head>
<body class="dashboard-body">

<div class="dash-shell">

  <aside class="sidebar">
    <div class="sidebar-brand"><span class="dot"></span> MALABON DISTRICT II</div>
    <div class="sidebar-user">
      <div class="sidebar-name"><?= htmlspecialchars($admin["full_name"] ?: $username) ?></div>
      <div class="sidebar-id">District Admin</div>
    </div>
    <nav class="sidebar-nav">
      <a href="#" class="nav-link" data-tab="home">
        Home
        <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
      </a>
      <a href="#" class="nav-link" data-tab="accounts">
        Manage Accounts
        <?php if ($pendingCount > 0): ?><span class="nav-badge"><?= $pendingCount ?></span><?php endif; ?>
      </a>
      <a href="#" class="nav-link" data-tab="centers">Health Centers</a>
      <a href="#" class="nav-link" data-tab="reports">Reports</a>
      <a href="#" class="nav-link" data-tab="forecast">
        Supply Forecast
        <?php if ($districtCriticalCount > 0): ?><span class="nav-badge"><?= $districtCriticalCount ?></span><?php endif; ?>
      </a>
      <a href="#" class="nav-link" data-tab="account">My Account</a>
    </nav>
    <a href="logout.php" class="sidebar-logout">Sign out</a>
  </aside>

  <main class="dash-main">

    <?php if ($statusMsg): ?>
      <div class="msg <?= htmlspecialchars($statusType) ?>"><?= htmlspecialchars($statusMsg) ?></div>
    <?php endif; ?>

    <!-- HOME -->
    <section class="dash-section" id="tab-home">
      <h1 class="dash-title">Welcome, <?= htmlspecialchars(explode(" ", $admin["full_name"] ?: $username)[0]) ?></h1>
      <p class="dash-sub">Malabon District II — district-wide overview.</p>

      <div class="cards-row">
        <div class="dash-card">
          <div class="dash-card-label">Pending staff requests</div>
          <div class="dash-card-value"><?= $pendingCount ?></div>
        </div>
        <div class="dash-card">
          <div class="dash-card-label">Health centers</div>
          <div class="dash-card-value"><?= $centerCount ?></div>
        </div>
        <div class="dash-card">
          <div class="dash-card-label">Low stock alerts (district-wide)</div>
          <div class="dash-card-value"><?= $lowStockDistrict ?></div>
        </div>
        <div class="dash-card">
          <div class="dash-card-label">Registered patients</div>
          <div class="dash-card-value"><?= $totalPatients ?></div>
        </div>
      </div>

      <?php if ($pendingCount > 0): ?>
        <div class="pending-banner">
          There are <?= $pendingCount ?> staff/doctor request(s) waiting for review.
          <a href="#" class="nav-link-inline" data-tab="accounts">Review now</a>
        </div>
      <?php endif; ?>
    </section>

    <!-- MANAGE ACCOUNTS -->
    <section class="dash-section" id="tab-accounts">
      <h1 class="dash-title">Manage Accounts</h1>
      <p class="dash-sub">Approve staff/doctor requests, and reassign existing staff between centers.</p>

      <div class="dash-card">
        <h2 class="card-heading">Pending requests</h2>
        <table class="dash-table">
          <thead><tr><th>Name</th><th>Claimed position</th><th>Claimed center</th><th>Employee ID</th><th></th></tr></thead>
          <tbody>
            <?php if ($pendingRequests->num_rows > 0): while ($row = $pendingRequests->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row["full_name"]) ?></td>
                <td><?= htmlspecialchars($row["staff_position"] ?: "—") ?></td>
                <td><?= htmlspecialchars($row["claimed_center_name"] ?: "—") ?></td>
                <td><?= htmlspecialchars($row["staff_employee_id"] ?: "—") ?></td>
                <td class="table-actions">
                  <form action="process_admin_account.php" method="POST" class="approve-form">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($row["username"]) ?>">
                    <select name="new_role" required>
                      <option value="">Role</option>
                      <option value="staff">Staff</option>
                      <option value="doctor">Doctor</option>
                    </select>
                    <select name="health_center_id" required>
                      <option value="">Center</option>
                      <?php foreach ($centers as $c): ?>
                        <option value="<?= $c["id"] ?>" <?= $c["id"] == $row["staff_claimed_center_id"] ? "selected" : "" ?>><?= htmlspecialchars($c["name"]) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-approve">Approve</button>
                  </form>
                  <form action="process_admin_account.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($row["username"]) ?>">
                    <button type="submit" class="btn-reject" onclick="return confirm('Reject this request? The account stays a regular patient account.');">Reject</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="5">No pending requests.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Current staff &amp; doctors</h2>
        <table class="dash-table">
          <thead><tr><th>Name</th><th>Role</th><th>Health center</th><th>Move to</th></tr></thead>
          <tbody>
            <?php if ($staffList->num_rows > 0): while ($row = $staffList->fetch_assoc()): ?>
              <tr>
                <td><?= htmlspecialchars($row["full_name"]) ?></td>
                <td><?= ucfirst($row["role"]) ?></td>
                <td><?= htmlspecialchars($row["center_name"] ?: "Unassigned") ?></td>
                <td>
                  <form action="process_admin_account.php" method="POST" class="move-form">
                    <input type="hidden" name="action" value="move">
                    <input type="hidden" name="username" value="<?= htmlspecialchars($row["username"]) ?>">
                    <select name="health_center_id" required>
                      <?php foreach ($centers as $c): ?>
                        <option value="<?= $c["id"] ?>" <?= $c["id"] == $row["health_center_id"] ? "selected" : "" ?>><?= htmlspecialchars($c["name"]) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-move">Move</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="4">No staff or doctors yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- HEALTH CENTERS -->
    <section class="dash-section" id="tab-centers">
      <h1 class="dash-title">Health Centers</h1>
      <p class="dash-sub">Add centers, edit their info, and set today's or an upcoming status.</p>

      <div class="dash-card">
        <h2 class="card-heading">Add a health center</h2>
        <form action="process_health_center.php" method="POST" class="appt-form">
          <input type="hidden" name="action" value="add_center">
          <div class="field">
            <label for="hc-name">Name</label>
            <input type="text" id="hc-name" name="name" required>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="hc-address">Address</label>
              <input type="text" id="hc-address" name="address">
            </div>
            <div class="field">
              <label for="hc-contact">Contact number</label>
              <input type="text" id="hc-contact" name="contact_number">
            </div>
          </div>
          <div class="field">
            <label for="hc-hours">Regular operating hours</label>
            <input type="text" id="hc-hours" name="operating_hours" placeholder="Mon–Fri, 8:00 AM – 5:00 PM">
          </div>
          <button type="submit" class="btn-primary">Add center</button>
        </form>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Existing centers</h2>
        <?php foreach ($centers as $c): ?>
          <form action="process_health_center.php" method="POST" class="edit-center-form">
            <input type="hidden" name="action" value="edit_center">
            <input type="hidden" name="id" value="<?= $c["id"] ?>">
            <div class="field-row">
              <div class="field"><label>Name</label><input type="text" name="name" value="<?= htmlspecialchars($c["name"]) ?>" required></div>
              <div class="field"><label>Contact</label><input type="text" name="contact_number" value="<?= htmlspecialchars($c["contact_number"]) ?>"></div>
            </div>
            <div class="field-row">
              <div class="field"><label>Address</label><input type="text" name="address" value="<?= htmlspecialchars($c["address"]) ?>"></div>
              <div class="field"><label>Hours</label><input type="text" name="operating_hours" value="<?= htmlspecialchars($c["operating_hours"]) ?>"></div>
            </div>
            <button type="submit" class="btn-secondary" style="width:auto;padding:8px 18px;">Save</button>
          </form>
        <?php endforeach; ?>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Set a status (holiday, event, or closure)</h2>
        <form action="process_health_center.php" method="POST" class="appt-form">
          <input type="hidden" name="action" value="add_status">
          <div class="field-row">
            <div class="field">
              <label for="st-center">Health center</label>
              <select id="st-center" name="health_center_id" required>
                <?php foreach ($centers as $c): ?><option value="<?= $c["id"] ?>"><?= htmlspecialchars($c["name"]) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="st-date">Date</label>
              <input type="date" id="st-date" name="status_date" required>
            </div>
          </div>
          <div class="field-row">
            <div class="field">
              <label for="st-status">Status</label>
              <select id="st-status" name="status" required>
                <option value="Open">Open (custom hours)</option>
                <option value="Closed">Closed (holiday)</option>
                <option value="Event">Event (e.g. vaccination drive)</option>
              </select>
            </div>
            <div class="field">
              <label for="st-hours">Hours <span class="hint">(if Open or Event)</span></label>
              <input type="text" id="st-hours" name="custom_hours" placeholder="8:00 AM – 12:00 PM">
            </div>
          </div>
          <div class="field">
            <label for="st-note">Note <span class="hint">(shown to patients)</span></label>
            <input type="text" id="st-note" name="note" placeholder="e.g. Vaccination Drive, Barangay Fiesta">
          </div>
          <button type="submit" class="btn-primary">Save status</button>
        </form>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Upcoming statuses</h2>
        <table class="dash-table">
          <thead><tr><th>Date</th><th>Center</th><th>Status</th><th>Hours</th><th>Note</th><th></th></tr></thead>
          <tbody>
            <?php if ($statusOverrides->num_rows > 0): while ($row = $statusOverrides->fetch_assoc()): ?>
              <tr>
                <td><?= date("M j, Y", strtotime($row["status_date"])) ?></td>
                <td><?= htmlspecialchars($row["center_name"]) ?></td>
                <td><span class="status-pill status-<?= $row["status"] === "Closed" ? "rejected" : ($row["status"] === "Event" ? "pending" : "confirmed") ?>"><?= $row["status"] ?></span></td>
                <td><?= htmlspecialchars($row["custom_hours"] ?: "—") ?></td>
                <td><?= htmlspecialchars($row["note"] ?: "—") ?></td>
                <td>
                  <form action="process_health_center.php" method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="delete_status">
                    <input type="hidden" name="id" value="<?= $row["id"] ?>">
                    <button type="submit" class="link-btn" onclick="return confirm('Remove this status?');">Remove</button>
                  </form>
                </td>
              </tr>
            <?php endwhile; else: ?>
              <tr><td colspan="6">No upcoming statuses set.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <!-- REPORTS -->
    <section class="dash-section" id="tab-reports">
      <h1 class="dash-title">Reports</h1>
      <p class="dash-sub">District-wide, across all <?= $centerCount ?> health centers.</p>

      <div class="dash-card">
        <div class="card-heading-row">
          <h2 class="card-heading">Appointments, last 7 days (all centers)</h2>
          <a href="export_all_appointments_csv.php" class="link-btn">Download CSV</a>
        </div>
        <canvas id="apptsChart" height="90"></canvas>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Appointments by center (all time)</h2>
        <?php if (empty($centerApptLabels)): ?><p class="hint">No appointments recorded yet.</p><?php else: ?>
          <canvas id="centerApptChart" height="100"></canvas>
        <?php endif; ?>
      </div>

      <div class="dash-card">
        <h2 class="card-heading">Most utilized services (district-wide, all time)</h2>
        <?php if (empty($serviceLabels)): ?><p class="hint">No appointments recorded yet.</p><?php else: ?>
          <canvas id="serviceChart" height="100"></canvas>
        <?php endif; ?>
      </div>

      <div class="dash-card">
        <div class="card-heading-row">
          <h2 class="card-heading">Low-stock medicines by center</h2>
          <a href="export_all_medicine_csv.php" class="link-btn">Download CSV</a>
        </div>
        <?php if (empty($lowStockLabels)): ?><p class="hint">No medicines added at any center yet.</p><?php else: ?>
          <canvas id="lowStockChart" height="100"></canvas>
        <?php endif; ?>
      </div>
    </section>

    <!-- SUPPLY FORECAST -->
    <section class="dash-section" id="tab-forecast">
      <h1 class="dash-title">Supply Forecast</h1>
      <p class="dash-sub">District-wide, across all <?= $centerCount ?> health centers.</p>

      <div class="dash-card">
        <div class="card-heading-row">
          <h2 class="card-heading">
            Projected days of stock remaining
            <?php if ($districtCriticalCount > 0): ?><span class="status-pill status-rejected"><?= $districtCriticalCount ?> critical</span><?php endif; ?>
          </h2>
          <a href="export_forecast_csv.php" class="link-btn">Download CSV</a>
        </div>
        <p class="hint" style="margin-bottom:12px;">
          Based on a 14-day consumption trend per medicine, per center — not just an average, so a recent
          spike shows up faster. Anything with fewer than 3 prescriptions in that window is marked
          "Not enough data" rather than showing a number that would just be noise. Sorted by urgency — a
          shortage at one center and surplus at another for the same medicine will both show up here.
        </p>

        <?php
          $districtChartable = array_values(array_filter($districtForecast, fn($f) => $f["days_remaining"] !== null));
          $forecastColorMap = ["critical" => "#C0392B", "soon" => "#E8A33D", "fine" => "#1E7A4C"];
        ?>
        <?php if (!empty($districtChartable)): ?>
          <canvas id="forecastChart" height="<?= max(100, count($districtChartable) * 26) ?>"></canvas>
          <?php if (count($districtChartable) < count($districtForecast)): ?>
            <p class="hint" style="margin-top:8px;">
              <?= count($districtForecast) - count($districtChartable) ?> medicine(s) with insufficient data aren't shown on the chart — see the table below.
            </p>
          <?php endif; ?>
        <?php endif; ?>

        <table class="dash-table">
          <thead><tr><th>Medicine</th><th>Health center</th><th>Stock</th><th>Daily use (avg)</th><th>Est. days left</th><th>Status</th></tr></thead>
          <tbody>
            <?php if (!empty($districtForecast)): foreach ($districtForecast as $f): ?>
              <tr>
                <td><?= htmlspecialchars($f["name"]) ?></td>
                <td><?= htmlspecialchars($f["center_name"]) ?></td>
                <td><?= $f["stock_quantity"] ?> <?= htmlspecialchars($f["unit"]) ?></td>
                <td><?= $f["daily_rate"] !== null ? number_format($f["daily_rate"], 1) . " " . htmlspecialchars($f["unit"]) . "/day" : "—" ?></td>
                <td><?= $f["days_remaining"] !== null ? $f["days_remaining"] . " days" : "—" ?></td>
                <td>
                  <?php
                    $forecastPill = [
                      "critical" => ["status-rejected", "Critical"],
                      "soon" => ["status-pending", "Reorder soon"],
                      "fine" => ["status-confirmed", "Fine"],
                      "insufficient" => ["status-completed", "Not enough data"],
                    ][$f["status"]];
                  ?>
                  <span class="status-pill <?= $forecastPill[0] ?>"><?= $forecastPill[1] ?></span>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6">No medicines added at any center yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
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
              <input type="email" id="acc-email" name="email" value="<?= htmlspecialchars($userRow["email"]) ?>" required>
            </div>
            <div class="field">
              <label for="acc-mobile">Mobile number</label>
              <input type="tel" id="acc-mobile" name="mobile" value="<?= htmlspecialchars($admin["mobile"]) ?>" required>
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
                <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 5c-5.5 0-9.5 4.2-11 7 1.5 2.8 5.5 7 11 7s9.5-4.2 11-7c-1.5-2.8-5.5-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;"><path d="M3.3 2.3 2 3.6l3.3 3.3C3.5 8.4 2 10 1 12c1.5 2.8 5.5 7 11 7 1.9 0 3.6-.5 5.1-1.3l3.6 3.6 1.3-1.3L3.3 2.3zM12 16.5a4.5 4.5 0 0 1-4.5-4.5c0-.7.2-1.4.5-2l1.6 1.6a2.5 2.5 0 0 0 2.9 2.9l1.6 1.6c-.6.3-1.3.5-2.1.5zm.2-9c5.1.1 8.8 4 10.3 6.5-.6 1-1.5 2.2-2.7 3.3l-1.5-1.5c.8-.8 1.5-1.6 2-2.3-1.4-2.1-4.4-5-8.3-5-.5 0-1 0-1.4.1L9 8.9c1-.3 2-.4 3.2-.4z"/></svg>
              </button>
            </div>
          </div>
          <div class="field">
            <label for="acc-newpass">New password</label>
            <div class="password-wrapper">
              <input type="password" id="acc-newpass" name="new_password" required>
              <button type="button" class="toggle-password" data-target="acc-newpass" aria-label="Show password">
                <svg class="icon-eye" viewBox="0 0 24 24"><path d="M12 5c-5.5 0-9.5 4.2-11 7 1.5 2.8 5.5 7 11 7s9.5-4.2 11-7c-1.5-2.8-5.5-7-11-7zm0 11.5A4.5 4.5 0 1 1 12 7.5a4.5 4.5 0 0 1 0 9zm0-7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/></svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" style="display:none;"><path d="M3.3 2.3 2 3.6l3.3 3.3C3.5 8.4 2 10 1 12c1.5 2.8 5.5 7 11 7 1.9 0 3.6-.5 5.1-1.3l3.6 3.6 1.3-1.3L3.3 2.3zM12 16.5a4.5 4.5 0 0 1-4.5-4.5c0-.7.2-1.4.5-2l1.6 1.6a2.5 2.5 0 0 0 2.9 2.9l1.6 1.6c-.6.3-1.3.5-2.1.5zm.2-9c5.1.1 8.8 4 10.3 6.5-.6 1-1.5 2.2-2.7 3.3l-1.5-1.5c.8-.8 1.5-1.6 2-2.3-1.4-2.1-4.4-5-8.3-5-.5 0-1 0-1.4.1L9 8.9c1-.3 2-.4 3.2-.4z"/></svg>
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
  <?php if (!empty($districtChartable)): ?>
  new Chart(document.getElementById("forecastChart"), {
    type: "bar",
    data: {
      labels: <?= json_encode(array_map(fn($f) => $f["name"] . " — " . $f["center_name"], $districtChartable)) ?>,
      datasets: [{
        label: "Est. days remaining",
        data: <?= json_encode(array_column($districtChartable, "days_remaining")) ?>,
        backgroundColor: <?= json_encode(array_map(fn($f) => $forecastColorMap[$f["status"]], $districtChartable)) ?>,
      }]
    },
    options: {
      indexAxis: "y",
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            afterLabel: function(context) {
              const rates = <?= json_encode(array_map(fn($f) => number_format($f["daily_rate"], 1), $districtChartable)) ?>;
              const units = <?= json_encode(array_column($districtChartable, "unit")) ?>;
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

  <?php if (!empty($centerApptLabels)): ?>
  new Chart(document.getElementById("centerApptChart"), {
    type: "bar",
    data: { labels: <?= json_encode($centerApptLabels) ?>, datasets: [{ label: "Appointments", data: <?= json_encode($centerApptValues) ?>, backgroundColor: "#0E4D46" }] },
    options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } }
  });
  <?php endif; ?>

  <?php if (!empty($serviceLabels)): ?>
  new Chart(document.getElementById("serviceChart"), {
    type: "bar",
    data: { labels: <?= json_encode($serviceLabels) ?>, datasets: [{ label: "Times booked", data: <?= json_encode($serviceValues) ?>, backgroundColor: "#E8A33D" }] },
    options: { indexAxis: "y", plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });
  <?php endif; ?>

  <?php if (!empty($lowStockLabels)): ?>
  new Chart(document.getElementById("lowStockChart"), {
    type: "bar",
    data: { labels: <?= json_encode($lowStockLabels) ?>, datasets: [{ label: "Low-stock medicines", data: <?= json_encode($lowStockValues) ?>, backgroundColor: "#C0392B" }] },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
  });
  <?php endif; ?>
</script>
</body>
</html>