
<?php
session_start();

require_once "connection.php";
require_once "auth_check.php";

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
| Assumptions:
| - The database connection variable is $conn.
| - The logged-in username is stored in $_SESSION["username"].
| - Passwords in users.password use password_hash().
*/

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

/*
|--------------------------------------------------------------------------
| Helper: Redirect with a message
|--------------------------------------------------------------------------
*/

function redirectWithMessage(
    string $tab,
    string $type,
    string $message
): void {
    header(
        "Location: patient_dashboard.php?tab=" .
        urlencode($tab) .
        "&type=" . urlencode($type) .
        "&msg=" . urlencode($message)
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Check authentication and request method
|--------------------------------------------------------------------------
*/

$username = $_SESSION["username"] ?? null;

if (!is_string($username) || trim($username) === "") {
    header("Location: login.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: patient_dashboard.php");
    exit;
}

/*
|--------------------------------------------------------------------------
| CSRF Protection
|--------------------------------------------------------------------------
| Each form must submit the token stored in the user's session.
*/

$sessionToken = $_SESSION["csrf_token"] ?? "";
$submittedToken = $_POST["csrf_token"] ?? "";

if (
    !is_string($sessionToken) ||
    $sessionToken === "" ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    redirectWithMessage(
        "profile",
        "error",
        "Your session validation failed. Please refresh the page and try again."
    );
}

$action = $_POST["action"] ?? "";

if (!is_string($action)) {
    redirectWithMessage("profile", "error", "Invalid request.");
}

/*
|--------------------------------------------------------------------------
| Process Account Updates
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Update Patient Profile
    |--------------------------------------------------------------------------
    */

    if ($action === "profile") {

        $dob = trim($_POST["date_of_birth"] ?? "");
        $gender = trim($_POST["gender"] ?? "");
        $address = trim($_POST["address"] ?? "");
        $ecName = trim($_POST["emergency_contact_name"] ?? "");
        $ecNumber = trim($_POST["emergency_contact_number"] ?? "");

        // Validate required fields.
        if (
            $dob === "" ||
            $gender === "" ||
            $address === "" ||
            $ecName === "" ||
            $ecNumber === ""
        ) {
            redirectWithMessage(
                "profile",
                "error",
                "Please complete all required profile fields."
            );
        }

        // Strict date validation.
        $date = DateTimeImmutable::createFromFormat("!Y-m-d", $dob);
        $dateErrors = DateTimeImmutable::getLastErrors();

        $validDate = (
            $date !== false &&
            $date->format("Y-m-d") === $dob &&
            ($dateErrors === false ||
                (
                    $dateErrors["warning_count"] === 0 &&
                    $dateErrors["error_count"] === 0
                )
            )
        );

        if (!$validDate) {
            redirectWithMessage(
                "profile",
                "error",
                "Please enter a valid date of birth."
            );
        }

        if ($dob > date("Y-m-d")) {
            redirectWithMessage(
                "profile",
                "error",
                "Date of birth cannot be in the future."
            );
        }

        // Validate gender against the supported options.
        if (!in_array($gender, ["Male", "Female", "Other"], true)) {
            redirectWithMessage(
                "profile",
                "error",
                "Please select a valid gender."
            );
        }

        // Validate reasonable input lengths.
        if (
            strlen($address) > 255 ||
            strlen($ecName) > 150
        ) {
            redirectWithMessage(
                "profile",
                "error",
                "One or more profile fields exceed the allowed length."
            );
        }

        // Validate emergency contact number.
        if (!preg_match('/^[0-9+\-\s()]{7,20}$/', $ecNumber)) {
            redirectWithMessage(
                "profile",
                "error",
                "Please enter a valid emergency contact number."
            );
        }

        $stmt = $conn->prepare("
            UPDATE patients
            SET
                date_of_birth = ?,
                gender = ?,
                address = ?,
                emergency_contact_name = ?,
                emergency_contact_number = ?
            WHERE username = ?
        ");

        $stmt->bind_param(
            "ssssss",
            $dob,
            $gender,
            $address,
            $ecName,
            $ecNumber,
            $username
        );

        $stmt->execute();
        $stmt->close();

        redirectWithMessage(
            "profile",
            "success",
            "Profile updated successfully."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Update Contact Information
    |--------------------------------------------------------------------------
    */

    if ($action === "contact") {

        $email = trim($_POST["email"] ?? "");
        $mobile = trim($_POST["mobile"] ?? "");

        if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirectWithMessage(
                "account",
                "error",
                "Please enter a valid email address."
            );
        }

        if (strlen($email) > 254) {
            redirectWithMessage(
                "account",
                "error",
                "The email address is too long."
            );
        }

        // Mobile number is optional, but validate it when provided.
        if (
            $mobile !== "" &&
            !preg_match('/^[0-9+\-\s()]{7,20}$/', $mobile)
        ) {
            redirectWithMessage(
                "account",
                "error",
                "Please enter a valid mobile number."
            );
        }

        // Ensure the email is not assigned to another account.
        $stmt = $conn->prepare("
            SELECT id
            FROM users
            WHERE email = ? AND username <> ?
            LIMIT 1
        ");

        $stmt->bind_param("ss", $email, $username);
        $stmt->execute();
        $stmt->store_result();

        $emailExists = $stmt->num_rows > 0;
        $stmt->close();

        if ($emailExists) {
            redirectWithMessage(
                "account",
                "error",
                "That email is already in use by another account."
            );
        }

        /*
         * Update both tables in one transaction so an error
         * does not leave the two records out of sync.
         */
        $conn->begin_transaction();

        try {

            $stmt = $conn->prepare("
                UPDATE users
                SET email = ?
                WHERE username = ?
            ");

            $stmt->bind_param("ss", $email, $username);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("
                UPDATE patients
                SET mobile = ?
                WHERE username = ?
            ");

            $stmt->bind_param("ss", $mobile, $username);
            $stmt->execute();
            $stmt->close();

            $conn->commit();

        } catch (Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        redirectWithMessage(
            "account",
            "success",
            "Contact details updated successfully."
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Change Password
    |--------------------------------------------------------------------------
    */

    if ($action === "password") {

        $current = $_POST["current_password"] ?? "";
        $newPass = $_POST["new_password"] ?? "";
        $confirmPass = $_POST["confirm_password"] ?? "";

        if (
            !is_string($current) ||
            !is_string($newPass) ||
            !is_string($confirmPass) ||
            $current === "" ||
            $newPass === "" ||
            $confirmPass === ""
        ) {
            redirectWithMessage(
                "account",
                "error",
                "Please complete all password fields."
            );
        }

        // Require the new password and confirmation to match.
        if (!hash_equals($newPass, $confirmPass)) {
            redirectWithMessage(
                "account",
                "error",
                "The new password and confirmation do not match."
            );
        }

        // Fetch the existing password hash.
        $stmt = $conn->prepare("
            SELECT password
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->bind_result($storedHash);

        $userFound = $stmt->fetch();
        $stmt->close();

        if (!$userFound || !is_string($storedHash)) {
            redirectWithMessage(
                "account",
                "error",
                "Unable to verify your account. Please try again."
            );
        }

        // Verify the current password.
        if (!password_verify($current, $storedHash)) {
            redirectWithMessage(
                "account",
                "error",
                "Your current password is incorrect."
            );
        }

        // Enforce password requirements.
        if (strlen($newPass) < 8) {
            redirectWithMessage(
                "account",
                "error",
                "The new password must be at least 8 characters."
            );
        }

        if (strlen($newPass) > 4096) {
            redirectWithMessage(
                "account",
                "error",
                "The new password is too long."
            );
        }

        if (!preg_match('/[0-9]/', $newPass)) {
            redirectWithMessage(
                "account",
                "error",
                "The new password must contain at least one number."
            );
        }

        if (!preg_match('/[^A-Za-z0-9]/', $newPass)) {
            redirectWithMessage(
                "account",
                "error",
                "The new password must contain at least one special character."
            );
        }

        if (password_verify($newPass, $storedHash)) {
            redirectWithMessage(
                "account",
                "error",
                "Your new password must be different from your current password."
            );
        }

        // Hash the new password before saving it.
        $hashed = password_hash($newPass, PASSWORD_DEFAULT);

        if ($hashed === false) {
            redirectWithMessage(
                "account",
                "error",
                "Unable to update your password. Please try again."
            );
        }

        $stmt = $conn->prepare("
            UPDATE users
            SET password = ?
            WHERE username = ?
        ");

        $stmt->bind_param("ss", $hashed, $username);
        $stmt->execute();
        $stmt->close();

        // Rotate the CSRF token after a password change.
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

        redirectWithMessage(
            "account",
            "success",
            "Password updated successfully."
        );
    }

    // Reject unsupported actions.
    redirectWithMessage(
        "profile",
        "error",
        "Invalid account update action."
    );

} catch (Throwable $e) {

    // Log technical details on the server, not in the browser.
    error_log("Patient Dashboard update_account.php error: " . $e->getMessage());

    redirectWithMessage(
        $action === "profile" ? "profile" : "account",
        "error",
        "An unexpected error occurred while saving your changes. Please try again."
    );
}
