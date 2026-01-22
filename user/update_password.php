<?php
require_once __DIR__ . '/../backend/config.php';   

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $resetToken   = $_POST['token'] ?? '';   
    $plainNewPass = $_POST['new_password'] ?? ''; 

    // hash new password 
    $hashedPwd = password_hash($plainNewPass, PASSWORD_BCRYPT);

    // Step 1: check if token exists 
    $checkToken = $conn->prepare("SELECT id, expires_at FROM password_resets WHERE token=?");
    if (!$checkToken) {
        die("DB error while preparing token check: " . $conn->error);
    }

    $checkToken->bind_param("s", $resetToken);
    $checkToken->execute();
    $checkToken->bind_result($userId, $expiry);

    if ($checkToken->fetch()) {
        //  Step 2: check if link expired 
        if (strtotime($expiry) < time()) {
            die("Oops! This reset link has already expired. Please request again.");
        }
        $checkToken->close();

        //  update the password for this user 
        $updatePwd = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $updatePwd->bind_param("si", $hashedPwd, $userId);
        $updatePwd->execute();
        $updatePwd->close();

        //  remove the used token 
        $delToken = $conn->prepare("DELETE FROM password_resets WHERE token=?");
        $delToken->bind_param("s", $resetToken);
        $delToken->execute();
        $delToken->close();

        //  redirect to login 
        header("Location: login.php?message=Password+updated+successfully");
        exit();

    } else {
        echo "Invalid or already used token!";
    }
}
?>
