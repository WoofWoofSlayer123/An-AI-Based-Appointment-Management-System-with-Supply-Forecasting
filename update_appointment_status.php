<?php
session_start();
include "connection.php";
include "auth_check.php";
include "notifications_helper.php";
include "email_helper.php";

// Confirming/rejecting is front-desk staff's job now — not the doctor's.
if ($_SESSION["role"] !== "staff") {
    header("Location: staff_dashboard.php");
    exit;
}

$username = $_SESSION["username"];

$stmt = $conn->prepare("SELECT health_center_id FROM patients WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$centerId = $stmt->get_result()->fetch_assoc()["health_center_id"];
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: staff_dashboard.php");
    exit;
}

$id = $_POST["id"];
$newStatus = $_POST["status"];
$assignedDoctor = $_POST["assigned_doctor"] ?? null;

if (!in_array($newStatus, ["Confirmed", "Rejected"])) {
    header("Location: staff_dashboard.php?type=error&msg=" . urlencode("Invalid status."));
    exit;
}

// Ownership check: the appointment has to belong to THIS staff member's
// health center, and still be Pending — a different center can't touch it,
// and an already-decided one can't be redone.
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND health_center_id = ? AND status = 'Pending'");
$stmt->bind_param("ii", $id, $centerId);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    header("Location: staff_dashboard.php?type=error&msg=" . urlencode("Appointment not found or already decided."));
    exit;
}

$doctorName = "";

if ($newStatus === "Confirmed") {
    if (!$assignedDoctor) {
        header("Location: staff_dashboard.php?tab=home&type=error&msg=" . urlencode("Please assign a doctor before confirming."));
        exit;
    }

    // Confirm the chosen doctor actually works at THIS center, and is a doctor.
    $stmt = $conn->prepare("
        SELECT patients.full_name FROM users
        JOIN patients ON patients.username = users.username
        WHERE users.username = ? AND users.role = 'doctor' AND patients.health_center_id = ?
    ");
    $stmt->bind_param("si", $assignedDoctor, $centerId);
    $stmt->execute();
    $doctorRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$doctorRow) {
        header("Location: staff_dashboard.php?tab=home&type=error&msg=" . urlencode("That doctor isn't valid for this health center."));
        exit;
    }
    $doctorName = $doctorRow["full_name"];

    $stmt = $conn->prepare("UPDATE appointments SET status = 'Confirmed', assigned_doctor = ? WHERE id = ?");
    $stmt->bind_param("si", $assignedDoctor, $id);
    $stmt->execute();
    $stmt->close();
} else {
    $stmt = $conn->prepare("UPDATE appointments SET status = 'Rejected' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->close();
}

$message = $newStatus === "Confirmed"
    ? "Your " . $appt["service"] . " appointment on " . date("M j, Y", strtotime($appt["appointment_date"])) . " has been confirmed with Dr. $doctorName."
    : "Your " . $appt["service"] . " appointment request on " . date("M j, Y", strtotime($appt["appointment_date"])) . " was not approved. Please contact the health center or book a new time.";

notify_with_email($conn, $appt["username"], $message, $id);

// The doctor only ever found out about a new assignment by refreshing their
// schedule — let them know directly instead.
if ($newStatus === "Confirmed") {
    $patientNameStmt = $conn->prepare("SELECT full_name FROM patients WHERE username = ?");
    $patientNameStmt->bind_param("s", $appt["username"]);
    $patientNameStmt->execute();
    $patientName = $patientNameStmt->get_result()->fetch_assoc()["full_name"] ?? "A patient";
    $patientNameStmt->close();

    notify_with_email(
        $conn, $assignedDoctor,
        "You've been assigned a new patient: $patientName for " . $appt["service"] . " on " . date("M j, Y", strtotime($appt["appointment_date"])) . " at " . date("g:i A", strtotime($appt["appointment_time"])) . ".",
        $id
    );
}

header("Location: staff_dashboard.php?tab=home&type=success&msg=" . urlencode("Appointment $newStatus."));
exit;
?>
