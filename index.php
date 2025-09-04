<?php
session_start();
require "db.php";

// Redirect if not logged in
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION["user_id"];

// Load usernames
$stmt = $db->prepare("SELECT id, name FROM usernames WHERE user_id = ?");
$stmt->execute([$user_id]);
$usernames = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo - Username Checker</title>
  <style>
    body { background:black; color:lime; font-family:monospace; padding:20px; }
    h1 { text-align:center; color:#0f0; }
    .username-list { width:100%; border-collapse:collapse; margin-top:20px; background:#111; }
    .username-list th, .username-list td { border:1px solid #333; padding:8px; }
    .btn { padding:6px 12px; border:none; border-radius:5px; cursor:pointer; }
    .btn-add { background:#0f0; color:black; }
    .btn-del { background:#f00; color:white; }
    .btn-refresh { background:#ff0; color:black; }
    .btn-auto { background:#0ff; color:black; }
    .countdown { font-size:12px; color:#aaa; }
  </style>
</head>
<body>
  <h1>🕵️ Scooby Doo Username Checker</h1>
  <a href="logout.php" style="color:red; float:right;">Logout</a>

  <div>
    <input type="text" id="newUsername" placeholder="Enter username">
    <button class="btn btn-add" onclick="addUsername()">Add</button>
  </div>

  <table class="username-list">
    <tr>
      <th>Username</th>
      <th>Status</th>
      <th>Followers</th>
      <th>Following</th>
      <th>Actions</th>
    </tr>
    <?php foreach ($usernames as $i => $row): ?>
    <tr id="row<?php echo $row['id']; ?>">
      <td><span onclick="copyUsername('<?php echo $row['name']; ?>')">@<?php echo htmlspecialchars($row['name']); ?></span></td>
      <td><span id="status<?php echo $row['id']; ?>">-</span></td>
      <td id="followers<?php echo $row['id']; ?>">-</td>
      <td id="following<?php echo $row['id']; ?>">-</td>
      <td>
        <button class="btn btn-refresh" onclick="refreshUser('<?php echo $row['name']; ?>',<?php echo $row['id']; ?>)">🔄 Refresh</button>
        <button class="btn btn-auto" onclick="toggleAuto('<?php echo $row['name']; ?>',<?php echo $row['id']; ?>)">⏱ Auto</button>
        <button class="btn btn-del" onclick="deleteUsername(<?php echo $row['id']; ?>)">❌ Delete</button>
        <div class="countdown" id="countdown<?php echo $row['id']; ?>"></div>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

<script>
let interval = 120; // default refresh time (seconds)
let timers = {};

function copyUsername(username) {
  navigator.clipboard.writeText(username);
  alert("Copied: " + username);
}

function addUsername() {
  let u = document.getElementById("newUsername").value.trim();
  if (!u) return alert("Enter a username");
  fetch("username_action.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "action=add&username=" + encodeURIComponent(u)
  }).then(r=>r.json()).then(data=>{
    if (data.success) location.reload();
    else alert(data.error);
  });
}

function deleteUsername(id) {
  if (!confirm("Are you sure to delete this username?")) return;
  fetch("username_action.php", {
    method: "POST",
    headers: {"Content-Type": "application/x-www-form-urlencoded"},
    body: "action=delete&id=" + id
  }).then(r=>r.json()).then(data=>{
    if (data.success) document.getElementById("row"+id).remove();
    else alert(data.error);
  });
}

function refreshUser(username, id) {
  fetch("refresh.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      document.getElementById("status" + id).textContent = data.status;
      document.getElementById("followers" + id).textContent = data.followers;
      document.getElementById("following" + id).textContent = data.following;
    });
}

function toggleAuto(username, id) {
  if (timers[id]) {
    clearInterval(timers[id].intervalId);
    document.getElementById("countdown"+id).innerText = "";
    delete timers[id];
  } else {
    let seconds = interval;
    document.getElementById("countdown"+id).innerText = "Next refresh in " + seconds + "s";
    timers[id] = {
      intervalId: setInterval(() => {
        seconds--;
        if (seconds <= 0) {
          refreshUser(username, id);
          seconds = interval;
        }
        document.getElementById("countdown"+id).innerText = "Next refresh in " + seconds + "s";
      }, 1000)
    };
  }
}
</script>
</body>
</html>
