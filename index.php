<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$userFile    = "usernames.txt";

// --- Load Cookies ---
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// --- Load Saved Usernames ---
$savedUsernames = [];
if (file_exists($userFile)) {
    $lines = file($userFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        [$u, $t] = array_pad(explode("|", $line), 2, "");
        $savedUsernames[] = ["username" => $u, "time" => $t];
    }
}

// --- Handle Actions ---
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    // Add Username
    if ($action === "addUsername" && !empty($_POST["newUsername"])) {
        $newUsername = trim($_POST["newUsername"]);
        $time = date("Y-m-d H:i:s");

        $already = array_column($savedUsernames, "username");
        if (!in_array($newUsername, $already)) {
            $savedUsernames[] = ["username" => $newUsername, "time" => $time];
            $lines = array_map(fn($row) => $row["username"] . "|" . $row["time"], $savedUsernames);
            file_put_contents($userFile, implode("\n", $lines));
        }
    }

    // Delete Username
    if ($action === "deleteUser" && !empty($_POST['username'])) {
        $usernameToDelete = trim($_POST['username']);
        $savedUsernames = array_filter($savedUsernames, fn($row) => $row["username"] !== $usernameToDelete);
        $lines = array_map(fn($row) => $row["username"] . "|" . $row["time"], $savedUsernames);
        file_put_contents($userFile, implode("\n", $lines));
    }

    // Save Cookies
    if ($action === "saveCookies" && isset($_POST['cookies'])) {
        file_put_contents($cookiesFile, trim($_POST['cookies']));
        $cookies = trim($_POST['cookies']);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo 🕵️ Username Checker</title>
  <style>
    body { background:#000; color:#0f0; font-family:monospace; padding:15px; }
    h1 { text-align:center; }
    nav { margin-bottom:20px; text-align:center; }
    nav button { background:#111; border:1px solid #0f0; color:#0f0; padding:10px 20px; cursor:pointer; margin:5px; border-radius:8px; }
    nav button.active { background:#0f0; color:#000; }
    section { display:none; }
    section.active { display:block; }
    form { margin:15px 0; }
    input, textarea { padding:6px; border:1px solid #0f0; border-radius:5px; background:#111; color:#0f0; width:100%; }
    button { padding:6px 12px; border:none; border-radius:5px; margin:3px; cursor:pointer; }
    .addBtn { background:#0f0; color:#000; }
    .deleteBtn { background:#f00; color:#fff; }
    .refreshBtn { background:#06f; color:#fff; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    th, td { padding:8px; border:1px solid #0f0; text-align:left; }
    .badge { padding:4px 8px; border-radius:12px; font-size:13px; }
    .exists { background:#0f0; color:#000; }
    .not_found { background:#f00; color:#fff; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#555; color:#fff; }
  </style>
</head>
<body>
  <h1>🕵️ Scooby Doo - Username Checker</h1>

  <nav>
    <button onclick="showTab('usernames')" id="btn-usernames" class="active">📋 Usernames</button>
    <button onclick="showTab('settings')" id="btn-settings">⚙️ Settings</button>
  </nav>

  <!-- Usernames Tab -->
  <section id="usernames" class="active">
    <form method="post">
      <input type="text" name="newUsername" placeholder="Enter username">
      <button type="submit" name="action" value="addUsername" class="addBtn">➕ Add</button>
    </form>

    <?php if (!empty($savedUsernames)): ?>
    <table>
      <tr>
        <th>Username</th>
        <th>Added At</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Following</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($savedUsernames as $i => $row): ?>
      <tr>
        <td>@<?php echo htmlspecialchars($row["username"]); ?></td>
        <td><?php echo htmlspecialchars($row["time"]); ?></td>
        <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
        <td id="followers<?php echo $i; ?>">-</td>
        <td id="following<?php echo $i; ?>">-</td>
        <td>
          <button type="button" class="refreshBtn" onclick="refreshUser('<?php echo $row["username"]; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
          <form method="post" style="display:inline;">
            <input type="hidden" name="username" value="<?php echo htmlspecialchars($row["username"]); ?>">
            <button type="submit" name="action" value="deleteUser" class="deleteBtn" onclick="return confirm('Delete this username?')">🗑 Delete</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>
  </section>

  <!-- Settings Tab -->
  <section id="settings">
    <form method="post">
      <h2>⚙️ Manage Cookies</h2>
      <textarea name="cookies" rows="5" placeholder="Paste Instagram cookies here..."><?php echo htmlspecialchars($cookies); ?></textarea>
      <button type="submit" name="action" value="saveCookies" class="addBtn">💾 Save Cookies</button>
    </form>
  </section>

<script>
function showTab(tab) {
  document.querySelectorAll("section").forEach(sec => sec.classList.remove("active"));
  document.querySelectorAll("nav button").forEach(btn => btn.classList.remove("active"));
  document.getElementById(tab).classList.add("active");
  document.getElementById("btn-" + tab).classList.add("active");
}

function refreshUser(username, index) {
  fetch("refresh.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      document.getElementById("status" + index).textContent = data.status.replace("_", " ");
      document.getElementById("status" + index).className = "badge " + data.status;
      document.getElementById("followers" + index).textContent = data.followers;
      document.getElementById("following" + index).textContent = data.following;
    })
    .catch(err => console.error("Error:", err));
}
</script>
</body>
</html>
