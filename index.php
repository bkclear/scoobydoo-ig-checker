<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$filename    = "usernames.txt";
$lastUpdated = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// =============================
// 📌 Load usernames
// =============================
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// =============================
// 📌 Process form
// =============================
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
    }

    if ($action === "addUser" && !empty($_POST["newUser"])) {
        $newUser = trim($_POST["newUser"]);
        if ($newUser && !in_array($newUser, $savedUsernames)) {
            $savedUsernames[] = $newUser;
            file_put_contents($filename, implode("\n", $savedUsernames));
        }
    }

    if ($action === "deleteUser" && !empty($_POST["deleteUser"])) {
        $deleteUser = $_POST["deleteUser"];
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
    body {
      font-family: "Courier New", monospace;
      padding: 15px;
      background: #000;
      color: #00ff00;
    }
    h1 {
      text-align: center;
      color: #00ff00;
      text-shadow: 0 0 10px #00ff00, 0 0 20px #0f0;
      margin-bottom: 20px;
    }
    form {
      background: #111;
      padding: 15px;
      border-radius: 8px;
      border: 1px solid #0f0;
      max-width: 600px;
      margin: 15px auto;
    }
    label {
      display: block;
      margin-bottom: 5px;
      color: #0f0;
    }
    input, textarea {
      width: 100%;
      padding: 8px;
      background: #000;
      color: #0f0;
      border: 1px solid #0f0;
      border-radius: 5px;
      font-family: "Courier New", monospace;
      margin-bottom: 10px;
    }
    button {
      margin-top: 8px;
      padding: 8px 12px;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-weight: bold;
      background: #0f0;
      color: #000;
      transition: 0.3s;
    }
    button:hover { background: #090; color: #fff; }
    small {
      color: #0f0;
      display: block;
      margin-bottom: 10px;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 20px;
      background: #111;
      border: 1px solid #0f0;
    }
    th, td {
      padding: 10px;
      border-bottom: 1px solid #0f0;
      text-align: left;
    }
    th { background: #000; }
    .badge {
      padding: 4px 8px;
      border-radius: 12px;
      color: #000;
      font-size: 13px;
      font-weight: bold;
    }
    .exists { background: #0f0; }
    .not_found { background: #f00; color: #fff; }
    .error { background: #ff0; color: #000; }
    .invalid_session { background: #666; color: #fff; }
    .masked { cursor: pointer; color: #0f0; text-decoration: underline; }
    .countdown { font-size: 12px; color: #0f0; margin-top: 4px; }
  </style>
</head>
<body>
  <h1>🐾 Scooby Doo 🕵️ Hacker Panel</h1>

  <!-- Save Cookies -->
  <form method="post">
    <label>Paste Instagram Cookies:</label>
    <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
    <small>Last updated: <?php echo $lastUpdated; ?></small>
    <button type="submit" name="action" value="saveCookies">💾 Save Cookies</button>
  </form>

  <!-- Add Username -->
  <form method="post">
    <label>Add Username:</label>
    <input type="text" name="newUser" placeholder="example123">
    <button type="submit" name="action" value="addUser">➕ Add</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table id="userTable">
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($savedUsernames as $i=>$username): ?>
    <tr id="row<?php echo $i; ?>">
      <td>
        <span class="masked" onclick="copyUsername('<?php echo $username; ?>')">
          <?php echo str_repeat('*', strlen($username)); ?>
        </span>
      </td>
      <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
      <td id="followers<?php echo $i; ?>">-</td>
      <td id="following<?php echo $i; ?>">-</td>
      <td>
        <button type="button" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
        <button type="button" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱ Auto</button>
        <form method="post" style="display:inline;">
          <input type="hidden" name="deleteUser" value="<?php echo $username; ?>">
          <button type="submit" name="action" value="deleteUser">❌ Delete</button>
        </form>
        <div class="countdown" id="countdown<?php echo $i; ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

<script>
let interval = 30; // seconds for auto-refresh
let timers = {};

function copyUsername(username){
  navigator.clipboard.writeText(username).then(()=>{
    alert("Copied: " + username);
  });
}

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

      sortTable();
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

// 📌 Auto-sort rows based on status
function sortTable() {
  let table = document.getElementById("userTable");
  let rows = Array.from(table.rows).slice(1);

  let order = { "exists": 1, "error": 2, "not_found": 3, "invalid_session": 4 };

  rows.sort((a, b) => {
    let sa = a.querySelector("td:nth-child(2) .badge").classList[1] || "error";
    let sb = b.querySelector("td:nth-child(2) .badge").classList[1] || "error";
    return order[sa] - order[sb];
  });

  rows.forEach(r => table.appendChild(r));
}
</script>
</body>
</html>