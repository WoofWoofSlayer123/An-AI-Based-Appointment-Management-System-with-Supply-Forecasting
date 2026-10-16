<?php
session_start();
include "connection.php";
include "auth_check.php";

if ($_SESSION["role"] !== "staff") {
    header("Location: doctor_dashboard.php");
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

$action = $_POST["action"] ?? "";

if ($action === "add") {
    $name = trim($_POST["name"]);
    $unit = trim($_POST["unit"]);
    $stock = (int) $_POST["stock_quantity"];
    $threshold = (int) $_POST["reorder_threshold"];

    if ($name === "" || $stock < 0 || $threshold < 0) {
        header("Location: staff_dashboard.php?tab=inventory&type=error&msg=" . urlencode("Please fill in valid values."));
        exit;
    }

    $stmt = $conn->prepare("
        INSERT INTO medicines (health_center_id, name, unit, stock_quantity, reorder_threshold)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issii", $centerId, $name, $unit, $stock, $threshold);
    $stmt->execute();
    $stmt->close();

    header("Location: staff_dashboard.php?tab=inventory&type=success&msg=" . urlencode("Medicine added."));
    exit;
}

if ($action === "restock") {
    $medId = $_POST["medicine_id"];
    $addQty = (int) $_POST["add_quantity"];

    if ($addQty <= 0) {
        header("Location: staff_dashboard.php?tab=inventory&type=error&msg=" . urlencode("Enter a quantity greater than zero."));
        exit;
    }

    // Scoping check — can only restock medicines that belong to this center.
    $stmt = $conn->prepare("UPDATE medicines SET stock_quantity = stock_quantity + ? WHERE id = ? AND health_center_id = ?");
    $stmt->bind_param("iii", $addQty, $medId, $centerId);
    $stmt->execute();
    $stmt->close();

    header("Location: staff_dashboard.php?tab=inventory&type=success&msg=" . urlencode("Stock updated."));
    exit;
}

header("Location: staff_dashboard.php?tab=inventory");
exit;
?>
