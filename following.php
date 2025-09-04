<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>👻 Scooby Doo - Following Viewer</title>
  <style>
    body { background:black; color:#0f0; font-family:monospace; text-align:center; }
    input, button { padding:10px; border:none; border-radius:5px; margin:5px; }
    input { width:250px; }
    button { cursor:pointer; background:#0f0; color:black; font-weight:bold; }
    button:hover { background:#333; color:#0f0; }
    .user-card { 
      display:inline-block; 
      border:1px solid #0f0; 
      margin:10px; 
      padding:10px; 
      border-radius:10px; 
      width:200px;
    }
    img { border-radius:50%; width:80px; height:80px; }
    a { color:#0f0; text-decoration:none; }
    a:hover { text-decoration:underline; }
  </style>
</head>
<body>
  <h1>👻 Scooby Doo - Following Viewer</h1>
  <p>Enter Instagram username to see their <b>following (≤2k followers)</b>:</p>
  <input type="text" id="targetUser" placeholder="username_here">
  <button onclick="loadFollowing()">View Following</button>

  <div id="results"></div>

<script>
function loadFollowing() {
  const username = document.getElementById("targetUser").value.trim();
  if (!username) {
    alert("Enter a username first!");
    return;
  }

  document.getElementById("results").innerHTML = "<p>⏳ Loading...</p>";

  fetch("view_following.php?username=" + encodeURIComponent(username))
    .then(res => res.text())
    .then(html => {
      document.getElementById("results").innerHTML = html;
    })
    .catch(err => {
      document.getElementById("results").innerHTML = "<p style='color:red'>❌ Failed to fetch data.</p>";
      console.error(err);
    });
}
</script>
</body>
</html>
