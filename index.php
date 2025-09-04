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

// Handle actions
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

    if ($action === "deleteUser" && !empty($_POST['username'])) {
        $usernameToDelete = trim($_POST['username']);
        $savedUsernames = array_filter($savedUsernames, fn($u) => $u !== $usernameToDelete);
        file_put_contents($filename, implode("\n", $savedUsernames));
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
    button { margin:5px; padding:8px 12px; border:none; border-radius:5px; cursor:pointer; font-weight:bold; }
    .saveBtn, .checkBtn { background:#0f0; color:#000; }
    .refreshBtn { background:#00f; color:#fff; }
    .autoBtn { background:#0f0; color:#000; }
    .deleteBtn { background:#f00; color:#fff; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    th, td { padding:10px; border:1px solid #0f0; text-align:left; }
    .badge { padding:4px 8px; border-radius:12px; color:#000; font-size:13px; }
    .exists { background:#0f0; }
    .not_found { background:#f00; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#555; }
    .copyable { cursor:pointer; }
    .copyable:hover { text-decoration:underline; }
    .countdown { font-size:12px; color:#0f0; margin-top:4px; }
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
    <button type="submit" name="action" value="saveCookies" class="saveBtn">💾 Save Cookies</button>

    <br><br>
    <label>Enter usernames (one per line):</label>
    <textarea name="usernames" rows="6"><?php echo htmlspecialchars(implode("\n", $savedUsernames)); ?></textarea>
    <br>
    <label>Refresh Interval (seconds):</label>
    <input type="number" name="refreshTime" value="<?php echo $_POST['refreshTime'] ?? 120; ?>" style="width:80px;">
    <br>
    <button type="submit" name="action" value="startChecking" class="checkBtn">▶ Save Usernames</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table>
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($savedUsernames as $i => $username): ?>
    <tr id="row<?php echo $i; ?>">
      <td class="copyable" onclick="copyUsername('<?php echo $username; ?>')">@<?php echo htmlspecialchars($username); ?></td>
      <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
      <td id="followers<?php echo $i; ?>">-</td>
      <td id="following<?php echo $i; ?>">-</td>
      <td>
        <button type="button" class="refreshBtn" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
        <button type="button" class="autoBtn" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱ Auto</button>
        <form method="post" style="display:inline;">
          <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
          <button type="submit" name="action" value="deleteUser" class="deleteBtn">🗑 Delete</button>
        </form>
        <div class="countdown" id="countdown<?php echo $i; ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<script>
let interval = <?php echo intval($_POST['refreshTime'] ?? 120); ?>;
let timers = {};

function refreshUser(username, index) {
  fetch("refresh.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      let statusEl = document.getElementById("status" + index);
      let followersEl = document.getElementById("followers" + index);
      let followingEl = document.getElementById("following" + index);

      statusEl.textContent = data.status.replace("_"," ");
      statusEl.className = "badge " + data.status;
      followersEl.textContent = data.followers;
      followingEl.textContent = data.following;
    })
    .catch(err => console.error("Error:", err));
}

function toggleAuto(username, index) {
  if (timers[index]) {
    clearInterval(timers[index].intervalId);
    document.getElementById("countdown" + index).innerText = "";
    delete timers[index];
  } else {
    let seconds = interval;
    document.getElementById("countdown" + index).innerText = "Next refresh in " + seconds + "s";
    timers[index] = {
      intervalId: setInterval(() => {
        seconds--;
        if (seconds <= 0) {
          refreshUser(username, index);
          seconds = interval;
        }
        document.getElementById("countdown" + index).innerText = "Next refresh in " + seconds + "s";
      }, 1000)
    };
  }
}

function copyUsername(username) {
  navigator.clipboard.writeText(username);
  alert("Copied: @" + username);
}
</script>
</body>
</html>
