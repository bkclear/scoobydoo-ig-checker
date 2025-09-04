<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>👥 Scooby Doo - Followers Viewer</title>
  <style>
    body { background:black; color:#0f0; font-family:monospace; padding:20px; }
    h2 { text-align:center; color:#0f0; }
    form { text-align:center; margin-bottom:20px; }
    input, button { padding:8px; border:none; border-radius:5px; }
    input { width:200px; }
    button { background:#0f0; color:black; font-weight:bold; cursor:pointer; }
    button:hover { background:#090; color:#fff; }
    .grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap:15px; }
    .card { background:#111; padding:10px; border:1px solid #0f0; border-radius:10px; text-align:center; }
    .card img { border-radius:50%; width:80px; height:80px; margin-bottom:10px; }
    .username { cursor:pointer; font-weight:bold; color:#0f0; }
    .username:hover { text-decoration:underline; }
    .small { font-size:12px; color:#999; }
    .actions { text-align:center; margin:10px; }
  </style>
</head>
<body>
  <h2>👥 Scooby Doo - Followers Viewer</h2>
  <form id="followerForm">
    <input type="text" id="targetUser" placeholder="Enter target username" required>
    <button type="submit">🔍 View Followers</button>
    <button type="button" id="refreshBtn" style="display:none;">🔄 Refresh</button>
  </form>

  <div id="result"></div>

<script>
let lastUser = null;

document.getElementById("followerForm").addEventListener("submit", function(e){
  e.preventDefault();
  let username = document.getElementById("targetUser").value.trim();
  if (!username) return;
  lastUser = username;
  fetchFollowers(username);
  document.getElementById("refreshBtn").style.display = "inline-block";
});

document.getElementById("refreshBtn").addEventListener("click", function(){
  if (lastUser) fetchFollowers(lastUser);
});

function fetchFollowers(username) {
  document.getElementById("result").innerHTML = "<p>⏳ Fetching followers for @" + username + "...</p>";

  fetch("view_followers.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        document.getElementById("result").innerHTML = "<p style='color:red;'>⚠ " + data.error + "</p>";
        return;
      }
      if (!data.followers || data.followers.length === 0) {
        document.getElementById("result").innerHTML = "<p>No followers found.</p>";
        return;
      }

      // Filter 2k below
      let filtered = data.followers.filter(f => f.follower_count <= 2000);

      let html = `<h3>Found ${filtered.length} followers (≤ 2000 followers)</h3>`;
      html += `<div class="grid">`;

      filtered.forEach(f => {
        html += `
          <div class="card">
            <img src="${f.profile_pic}" alt="pic">
            <div class="username" onclick="copyUser('${f.username}')">@${f.username}</div>
            <div>${f.full_name || ''}</div>
            <div class="small">Followers: ${f.follower_count}</div>
          </div>
        `;
      });

      html += `</div>`;
      document.getElementById("result").innerHTML = html;
    })
    .catch(err => {
      document.getElementById("result").innerHTML = "<p style='color:red;'>Error fetching followers</p>";
      console.error(err);
    });
}

function copyUser(username) {
  navigator.clipboard.writeText(username).then(() => {
    alert("Copied @" + username);
  });
}
</script>
</body>
</html>
