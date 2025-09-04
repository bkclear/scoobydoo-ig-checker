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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo - Username Checker</title>
  <meta name="viewport" content="width=device-width, initial-scale=1"> <!-- Mobile support -->
  <style>
    body { background:#000; color:#0f0; font-family:monospace; margin:0; }
    header { background:#111; padding:10px; text-align:center; }
    header h1 { margin:5px; font-size:22px; }
    nav { margin-top:5px; }
    nav a { color:#0ff; margin:0 10px; text-decoration:none; }
    nav a:hover { text-decoration:underline; }

    .container { padding:15px; }

    textarea, input { width:100%; background:#111; color:#0f0; border:1px solid #333; padding:6px; border-radius:4px; }
    button { padding:6px 10px; margin:3px 2px; cursor:pointer; border:none; border-radius:4px; font-size:14px; }
    .btn-add { background:#0f0; color:#000; }
    .btn-refresh { background:#ff0; color:#000; }
    .btn-auto { background:#0ff; color:#000; }
    .btn-del { background:#f00; color:#fff; }

    table { width:100%; border-collapse:collapse; background:#111; margin-top:20px; font-size:14px; }
    th, td { padding:8px; border:1px solid #333; text-align:left; }
    a.username-link { color:#0ff; text-decoration:none; }
    a.username-link:hover { text-decoration:underline; }

    .badge { padding:2px 6px; border-radius:5px; }
    .exists { background:green; color:#fff; }
    .not_found { background:red; color:#fff; }
    .error { background:orange; color:#000; }
    .invalid_session { background:gray; color:#fff; }

    .countdown { font-size:12px; color:#999; }

    /* 📱 Mobile responsiveness */
    @media (max-width:600px) {
      table, thead, tbody, th, td, tr { display:block; width:100%; }
      tr { margin-bottom:10px; border-bottom:1px solid #333; }
      td { padding:6px; text-align:right; position:relative; }
      td::before { position:absolute; left:6px; text-align:left; font-weight:bold; }
      td:nth-of-type(1)::before { content:"Username"; }
      td:nth-of-type(2)::before { content:"Status"; }
      td:nth-of-type(3)::before { content:"Followers"; }
      td:nth-of-type(4)::before { content:"Following"; }
      td:nth-of-type(5)::before { content:"Actions"; }
    }
  </style>
</head>
<body>
  <header>
    <h1>🐶 Scooby Doo</h1>
    <nav>
      <a href="index.php">🏠 Home</a>
      <a href="settings.php">⚙️ Settings</a>
      <a href="logout.php" style="color:red;">🚪 Logout</a>
    </nav>
  </header>

  <div class="container">
    <h2>🔍 Username Checker</h2>
    <textarea id="newUsernames" placeholder="Enter usernames (one per line)"></textarea><br>
    <button class="btn-add" onclick="addUsernames()">➕ Add Usernames</button>

    <div style="overflow-x:auto;">
      <table>
        <thead>
          <tr>
            <th>Username</th>
            <th>Status</th>
            <th>Followers</th>
            <th>Following</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($usernames as $row): ?>
          <tr id="row<?php echo $row['id']; ?>">
            <td>
              <a href="https://instagram.com/<?php echo htmlspecialchars($row['name']); ?>" 
                 target="_blank" 
                 class="username-link">
                <?php echo htmlspecialchars($row['name']); ?>
              </a>
            </td>
            <td><span class="badge" id="status<?php echo $row['id']; ?>">-</span></td>
            <td id="followers<?php echo $row['id']; ?>">-</td>
            <td id="following<?php echo $row['id']; ?>">-</td>
            <td>
              <button class="btn-refresh" onclick="refreshUser('<?php echo $row['name']; ?>', <?php echo $row['id']; ?>)">🔄</button>
              <button class="btn-auto" onclick="toggleAuto('<?php echo $row['name']; ?>', <?php echo $row['id']; ?>)">⏱</button>
              <button class="btn-del" onclick="deleteUser(<?php echo $row['id']; ?>)">🗑</button>
              <div class="countdown" id="countdown<?php echo $row['id']; ?>"></div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

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
