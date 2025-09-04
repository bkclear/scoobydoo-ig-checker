<?php
session_start();

$cookiesFile = "cookies.txt";
$filename    = "usernames.txt";
$lastUpdated = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Load usernames
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

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
    body { font-family: monospace; background:#000; color:#0f0; padding:15px; }
    h2 { text-align:center; color:#0f0; }
    form { background:#111; padding:15px; border-radius:8px; max-width:600px; margin:auto; color:#0f0; }
    textarea, input { width:100%; padding:8px; border:1px solid #0f0; border-radius:5px; background:#000; color:#0f0; }
    button { margin-top:8px; padding:10px 15px; border:none; border-radius:5px; cursor:pointer; font-weight:bold; }
    .saveBtn { background:#006400; color:#0f0; }
    .checkBtn { background:#003366; color:#0f0; }
    .refreshBtn { background:#444400; color:#0f0; margin-right:5px; }
    .autoBtn { background:#004444; color:#0f0; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    th, td { padding:10px; border-bottom:1px solid #0f0; text-align:left; }
    .badge { padding:4px 8px; border-radius:12px; color:#000; font-size:13px; font-weight:bold; }
    .exists { background:#0f0; color:#000; }
    .not_found { background:#f00; color:#fff; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#888; color:#fff; }
    .countdown { font-size:12px; color:#0f0; margin-top:4px; }
  </style>
</head>
<body>
  <h2>🐾 Scooby Doo - Instagram Username Checker</h2>
  <form method="post">
    <label>Paste Instagram cookies:</label>
    <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
    <br><small>Last updated: <?php echo $lastUpdated; ?></small><br>
    <button type="submit" name="action" value="saveCookies" class="saveBtn">💾 Save Cookies</button>

    <br><br>
    <label>Enter usernames (one per line):</label>
    <textarea name="usernames" rows="6"><?php echo htmlspecialchars(implode("\n", $savedUsernames)); ?></textarea>
    <br>
    <label>Check interval (seconds):</label>
    <input type="number" name="refreshTime" value="<?php echo $_POST['refreshTime'] ?? 120; ?>" style="width:80px; background:#000; color:#0f0; border:1px solid #0f0;">
    <br>
    <button type="submit" name="action" value="startChecking" class="checkBtn">▶ Start Checking</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table>
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Last Checked</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($savedUsernames as $i => $username): ?>
    <tr id="row<?php echo $i; ?>">
      <td><a href="https://instagram.com/<?php echo htmlspecialchars($username); ?>" target="_blank" style="color:#0f0;">@<?php echo htmlspecialchars($username); ?></a></td>
      <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
      <td id="followers<?php echo $i; ?>">-</td>
      <td id="following<?php echo $i; ?>">-</td>
      <td id="last<?php echo $i; ?>">Never</td>
      <td>
        <button type="button" class="refreshBtn" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
        <button type="button" class="autoBtn" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱ Auto Refresh</button>
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
      let lastEl = document.getElementById("last" + index);

      if (data.status === "exists") {
        statusEl.textContent = "✔ Exists";
        statusEl.className = "badge exists";
        followersEl.textContent = data.followers;
        followingEl.textContent = data.following;
      } 
      else if (data.status === "not_found") {
        statusEl.textContent = "❌ Not Found";
        statusEl.className = "badge not_found";
        followersEl.textContent = "-";
        followingEl.textContent = "-";
      }
      else if (data.status === "invalid_session") {
        statusEl.textContent = "⚠ Invalid Session";
        statusEl.className = "badge invalid_session";
        followersEl.textContent = "-";
        followingEl.textContent = "-";
      }
      else if (data.status === "error") {
        statusEl.textContent = "⚠ Error: " + (data.message || "Unknown");
        statusEl.className = "badge error";
        followersEl.textContent = "-";
        followingEl.textContent = "-";
      }
      else {
        statusEl.textContent = "⚠ Unexpected";
        statusEl.className = "badge error";
        followersEl.textContent = "-";
        followingEl.textContent = "-";
      }

      // update last checked
      let now = new Date();
      lastEl.textContent = now.toLocaleTimeString();
    })
    .catch(err => {
      let statusEl = document.getElementById("status" + index);
      statusEl.textContent = "⚠ Fetch Error";
      statusEl.className = "badge error";
      console.error("Fetch failed:", err);
    });
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
</script>
</body>
</html>
