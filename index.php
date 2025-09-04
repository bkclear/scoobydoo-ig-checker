<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$filename    = "usernames.txt";
$lastUpdated = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// =============================
// Handle AJAX refresh (no page reload)
// =============================
if (isset($_GET['ajax']) && $_GET['ajax'] === "1" && !empty($_GET['username'])) {
    $username = trim($_GET['username']);
    $cookies  = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
    $csrftoken = "";

    function checkUser($username, $cookies, $csrftoken) {
        $url = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);

        $headers = [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
            "Accept: application/json",
            "Referer: https://www.instagram.com/",
            "X-CSRFToken: $csrftoken",
            "X-IG-App-ID: 936619743392459",
            "Cookie: $cookies"
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            $data = json_decode($response, true);
            if (isset($data['data']['user'])) {
                $user = $data['data']['user'];
                return [
                    "status" => "exists",
                    "followers" => $user['edge_followed_by']['count'] ?? 0,
                    "following" => $user['edge_follow']['count'] ?? 0
                ];
            } else {
                return ["status" => "not_found", "followers" => 0, "following" => 0];
            }
        } elseif ($httpcode == 401 || $httpcode == 403) {
            return ["status" => "invalid_session", "followers" => 0, "following" => 0];
        } else {
            return ["status" => "error", "followers" => 0, "following" => 0];
        }
    }

    header("Content-Type: application/json");
    echo json_encode(checkUser($username, $cookies, $csrftoken));
    exit;
}

// =============================
// Normal Page Logic
// =============================
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
    }

    if ($action === "addUsername" && !empty($_POST["newUsername"])) {
        $newUsername = trim($_POST["newUsername"]);
        if (!in_array($newUsername, $savedUsernames)) {
            $savedUsernames[] = $newUsername;
            file_put_contents($filename, implode("\n", $savedUsernames));
        }
    }

    if ($action === "bulkAdd" && !empty($_POST["bulkUsernames"])) {
        $bulkList = explode("\n", trim($_POST["bulkUsernames"]));
        foreach ($bulkList as $name) {
            $name = trim($name);
            if ($name !== "" && !in_array($name, $savedUsernames)) {
                $savedUsernames[] = $name;
            }
        }
        file_put_contents($filename, implode("\n", $savedUsernames));
    }

    if ($action === "deleteUsername" && !empty($_POST["deleteUser"])) {
        $deleteUser = trim($_POST["deleteUser"]);
        $savedUsernames = array_filter($savedUsernames, fn($u) => $u !== $deleteUser);
        file_put_contents($filename, implode("\n", $savedUsernames));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo 🐾</title>
  <style>
    body { font-family: monospace; padding:15px; background:#000; color:#0f0; }
    h2 { text-align:center; color:#0f0; }
    form { background:#111; padding:15px; border-radius:8px; max-width:600px; margin:auto; color:#0f0; margin-bottom:20px; }
    input, textarea { width:100%; padding:8px; border:1px solid #0f0; border-radius:5px; background:#000; color:#0f0; }
    button { margin-top:8px; padding:10px 15px; border:none; border-radius:5px; cursor:pointer; }
    .saveBtn { background:#060; color:#0f0; }
    .addBtn { background:#030; color:#0f0; }
    .bulkBtn { background:#004; color:#0f0; }
    .deleteBtn { background:#600; color:#fff; }
    .refreshBtn { background:#060; color:#0f0; }
    .autoBtn { background:#033; color:#0f0; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; color:#0f0; }
    th, td { padding:10px; border-bottom:1px solid #0f0; text-align:left; }
    .badge { padding:4px 8px; border-radius:12px; color:#000; font-size:13px; }
    .exists { background:#0f0; color:#000; }
    .not_found { background:#f00; color:#fff; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#666; color:#fff; }
    .countdown { font-size:12px; color:#0f0; margin-top:4px; }
    .clickable { cursor:pointer; text-decoration:underline; }
  </style>
</head>
<body>
  <h2>🐾 Scooby Doo Username Checker</h2>
  
  <!-- Cookies form -->
  <form method="post">
    <label>Paste Instagram cookies:</label>
    <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
    <br><small>Last updated: <?php echo $lastUpdated; ?></small><br>
    <button type="submit" name="action" value="saveCookies" class="saveBtn">💾 Save Cookies</button>
  </form>

  <!-- Add single username -->
  <form method="post">
    <label>Add Username:</label>
    <input type="text" name="newUsername" placeholder="Enter username">
    <button type="submit" name="action" value="addUsername" class="addBtn">➕ Add</button>
  </form>

  <!-- Bulk paste usernames -->
  <form method="post">
    <label>Bulk Add Usernames (one per line):</label>
    <textarea name="bulkUsernames" rows="5" placeholder="user1&#10;user2&#10;user3"></textarea>
    <button type="submit" name="action" value="bulkAdd" class="bulkBtn">📥 Bulk Add</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table>
    <tr>
      <th>Username (click to copy)</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($savedUsernames as $i => $username): ?>
    <tr id="row<?php echo $i; ?>">
      <td class="clickable" onclick="copyUsername('<?php echo $username; ?>')">@<?php echo htmlspecialchars($username); ?></td>
      <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
      <td id="followers<?php echo $i; ?>">-</td>
      <td id="following<?php echo $i; ?>">-</td>
      <td>
        <button type="button" class="refreshBtn" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
        <button type="button" class="autoBtn" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱ Auto</button>
        <form method="post" style="display:inline;">
          <input type="hidden" name="deleteUser" value="<?php echo htmlspecialchars($username); ?>">
          <button type="submit" name="action" value="deleteUsername" class="deleteBtn">🗑 Delete</button>
        </form>
        <div class="countdown" id="countdown<?php echo $i; ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<script>
let interval = 120;
let timers = {};

function copyUsername(username) {
  navigator.clipboard.writeText(username).then(() => {
    alert("Copied: @" + username);
  });
}

function refreshUser(username, index) {
  fetch("?ajax=1&username=" + encodeURIComponent(username))
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
</script>
</body>
</html>
