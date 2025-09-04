<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $cookies = trim($_POST["cookies"]);
    $stmt = $db->prepare("UPDATE users SET cookies = ? WHERE id = ?");
    $stmt->execute([$cookies, $user_id]);
    $msg = "✅ Cookies updated!";
}

$stmt = $db->prepare("SELECT cookies FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$cookies = $user["cookies"] ?? "";
?>
<!DOCTYPE html>
<html>
<head>
  <title>Settings - Scooby Doo</title>
</head>
<body style="background:black;color:lime;font-family:monospace;">
  <h1>⚙️ Settings</h1>
  <a href="index.php" style="color:#0ff;">⬅ Back</a> | 
  <a href="logout.php" style="color:red;">Logout</a>
  <hr>
  <?php if (!empty($msg)) echo "<p>$msg</p>"; ?>
  <form method="post">
    <label>Paste Instagram Cookies:</label><br>
    <textarea name="cookies" rows="5" style="width:100%;background:#111;color:#0f0;"><?php echo htmlspecialchars($cookies); ?></textarea><br>
    <button type="submit">Save Cookies</button>
  </form>
</body>
</html>
