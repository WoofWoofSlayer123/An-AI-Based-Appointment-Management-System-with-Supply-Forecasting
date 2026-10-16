<?php
session_start();
include "connection.php";
include "auth_check.php";
include "notifications_helper.php";

if ($_SESSION["role"] !== "doctor") {
    header("Location: staff_dashboard.php");
    exit;
}

$staffUsername = $_SESSION["username"];

$stmt = $conn->prepare("SELECT health_center_id FROM patients WHERE username = ?");
$stmt->bind_param("s", $staffUsername);
$stmt->execute();
$centerId = $stmt->get_result()->fetch_assoc()["health_center_id"];
$stmt->close();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: doctor_dashboard.php");
    exit;
}

$apptId = $_POST["appointment_id"];
$diagnosisText = trim($_POST["diagnosis"]);
$notes = trim($_POST["notes"] ?? "");
$medicineIds = $_POST["medicine_id"] ?? [];
$quantities = $_POST["quantity"] ?? [];
$instructions = $_POST["instructions"] ?? [];

// Ownership + status check — can only complete a Confirmed appointment that
// is specifically assigned to THIS doctor.
$stmt = $conn->prepare("SELECT * FROM appointments WHERE id = ? AND assigned_doctor = ? AND status = 'In consultation'");
$stmt->bind_param("is", $apptId, $staffUsername);
$stmt->execute();
$appt = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$appt) {
    header("Location: doctor_dashboard.php?tab=schedule&type=error&msg=" . urlencode("Appointment not found, already completed, not yet confirmed, or not assigned to you."));
    exit;
}

if ($diagnosisText === "") {
    header("Location: view_patient.php?id=$apptId&type=error&msg=" . urlencode("Diagnosis can't be empty."));
    exit;
}

$patientUsername = $appt["username"];

// Diagnosis record — permanent, never edited after this point.
$stmt = $conn->prepare("
    INSERT INTO diagnoses (appointment_id, username, staff_username, diagnosis, notes)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param("issss", $apptId, $patientUsername, $staffUsername, $diagnosisText, $notes);
$stmt->execute();
$diagnosisId = $stmt->insert_id;
$stmt->close();

// Prescriptions — skip any row where no medicine was selected. Each valid
// row decrements stock, clamped at 0 rather than going negative.
for ($i = 0; $i < count($medicineIds); $i++) {
    $medId = $medicineIds[$i];
    $qty = (int) ($quantities[$i] ?? 0);
    $instr = trim($instructions[$i] ?? "");

    if (!$medId || $qty <= 0)
        continue;

    // Confirm the medicine actually belongs to this center before touching it.
    $stmt = $conn->prepare("SELECT * FROM medicines WHERE id = ? AND health_center_id = ?");
    $stmt->bind_param("ii", $medId, $centerId);
    $stmt->execute();
    $med = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$med)
        continue;


    $prescribedBy = $_SESSION["username"];

    $stmtPrescription = $conn->prepare("
        INSERT INTO prescriptions
            (diagnosis_id, medicine_id, quantity, instructions,
             prescribed_by, prescribed_at)
        VALUES (?, ?, ?, ?, ?, NOW())
        ");

    $stmtPrescription->bind_param(
        "iiiss",
        $diagnosisId,
        $medicineId,
        $quantity,
        $instructions,
        $prescribedBy
    );

    if (!$stmtPrescription->execute()) {
        throw new Exception("Failed to save prescription.");
    }

    $stmtPrescription->close();

    $stmt->bind_param("iiis", $diagnosisId, $medId, $qty, $instr);
    $stmt->execute();
    $stmt->close();

    $newStock = max(0, $med["stock_quantity"] - $qty);
    $stmt = $conn->prepare("UPDATE medicines SET stock_quantity = ? WHERE id = ?");
    $stmt->bind_param("ii", $newStock, $medId);
    $stmt->execute();
    $stmt->close();
}

$stmt = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ? AND assigned_doctor = ? AND status = 'In consultation'");
$stmt->bind_param("is", $appointmentId, $staffUsername);
$stmt->execute();

if ($stmt->affected_rows !== 1) {
    $stmt->close();

    // Do not report a successful completion if the status
    // transition failed. A transaction should roll back
    // the diagnosis, prescriptions, and stock deductions.
    exit("Unable to complete the consultation. Please reload the appointment.");
}

$stmt->close();

// Never put clinical content in the notification — just point them to log in.
create_notification(
    $conn,
    $patientUsername,
    "Your visit summary from " . date("M j, Y", strtotime($appt["appointment_date"])) . " is ready. Log in to view your diagnosis.",
    $apptId
);

header("Location: doctor_dashboard.php?tab=schedule&type=success&msg=" . urlencode("Visit completed and patient notified."));
exit;
?>