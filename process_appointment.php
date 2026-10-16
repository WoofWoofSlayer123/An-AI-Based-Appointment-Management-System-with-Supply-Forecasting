<?php
session_start();
include "connection.php";
include "auth_check.php";
include "notifications_helper.php";
include "booking_rules.php";
include "email_helper.php";

$username = $_SESSION["username"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: patient_dashboard.php");
    exit;
}

function back($type, $msg) {
    header("Location: patient_dashboard.php?tab=appointments&type=$type&msg=" . urlencode($msg));
    exit;
}

$action = $_POST["action"] ?? "";

if ($action === "add") {
    $health_center_id = (int) ($_POST["health_center_id"] ?? 0);
    $service = trim($_POST["service"] ?? "");
    $date = $_POST["appointment_date"] ?? "";
    $time = $_POST["appointment_time"] ?? "";

    if (!$health_center_id || $service === "" || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) {
        back("error", "Please fill in every field.");
    }
    if (strtotime($date) < strtotime(date("Y-m-d"))) {
        back("error", "You can't book a date in the past.");
    }
    if (strtotime("$date $time") <= time()) {
        back("error", "That time has already passed. Please pick a later time.");
    }

    // Working hours of the chosen center — bookings outside them are refused.
    $stmt = $conn->prepare("SELECT name, operating_hours FROM health_centers WHERE id = ?");
    $stmt->bind_param("i", $health_center_id);
    $stmt->execute();
    $center = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$center) back("error", "Health center not found.");

    $hours = parse_operating_hours($center["operating_hours"]);
    $tMin = (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    if ($tMin < $hours["open"] || $tMin >= $hours["close"]) {
        back("error", $center["name"] . " accepts appointments only between " . minutes_label($hours["open"]) . " and " . minutes_label($hours["close"]) . ".");
    }

    // Admin-marked closures (holiday, etc). "Event" doesn't block booking, only "Closed" does.
    $stmt = $conn->prepare("SELECT note FROM health_center_status WHERE health_center_id = ? AND status_date = ? AND status = 'Closed'");
    $stmt->bind_param("is", $health_center_id, $date);
    $stmt->execute();
    $closed = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($closed) {
        $reason = $closed["note"] ? " (" . $closed["note"] . ")" : "";
        back("error", "This health center is closed on that date$reason. Please pick another date or center.");
    }

    // Capacity: at most ONLINE_BOOKING_CAP online bookings per 2-hour window, so the
    // rest of the window stays open for walk-ins. Done in a transaction so two patients
    // can't both grab the last slot.
    [$wStart, $wEnd] = slot_window($tMin, $hours["open"]);
    $startSec = $wStart * 60;
    $endSec = $wEnd * 60;

    $conn->begin_transaction();
    $stmt = $conn->prepare("
        SELECT id FROM appointments
        WHERE health_center_id = ? AND appointment_date = ?
          AND status NOT IN (" . INACTIVE_STATUSES_SQL . ")
          AND TIME_TO_SEC(appointment_time) >= ? AND TIME_TO_SEC(appointment_time) < ?
        FOR UPDATE
    ");
    $stmt->bind_param("isii", $health_center_id, $date, $startSec, $endSec);
    $stmt->execute();
    $taken = $stmt->get_result()->num_rows;
    $stmt->close();

    if ($taken >= ONLINE_BOOKING_CAP) {
        $conn->rollback();
        back("error", "The " . minutes_label($wStart) . " – " . minutes_label($wEnd) . " window is fully booked online (the remaining spots are kept for walk-ins). Please choose another time.");
    }

    $stmt = $conn->prepare("
        INSERT INTO appointments (username, health_center_id, service, appointment_date, appointment_time, status)
        VALUES (?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->bind_param("sisss", $username, $health_center_id, $service, $date, $time);
    $stmt->execute();
    $newApptId = $stmt->insert_id;
    $stmt->close();
    $conn->commit();

    notify_with_email(
        $conn, $username,
        "Your appointment request for $service on " . date("M j, Y", strtotime($date)) . " has been submitted and is awaiting confirmation.",
        $newApptId
    );

    back("success", "Appointment requested. You'll see its status here once it's reviewed.");
}

if ($action === "cancel") {
    $id = (int) ($_POST["id"] ?? 0);
    $reason = trim($_POST["cancel_reason"] ?? "");

    if (mb_strlen($reason) < 5) {
        back("error", "Please give a reason for cancelling (at least 5 characters).");
    }
    $reason = mb_substr($reason, 0, 255);

    $stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND username = ?");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $appt = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$appt) back("error", "Appointment not found.");

    // Last-minute cancellation is prohibited: confirmed, or within 24 hours of the slot.
    $apptTime = strtotime($appt["appointment_date"] . " " . $appt["appointment_time"]);
    $isLocked = $appt["status"] === "Confirmed" || ($apptTime - time()) < 86400;
    if ($isLocked) {
        back("error", "This appointment can no longer be cancelled — it's confirmed or too close to the scheduled time.");
    }

    $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled', cancel_reason = ?, cancelled_at = NOW() WHERE id = ? AND username = ?");
    $stmt->bind_param("sis", $reason, $id, $username);
    $stmt->execute();
    $stmt->close();

    notify_with_email(
        $conn, $username,
        "You cancelled your " . $appt["service"] . " appointment on " . date("M j, Y", strtotime($appt["appointment_date"])) . ". Reason: $reason",
        $id
    );

    back("success", "Appointment cancelled.");
}

// Patient answers the reminder from inside the dashboard (same effect as the email link).
if ($action === "confirm_attendance") {
    $id = (int) ($_POST["id"] ?? 0);
    $stmt = $conn->prepare("
        UPDATE appointments SET attendance_confirmed = 1, attendance_confirmed_at = NOW()
        WHERE id = ? AND username = ? AND status = 'Confirmed' AND appointment_date >= CURDATE()
    ");
    $stmt->bind_param("is", $id, $username);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();

    if ($changed) back("success", "Thanks — your attendance is confirmed and your slot is kept.");
    back("error", "That appointment can't be confirmed.");
}

header("Location: patient_dashboard.php");
exit;
?>
