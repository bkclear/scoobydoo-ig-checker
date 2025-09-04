<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$filename = "usernames.txt";
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($_POST['action'] === "addUsername" && !empty($_POST["newUsername"])) {
        $newUsername = trim($_POST["newUsername"]);
        if (!in_array($newUsername, $savedUsernames)) {
            $savedUsernames[] = $newUsername;
            file_put_contents($filename, implode("\n", $savedUsernames));
        }
    }

    if ($_POST['action'] === "bulkAdd" && !empty($_POST["bulkUsernames"])) {
        $bulkList = explode("\n", trim($_POST["bulkUsernames"]));
        foreach ($bulkList as $name) {
            $name = trim($name);
            if ($name !== "" && !in_array($name, $savedUsernames)) {
                $savedUsernames[] = $name;
            }
        }
        file_put_contents($filename, implode("\n", $savedUsernames));
    }

    if ($_POST['action'] === "deleteUsername" && !empty($_POST["deleteUser"])) {
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

  <!-- Add username -->
  <form method="post">
    <label>Add Username:</label>
    <input type="text" name="newUsername" placeholder="Enter username">
    <button type="submit" name="action" value="addUsername">➕ Add</button>
  </form>

  <!-- Bulk add -->
  <form method="post">
    <label>Bulk Add:</label>
    <textarea name="bulkUsernames" rows="4" placeholder="user1&#10;user2"></textarea>
    <button type="submit" name="action" value="bulkAdd">📥 Bulk Add</button>
  </form>

  <!-- Username Table -->
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
      <td onclick="copyUsername('<?php echo $username; ?>')" class="clickable">
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

<script>
let interval = 120;

function copyUsername(username) {
  navigator.clipboard.writeText(username).then(()=>alert("Copied: @"+username));
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
