<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$filename    = "usernames.txt";
$lastUpdated = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Load usernames
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Process form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
    }

    if ($action === "startChecking") {
        $postedUsernames = explode("\n", trim($_POST["usernames"] ?? ""));
        $postedUsernames = array_map("trim", $postedUsernames);
        $postedUsernames = array_filter($postedUsernames);

        if (!empty($postedUsernames)) {
            file_put_contents($filename, implode("\n", $postedUsernames));
        }

        $savedUsernames = $postedUsernames;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo - Username Checker</title>
  <style>
    body { background:#000; color:#0f0; font-family:Courier, monospace; padding:15px; }
    h1 { text-align:center; color:#0f0; }
    a { color:#0f0; margin:0 10px; text-decoration:none; }
    form { background:#111; padding:15px; border:1px solid #0f0; border-radius:8px; max-width:600px; margin:auto; }
    textarea, input { width:100%; padding:8px; background:#000; color:#0f0; border:1px solid #0f0; border-radius:5px; }
    button { margin-top:8px; padding:10px 15px; border:none; border-radius:5px; cursor:pointer; background:#0f0; color:#000; font-weight:bold; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    th, td { padding:10px; border:1px solid #0f0; text-align:left; }
    .badge { padding:4px 8px; border-radius:12px; color:#000; font-size:13px; }
    .exists { background:#0f0; }
    .not_found { background:#f00; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#555; }
  </style>
</head>
<body>
  <h1>🕵️ Scooby Doo - Username Checker</h1>
  <div style="text-align:center;">
    <a href="index.php">🔍 Username Checker</a> | 
    <a href="followers.php">👥 Followers Viewer</a>
  </div>
  <br>
  <form method="post">
    <label>Paste Instagram cookies:</label>
    <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
    <br><small>Last updated: <?php echo $lastUpdated; ?></small><br>
    <button type="submit" name="action" value="saveCookies">💾 Save Cookies</button>

    <br><br>
    <label>Enter usernames (one per line):</label>
    <textarea name="usernames" rows="6"><?php echo htmlspecialchars(implode("\n", $savedUsernames)); ?></textarea>
    <br>
    <button type="submit" name="action" value="startChecking">▶ Start Checking</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table>
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
    </tr>
    <?php foreach ($savedUsernames as $i => $username): ?>
    <tr id="row<?php echo $i; ?>">
      <td>@<?php echo htmlspecialchars($username); ?></td>
      <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
      <td id="followers<?php echo $i; ?>">-</td>
      <td id="following<?php echo $i; ?>">-</td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</body>
</html>
