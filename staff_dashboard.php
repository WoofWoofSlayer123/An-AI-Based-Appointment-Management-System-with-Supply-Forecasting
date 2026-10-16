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
      <section class="dash-section" id="tab-home">
        <h1 class="dash-title">Welcome, <?= htmlspecialchars(explode(" ", $staff["full_name"] ?: $username)[0]) ?></h1>
        <p class="dash-sub"><?= htmlspecialchars($centerName) ?></p>

        <div class="cards-row">
          <div class="dash-card">
            <div class="dash-card-label">Pending requests</div>
            <div class="dash-card-value"><?= $pending->num_rows ?></div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Today's confirmed visits</div>
            <div class="dash-card-value"><?= $today->num_rows ?></div>
          </div>
          <div class="dash-card">
            <div class="dash-card-label">Low stock medicines</div>
            <div class="dash-card-value"><?= $lowStockCount ?></div>
          </div>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Pending requests needing a decision</h2>
          <?php if (empty($doctors)): ?>
            <p class="hint">No doctors are assigned to this center yet — an admin needs to set that up before you can
              confirm appointments.</p>
          <?php endif; ?>
          <table class="dash-table">
            <thead>
              <tr>
                <th>Patient</th>
                <th>Date</th>
                <th>Time</th>
                <th>Service</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($pending->num_rows > 0):
                while ($row = $pending->fetch_assoc()): ?>
                  <tr>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td><?= date("M j, Y", strtotime($row["appointment_date"])) ?></td>
                    <td><?= date("g:i A", strtotime($row["appointment_time"])) ?></td>
                    <td><?= htmlspecialchars($row["service"]) ?></td>
                    <td class="table-actions">
                      <form action="update_appointment_status.php" method="POST" class="confirm-form">
                        <input type="hidden" name="id" value="<?= $row["id"] ?>">
                        <input type="hidden" name="status" value="Confirmed">
                        <select name="assigned_doctor" required>
                          <option value="">Assign doctor</option>
                          <?php foreach ($doctors as $d): ?>
                            <option value="<?= htmlspecialchars($d["username"]) ?>"><?= htmlspecialchars($d["full_name"]) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <button type="submit" class="link-btn">Confirm</button>
                      </form>
                      <form action="update_appointment_status.php" method="POST" style="display:inline;">
                        <input type="hidden" name="id" value="<?= $row["id"] ?>">
                        <input type="hidden" name="status" value="Rejected">
                        <button type="submit" class="link-btn"
                          onclick="return confirm('Reject this appointment request?');">Reject</button>
                      </form>
                    </td>
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="5">No pending requests.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Today's schedule</h2>
          <table class="dash-table">
            <thead>
              <tr>
                <th>Time</th>
                <th>Patient</th>
                <th>Service</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($today->num_rows > 0):
                while ($row = $today->fetch_assoc()): ?>
                  <tr>
                    <td><?= date("g:i A", strtotime($row["appointment_time"])) ?></td>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td><?= htmlspecialchars($row["service"]) ?></td>
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="3">No confirmed visits today.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- SCHEDULE (read-only browse; decisions happen on Home) -->
      <section class="dash-section" id="tab-schedule">
        <h1 class="dash-title">Schedule</h1>
        <p class="dash-sub">All pending and confirmed appointments at <?= htmlspecialchars($centerName) ?>.</p>

        <div class="dash-card">
          <table class="dash-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Time</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($schedule->num_rows > 0):
                while ($row = $schedule->fetch_assoc()): ?>
                  <tr>
                    <td><?= date("M j, Y", strtotime($row["appointment_date"])) ?></td>
                    <td><?= date("g:i A", strtotime($row["appointment_time"])) ?></td>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td><?= htmlspecialchars($row["service"]) ?></td>
                    <td><span class="status-pill status-<?= strtolower($row["status"]) ?>"><?= $row["status"] ?></span></td>
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="5">Nothing on the schedule.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
          <p class="hint" style="margin-top:10px;">Pending requests are confirmed or rejected from the Home tab.</p>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Recent history</h2>
          <table class="dash-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Patient</th>
                <th>Service</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($history->num_rows > 0):
                while ($row = $history->fetch_assoc()): ?>
                  <tr>
                    <td><?= date("M j, Y", strtotime($row["appointment_date"])) ?></td>
                    <td><?= htmlspecialchars($row["full_name"]) ?></td>
                    <td><?= htmlspecialchars($row["service"]) ?></td>
                    <td><span class="status-pill status-<?= strtolower($row["status"]) ?>"><?= $row["status"] ?></span></td>
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="4">No history yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>

      <!-- MEDICINE INVENTORY -->
      <section class="dash-section" id="tab-inventory">
        <h1 class="dash-title">Medicine Inventory</h1>
        <p class="dash-sub">Stock on hand at <?= htmlspecialchars($centerName) ?>.</p>

        <div class="dash-card">
          <h2 class="card-heading">Add a medicine</h2>
          <form action="process_medicine.php" method="POST" class="appt-form">
            <input type="hidden" name="action" value="add">
            <div class="field-row">
              <div class="field">
                <label for="med-name">Name</label>
                <input type="text" id="med-name" name="name" required>
              </div>
              <div class="field">
                <label for="med-unit">Unit</label>
                <input type="text" id="med-unit" name="unit" placeholder="tablet, bottle, vial" required>
              </div>
            </div>
            <div class="field-row">
              <div class="field">
                <label for="med-stock">Starting stock</label>
                <input type="number" id="med-stock" name="stock_quantity" min="0" required>
              </div>
              <div class="field">
                <label for="med-threshold">Low-stock threshold</label>
                <input type="number" id="med-threshold" name="reorder_threshold" min="0" value="10" required>
              </div>
            </div>
            <button type="submit" class="btn-primary">Add medicine</button>
          </form>
        </div>

        <div class="dash-card">
          <h2 class="card-heading">Current stock</h2>
          <table class="dash-table">
            <thead>
              <tr>
                <th>Medicine</th>
                <th>Acquisition Date</th>
                <th>Expiration Date</th>
                <th>Expiration Status</th>
                <th>Unit</th>
                <th>Stock</th>
                <th></th>
                <th>Restock</th>
              </tr>
            </thead>
            <tbody>
              <?php if ($medicines->num_rows > 0):
                while ($row = $medicines->fetch_assoc()):
                  $isLow = $row["stock_quantity"] <= $row["reorder_threshold"];
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($row["name"]) ?></td>
                    <td><?= htmlspecialchars($row["unit"]) ?></td>
                    <td><?= $row["stock_quantity"] ?></td>
                    <td><?php if ($isLow): ?><span class="status-pill status-rejected">Low</span><?php else: ?><span
                          class="status-pill status-confirmed">OK</span><?php endif; ?></td>
                    <td>
                      <form action="process_medicine.php" method="POST" class="restock-form">
                        <input type="hidden" name="action" value="restock">
                        <input type="hidden" name="medicine_id" value="<?= $row["id"] ?>">
                        <input type="number" name="add_quantity" min="1" placeholder="+ qty" required>
                        <button type="submit" class="link-btn">Add</button>
                      </form>
                    </td>
                    
                  </tr>
                <?php endwhile; else: ?>
                <tr>
                  <td colspan="5">No medicines added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php
        $forecastChartable = array_values(array_filter($forecast, fn($f) => $f["days_remaining"] !== null));
        $forecastColorMap = ["critical" => "#C0392B", "soon" => "#E8A33D", "fine" => "#1E7A4C"];
        ?>
        <div class="dash-card">
          <div class="card-heading-row">
            <h2 class="card-heading">Supply Forecast</h2>
            <?php if ($criticalCount > 0): ?><span class="status-pill status-rejected"><?= $criticalCount ?>
                critical</span><?php endif; ?>
          </div>
          <p class="hint" style="margin-bottom:12px;">
            Based on a 14-day consumption trend (not just an average), so a recent spike shows up faster.
            Medicines with too little prescription history to project reliably are marked "Not enough data"
            rather than guessing.
          </p>

          <?php if (!empty($forecastChartable)): ?>
            <canvas id="forecastChart" height="<?= max(80, count($forecastChartable) * 28) ?>"></canvas>
            <?php if (count($forecastChartable) < count($forecast)): ?>
              <p class="hint" style="margin-top:8px;">
                <?= count($forecast) - count($forecastChartable) ?> medicine(s) with insufficient data aren't shown on the
                chart — see the table below.
              </p>
            <?php endif; ?>
          <?php endif; ?>

          <table class="dash-table">
            <thead>
              <tr>
                <th>Medicine</th>
                <th>Stock</th>
                <th>Daily use (avg)</th>
                <th>Est. days left</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($forecast)):
                foreach ($forecast as $f): ?>
                  <tr>
                    <td><?= htmlspecialchars($f["name"]) ?></td>
                    <td><?= $f["stock_quantity"] ?>     <?= htmlspecialchars($f["unit"]) ?></td>
                    <td>
                      <?= $f["daily_rate"] !== null ? number_format($f["daily_rate"], 1) . " " . htmlspecialchars($f["unit"]) . "/day" : "—" ?>
                    </td>
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
                <tr>
                  <td colspan="5">No medicines added yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
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