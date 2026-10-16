<?php
session_start();
include "connection.php";
include "auth_check.php";

if ($_SESSION["role"] !== "admin") {
    header("Location: staff_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: admin_dashboard.php");
    exit;
}

$action = $_POST["action"] ?? "";

if ($action === "approve") {
    $targetUsername = $_POST["username"];
    $newRole = $_POST["new_role"];
    $centerId = $_POST["health_center_id"];

    if (!in_array($newRole, ["staff", "doctor"])) {
        header("Location: admin_dashboard.php?tab=accounts&type=error&msg=" . urlencode("Invalid role."));
        exit;
    }

    // Only act on an account that's actually still pending — prevents
    // re-approving something already resolved via a stale page/double click.
    $stmt = $conn->prepare("
        SELECT users.username FROM users
        JOIN patients ON patients.username = users.username
        WHERE users.username = ? AND users.role = 'patient' AND patients.pending_staff = 1
    ");
    $stmt->bind_param("s", $targetUsername);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$valid) {
        header("Location: admin_dashboard.php?tab=accounts&type=error&msg=" . urlencode("Request not found or already resolved."));
        exit;
    }

    // Role and health center are set together, in one action — never separately.
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE username = ?");
    $stmt->bind_param("ss", $newRole, $targetUsername);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE patients SET health_center_id = ?, pending_staff = 0 WHERE username = ?");
    $stmt->bind_param("is", $centerId, $targetUsername);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_dashboard.php?tab=accounts&type=success&msg=" . urlencode("Account approved as $newRole."));
    exit;
}

if ($action === "reject") {
    $targetUsername = $_POST["username"];

    // Rejecting just clears the flag — role was never touched, so the
    // account simply continues on as a normal patient account.
    $stmt = $conn->prepare("UPDATE patients SET pending_staff = 0 WHERE username = ?");
    $stmt->bind_param("s", $targetUsername);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_dashboard.php?tab=accounts&type=success&msg=" . urlencode("Request rejected."));
    exit;
}

if ($action === "move") {
    $targetUsername = $_POST["username"];
    $newCenterId = $_POST["health_center_id"];

    // Only move an account that's actually staff/doctor — patients and
    // admins don't have a "center" in the same sense.
    $stmt = $conn->prepare("SELECT role FROM users WHERE username = ? AND role IN ('staff','doctor')");
    $stmt->bind_param("s", $targetUsername);
    $stmt->execute();
    $valid = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$valid) {
        header("Location: admin_dashboard.php?tab=accounts&type=error&msg=" . urlencode("That account isn't a staff or doctor account."));
        exit;
    }

    $stmt = $conn->prepare("UPDATE patients SET health_center_id = ? WHERE username = ?");
    $stmt->bind_param("is", $newCenterId, $targetUsername);
    $stmt->execute();
    $stmt->close();

    header("Location: admin_dashboard.php?tab=accounts&type=success&msg=" . urlencode("Account moved."));
    exit;
}

header("Location: admin_dashboard.php");
exit;
?>