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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Settings - Scooby Doo</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { background:#000; color:#0f0; font-family:monospace; margin:0; }
    header { background:#111; padding:10px; text-align:center; }
    header h1 { margin:5px; font-size:22px; }
    nav a { color:#0ff; margin:0 10px; text-decoration:none; }
    nav a:hover { text-decoration:underline; }
    .container { padding:15px; }
    textarea { width:100%; height:120px; background:#111; color:#0f0; border:1px solid #333; border-radius:4px; padding:6px; }
    button { margin-top:10px; padding:8px 15px; border:none; border-radius:4px; background:#0f0; color:#000; cursor:pointer; }
  </style>
</head>
<body>
  <header>
    <h1>⚙️ Scooby Doo - Settings</h1>
    <nav>
      <a href="index.php">🏠 Home</a>
      <a href="settings.php">⚙️ Settings</a>
      <a href="logout.php" style="color:red;">🚪 Logout</a>
    </nav>
  </header>

  <div class="container">
    <?php if (!empty($msg)) echo "<p>$msg</p>"; ?>
    <form method="post">
      <label>Instagram Cookies:</label><br>
      <textarea name="cookies"><?php echo htmlspecialchars($cookies); ?></textarea><br>
      <button type="submit">💾 Save Cookies</button>
    </form>
  </div>
</body>
</html>
