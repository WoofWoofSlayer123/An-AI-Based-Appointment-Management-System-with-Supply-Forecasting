<?php
session_start();
include "connection.php";
include "auth_check.php";

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

$stmt = $conn->prepare("SELECT name, unit, stock_quantity, reorder_threshold FROM medicines WHERE health_center_id = ? ORDER BY name");
$stmt->bind_param("i", $centerId);
$stmt->execute();
$result = $stmt->get_result();

header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=medicine_inventory_" . date("Y-m-d") . ".csv");

$out = fopen("php://output", "w");
fputcsv($out, ["Medicine", "Unit", "Stock", "Low-Stock Threshold", "Status"]);
while ($row = $result->fetch_assoc()) {
    $status = $row["stock_quantity"] <= $row["reorder_threshold"] ? "Low" : "OK";
    fputcsv($out, [$row["name"], $row["unit"], $row["stock_quantity"], $row["reorder_threshold"], $status]);
}
fclose($out);
exit;
?>
