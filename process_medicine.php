<?php
session_start();

include "connection.php";
include "auth_check.php";

if (($_SESSION["role"] ?? "") !== "staff") {
    header("Location: staff_dashboard.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: staff_dashboard.php?tab=inventory");
    exit;
}

$username = $_SESSION["username"] ?? "";

function inventoryRedirect($type, $message)
{
    header(
        "Location: staff_dashboard.php?tab=inventory&type=" .
        urlencode($type) . "&msg=" . urlencode($message)
    );
    exit;
}

// Verify staff health center.
$stmt = $conn->prepare(
    "SELECT health_center_id FROM patients WHERE username = ?"
);
$stmt->bind_param("s", $username);
$stmt->execute();
$staffRow = $stmt->get_result()->fetch_assoc();
$stmt->close();

$centerId = (int) ($staffRow["health_center_id"] ?? 0);

if ($centerId <= 0) {
    inventoryRedirect("error", "No health center is assigned to your account.");
}

$action = $_POST["action"] ?? "";

try {
    if ($action === "add") {
        $name = trim($_POST["name"] ?? "");
        $unit = trim($_POST["unit"] ?? "");
        $stockInput = $_POST["stock_quantity"] ?? "";
        $thresholdInput = $_POST["reorder_threshold"] ?? "";

        if (
            $name === "" ||
            $unit === "" ||
            filter_var($stockInput, FILTER_VALIDATE_INT) === false ||
            filter_var($thresholdInput, FILTER_VALIDATE_INT) === false
        ) {
            inventoryRedirect("error", "Enter a valid medicine name, unit, stock, and threshold.");
        }

        $acquisitionDate = trim($_POST["acquisition_date"] ?? "");
        $expirationDate = trim($_POST["expiration_date"] ?? "");

        $acquisitionDate = $acquisitionDate !== ""
            ? $acquisitionDate
            : null;

        $expirationDate = $expirationDate !== ""
            ? $expirationDate
            : null;

        $validDate = function ($date) {
            if ($date === null) {
                return true;
            }

            $parsed = DateTimeImmutable::createFromFormat("!Y-m-d", $date);

            return $parsed !== false
                && $parsed->format("Y-m-d") === $date;
        };

        if (!$validDate($acquisitionDate) || !$validDate($expirationDate)) {
            die("Please provide valid acquisition and expiration dates.");
        }

        if (
            $acquisitionDate !== null &&
            $expirationDate !== null &&
            $expirationDate < $acquisitionDate
        ) {
            die("The expiration date cannot be earlier than the acquisition date.");
        }


        $stock = (int) $stockInput;
        $threshold = (int) $thresholdInput;

        if ($stock < 0 || $threshold < 0) {
            inventoryRedirect("error", "Stock and reorder threshold cannot be negative.");
        }

        $conn->begin_transaction();

        // Create medicine.

        $stmt = $conn->prepare(
            "INSERT INTO medicines
        (
            health_center_id,
            name,
            unit,
            stock_quantity,
            reorder_threshold,
            acquisition_date,
            expiration_date
        )
     VALUES (?, ?, ?, ?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "issiiss",
            $centerId,
            $medicineName,
            $unit,
            $stockQuantity,
            $reorderThreshold,
            $acquisitionDate,
            $expirationDate
        );

        $stmt->execute();
        $medicineId = (int) $conn->insert_id;
        $stmt->close();

        // Record opening stock.
        $stmt = $conn->prepare("
            INSERT INTO medicine_stock_movements
                (
                    medicine_id,
                    health_center_id,
                    performed_by,
                    movement_type,
                    quantity,
                    stock_before,
                    stock_after,
                    reason
                )
            VALUES (?, ?, ?, 'INITIAL', ?, 0, ?, 'Initial stock')
        ");
        $stmt->bind_param(
            "iisii",
            $medicineId,
            $centerId,
            $username,
            $stock,
            $stock
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        inventoryRedirect("success", "Medicine added and initial stock recorded.");
    }

    if ($action === "restock") {
        $medIdInput = $_POST["medicine_id"] ?? "";
        $quantityInput = $_POST["add_quantity"] ?? "";

        if (
            filter_var($medIdInput, FILTER_VALIDATE_INT) === false ||
            filter_var($quantityInput, FILTER_VALIDATE_INT) === false
        ) {
            inventoryRedirect("error", "Enter a valid medicine and restock quantity.");
        }

        $medId = (int) $medIdInput;
        $addQty = (int) $quantityInput;

        if ($medId <= 0 || $addQty <= 0) {
            inventoryRedirect("error", "Restock quantity must be greater than zero.");
        }

        $conn->begin_transaction();

        // Lock the medicine row to prevent conflicting stock updates.
        $stmt = $conn->prepare("
            SELECT stock_quantity
            FROM medicines
            WHERE id = ? AND health_center_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("ii", $medId, $centerId);
        $stmt->execute();
        $medicine = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$medicine) {
            throw new RuntimeException(
                "Medicine not found in your assigned health center."
            );
        }

        $before = (int) $medicine["stock_quantity"];

        // Prevent integer overflow.
        if ($addQty > PHP_INT_MAX - $before) {
            throw new RuntimeException("The resulting stock quantity is too large.");
        }

        $after = $before + $addQty;

        $stmt = $conn->prepare("
            UPDATE medicines
            SET stock_quantity = ?
            WHERE id = ? AND health_center_id = ?
        ");
        $stmt->bind_param("iii", $after, $medId, $centerId);
        $stmt->execute();
        $stmt->close();

        // Record the restock in the same transaction.
        $stmt = $conn->prepare("
            INSERT INTO medicine_stock_movements
                (
                    medicine_id,
                    health_center_id,
                    performed_by,
                    movement_type,
                    quantity,
                    stock_before,
                    stock_after,
                    reason
                )
            VALUES (?, ?, ?, 'RESTOCK', ?, ?, ?, 'Stock replenishment')
        ");
        $stmt->bind_param(
            "iisiii",
            $medId,
            $centerId,
            $username,
            $addQty,
            $before,
            $after
        );
        $stmt->execute();
        $stmt->close();

        $conn->commit();

        inventoryRedirect("success", "Stock restocked and movement recorded.");
    }

    inventoryRedirect("error", "Invalid inventory action.");

} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $rollbackError) {
        // The transaction may already have been rolled back.
    }

    error_log("Medicine inventory error: " . $e->getMessage());

    inventoryRedirect(
        "error",
        "The inventory operation could not be completed. Please try again."
    );
}
?>