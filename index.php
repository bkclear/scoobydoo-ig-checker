<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$filename     = "usernames.txt";
$cookiesFile  = "cookies.txt";
$lastUpdated  = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
$cookies        = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? "";

    if ($action === "saveCookies" && !empty($_POST["cookies"])) {
        $cookies = trim($_POST["cookies"]);
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
  <title>🕵️ Username Checker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <h1>🕵️ Username Checker</h1>

  <!-- Tabs -->
  <div class="tabs">
    <button class="tablink active" onclick="openTab(event,'checker')">Checker</button>
    <button class="tablink" onclick="openTab(event,'settings')">Settings</button>
  </div>

  <!-- Username Checker -->
  <div id="checker" class="tabcontent" style="display:block;">
    <form method="post">
      <label>Add Username:</label>
      <input type="text" name="newUsername" placeholder="Enter username">
      <button type="submit" name="action" value="addUsername">➕ Add</button>
    </form>

    <form method="post">
      <label>Bulk Add:</label>
      <textarea name="bulkUsernames" rows="4" placeholder="user1&#10;user2"></textarea>
      <button type="submit" name="action" value="bulkAdd">📥 Bulk Add</button>
    </form>

    <?php if (!empty($savedUsernames)): ?>
    <div class="table-wrapper">
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
          <td class="clickable">
            <a href="https://instagram.com/<?php echo htmlspecialchars($username); ?>" target="_blank">
              @<?php echo htmlspecialchars($username); ?>
            </a>
          </td>
          <td><span class="badge" id="status<?php echo $i; ?>">-</span></td>
          <td id="followers<?php echo $i; ?>">-</td>
          <td id="following<?php echo $i; ?>">-</td>
          <td>
            <button type="button" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄</button>
            <form method="post" style="display:inline;" onsubmit="return confirm('Delete @<?php echo $username; ?>?');">
              <input type="hidden" name="deleteUser" value="<?php echo htmlspecialchars($username); ?>">
              <button type="submit" name="action" value="deleteUsername" class="delete">🗑</button>
            </form>
            <div class="countdown" id="countdown<?php echo $i; ?>"></div>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Settings Tab -->
  <div id="settings" class="tabcontent">
    <form method="post">
      <label>Paste Instagram Cookies:</label>
      <textarea name="cookies" rows="4"><?php echo htmlspecialchars($cookies); ?></textarea>
      <br><small>Last updated: <?php echo $lastUpdated; ?></small><br>
      <button type="submit" name="action" value="saveCookies">💾 Save Cookies</button>
    </form>
  </div>

<script>
function openTab(evt, tabName) {
  document.querySelectorAll(".tabcontent").forEach(t => t.style.display = "none");
  document.querySelectorAll(".tablink").forEach(b => b.classList.remove("active"));
  document.getElementById(tabName).style.display = "block";
  evt.currentTarget.classList.add("active");
}

function refreshUser(username,index){
  fetch("refresh.php?username="+encodeURIComponent(username))
    .then(res=>res.json())
    .then(data=>{
      document.getElementById("status"+index).textContent=data.status;
      document.getElementById("status"+index).className="badge "+data.status;
      document.getElementById("followers"+index).textContent=data.followers;
      document.getElementById("following"+index).textContent=data.following;
    })
    .catch(err=>console.error(err));
}
</script>
</body>
</html>
