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

// Save cookies / usernames
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
    }

    if ($action === "addUsername" && !empty($_POST["newUsername"])) {
        $newUsername = trim($_POST["newUsername"]);
        if ($newUsername && !in_array($newUsername, $savedUsernames)) {
            $savedUsernames[] = $newUsername;
            file_put_contents($filename, implode("\n", $savedUsernames));
        }
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
  <title>Scooby Doo - Hacker IG Tool</title>
  <style>
    body { background:#000; color:#0f0; font-family:Courier, monospace; padding:15px; }
    h1 { text-align:center; color:#0f0; }
    form { background:#111; padding:15px; border:1px solid #0f0; border-radius:8px; max-width:600px; margin:auto; }
    textarea, input { width:100%; padding:8px; background:#000; color:#0f0; border:1px solid #0f0; border-radius:5px; }
    button { margin-top:8px; padding:10px 15px; border:none; border-radius:5px; cursor:pointer; background:#0f0; color:#000; font-weight:bold; }
    table { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    th, td { padding:10px; border:1px solid #0f0; text-align:left; }
    img { border-radius:50%; }
    .copyable { cursor:pointer; color:#0f0; }
    .copyable:hover { text-decoration:underline; }
    #followersContainer { margin-top:20px; }
    #paginationControls button { background:#0f0; color:#000; margin:5px; }
  </style>
</head>
<body>
  <h1>🕵️ Scooby Doo Hacker Tool</h1>
  <form method="post">
    <label>Paste Instagram cookies:</label>
    <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
    <br><small>Last updated: <?php echo $lastUpdated; ?></small><br>
    <button type="submit" name="action" value="saveCookies">💾 Save Cookies</button>
  </form>

  <br>
  <form method="post">
    <label>Add Username:</label>
    <input type="text" name="newUsername" placeholder="example_user">
    <button type="submit" name="action" value="addUsername">➕ Add</button>
  </form>

  <?php if (!empty($savedUsernames)): ?>
  <table>
    <tr>
      <th>Username</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($savedUsernames as $u): ?>
    <tr>
      <td>@<?php echo htmlspecialchars($u); ?></td>
      <td>
        <form method="post" style="display:inline;">
          <input type="hidden" name="deleteUser" value="<?php echo htmlspecialchars($u); ?>">
          <button type="submit" name="action" value="deleteUsername">🗑 Delete</button>
        </form>
        <button type="button" onclick="viewFollowers('<?php echo $u; ?>')">👥 View Followers</button>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <div id="followersContainer"></div>
  <div id="paginationControls" style="text-align:center;"></div>

<script>
let allFollowers = [];
let currentPage = 1;
const perPage = 50;

function viewFollowers(username) {
  fetch("view_followers.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      allFollowers = data;
      renderPage(1);
    })
    .catch(err => {
      document.getElementById("followersContainer").innerHTML = "<p style='color:red;'>Error fetching followers</p>";
      console.error(err);
    });
}

function renderPage(page) {
  currentPage = page;
  const start = (page - 1) * perPage;
  const end = start + perPage;
  const sliced = allFollowers.slice(start, end);

  let html = `<h3>Followers (Page ${page})</h3><table><tr><th>Pic</th><th>Username</th></tr>`;
  sliced.forEach(f => {
    html += `
      <tr>
        <td><img src="${f.pic}" width="40" height="40"></td>
        <td><span class="copyable" onclick="copyUsername('${f.username}')">@${f.username}</span></td>
      </tr>`;
  });
  html += `</table>`;
  document.getElementById("followersContainer").innerHTML = html;

  // Pagination controls
  let totalPages = Math.ceil(allFollowers.length / perPage);
  let controls = "";
  if (page > 1) controls += `<button onclick="renderPage(${page-1})">⬅ Prev</button>`;
  if (page < totalPages) controls += `<button onclick="renderPage(${page+1})">Next ➡</button>`;
  document.getElementById("paginationControls").innerHTML = controls;
}

function copyUsername(username) {
  navigator.clipboard.writeText(username);
  alert("Copied: @" + username);
}
</script>
</body>
</html>
