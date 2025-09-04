<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Load usernames
$stmt = $db->prepare("SELECT * FROM usernames WHERE user_id = ?");
$stmt->execute([$user_id]);
$usernames = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo - Username Checker</title>
  <style>
    body { background:black; color:lime; font-family:monospace; padding:20px; }
    table { width:100%; border-collapse:collapse; background:#111; margin-top:20px; }
    th, td { padding:8px; border:1px solid #333; }
    button { padding:4px 8px; margin:2px; cursor:pointer; }
    .btn-add { background:#0f0; color:black; }
    .btn-refresh { background:#ff0; color:black; }
    .btn-auto { background:#0ff; color:black; }
    .btn-del { background:red; color:white; }
    .badge { padding:2px 6px; border-radius:5px; }
    .exists { background:green; }
    .not_found { background:red; }
    .error { background:orange; color:black; }
    .invalid_session { background:gray; }
    textarea { width:100%; height:60px; background:#111; color:#0f0; border:1px solid #333; }
    .countdown { font-size:12px; color:#999; }
  </style>
</head>
<body>
  <h1>🐶 Scooby Doo - Username Checker</h1>
  <a href="index.php">🏠 Home</a> | 
  <a href="settings.php">⚙️ Settings</a> | 
  <a href="logout.php" style="color:red;">Logout</a>
  <hr>

  <div>
    <textarea id="newUsernames" placeholder="Enter one username per line"></textarea><br>
    <button class="btn-add" onclick="addUsernames()">Add</button>
  </div>

  <table>
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($usernames as $i => $row): ?>
    <tr id="row<?php echo $row['id']; ?>">
      <td><a href="https://instagram.com/<?php echo htmlspecialchars($row['name']); ?>" target="_blank" style="color:#0ff;"><?php echo htmlspecialchars($row['name']); ?></a></td>
      <td><span class="badge" id="status<?php echo $row['id']; ?>">-</span></td>
      <td id="followers<?php echo $row['id']; ?>">-</td>
      <td id="following<?php echo $row['id']; ?>">-</td>
      <td>
        <button class="btn-refresh" onclick="refreshUser('<?php echo $row['name']; ?>', <?php echo $row['id']; ?>)">🔄 Refresh</button>
        <button class="btn-auto" onclick="toggleAuto('<?php echo $row['name']; ?>', <?php echo $row['id']; ?>)">⏱ Auto</button>
        <button class="btn-del" onclick="deleteUser(<?php echo $row['id']; ?>)">🗑 Delete</button>
        <div class="countdown" id="countdown<?php echo $row['id']; ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

<script>
let interval = 120;
let timers = {};

function addUsernames() {
  let text = document.getElementById("newUsernames").value.trim();
  if (!text) return alert("Enter usernames");
  fetch("username_action.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "action=add_bulk&usernames=" + encodeURIComponent(text)
  }).then(r=>r.json()).then(data=>{
    if (data.success) location.reload();
    else alert(data.error);
  });
}

function refreshUser(username, id) {
  fetch("refresh.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      let statusEl = document.getElementById("status" + id);
      let followersEl = document.getElementById("followers" + id);
      let followingEl = document.getElementById("following" + id);
      statusEl.textContent = data.status;
      statusEl.className = "badge " + data.status;
      followersEl.textContent = data.followers;
      followingEl.textContent = data.following;
    })
    .catch(err => console.error("Error:", err));
}

function toggleAuto(username, id) {
  if (timers[id]) {
    clearInterval(timers[id].intervalId);
    document.getElementById("countdown" + id).innerText = "";
    delete timers[id];
  } else {
    let seconds = interval;
    document.getElementById("countdown" + id).innerText = "Next in " + seconds + "s";
    timers[id] = {
      intervalId: setInterval(() => {
        seconds--;
        if (seconds <= 0) {
          refreshUser(username, id);
          seconds = interval;
        }
        document.getElementById("countdown" + id).innerText = "Next in " + seconds + "s";
      }, 1000)
    };
  }
}

function deleteUser(id) {
  if (!confirm("Are you sure to delete this username?")) return;
  fetch("username_action.php", {
    method:"POST",
    headers: {"Content-Type":"application/x-www-form-urlencoded"},
    body:"action=delete&id=" + id
  }).then(r=>r.json()).then(data=>{
    if (data.success) document.getElementById("row"+id).remove();
    else alert(data.error);
  });
}
</script>
</body>
</html>
