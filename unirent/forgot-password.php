<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/functions.php';
include 'includes/header.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
            $stmt->bind_param("sss", $token, $expires, $email);
            $stmt->execute();

            // In real app: send email with this link
            $reset_link = "http://localhost/unirent/reset-password.php?token=$token";
            $message = "Reset link: <a href='$reset_link'>$reset_link</a>";
        } else {
            $message = "No account found with that email.";
        }
    } else {
        $message = "Please enter your email address.";
    }
}
?>

<h2>Forgot Password</h2>

<form method="post">
    <label>Email:</label>
    <input type="email" name="email" required>
    <button type="submit">Send Reset Link</button>
</form>

<p><?php echo $message; ?></p>

<?php include 'includes/footer.php'; ?>
