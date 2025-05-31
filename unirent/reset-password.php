<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'includes/header.php';

$token = $_GET['token'] ?? '';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    if (empty($password) || empty($confirm)) {
        $message = "Please fill in all fields.";
    } elseif ($password !== $confirm) {
        $message = "Passwords do not match.";
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($user = $result->fetch_assoc()) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update->bind_param("si", $hashed, $user['id']);
            $update->execute();
            $message = "Password has been reset. <a href='login.php'>Login now</a>";
        } else {
            $message = "Invalid or expired token.";
        }
    }
}
?>

<h2>Reset Password</h2>

<?php if (!empty($message)): ?>
    <p><?php echo $message; ?></p>
<?php endif; ?>

<?php if (empty($message) || strpos($message, 'reset') === false): ?>
<form method="post">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <label>New Password:</label>
    <input type="password" name="password" required><br>
    <label>Confirm Password:</label>
    <input type="password" name="confirm" required><br>
    <button type="submit">Reset Password</button>
</form>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
