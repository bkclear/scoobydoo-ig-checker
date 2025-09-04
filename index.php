<?php
error_reporting(0);

$dataFile    = "data.json";
$cookiesFile = "cookies.txt";

// Load usernames + userData
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
$cookies  = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Handle AJAX actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"] ?? "";
    if ($action === "saveCookies") {
        file_put_contents($cookiesFile, $_POST["cookies"]);
        echo json_encode(["message" => "✅ Cookies saved!"]);
        exit;
    }
    if ($action === "addUsernames") {
        $usernames = preg_split('/[\s,]+/', $_POST["usernames"]);
        $added = [];
        foreach ($usernames as $u) {
            $u = trim($u);
            if ($u && !isset($userData[$u])) {
                $userData[$u] = [
                    "status" => "pending",
                    "followers" => 0,
                    "following" => 0,
                    "last_checked" => "-"
                ];
                $added[] = $u;
            }
        }
        file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT));
        echo json_encode(["usernames" => $added, "userData" => $userData]);
        exit;
    }
    if ($action === "deleteUser") {
        $u = $_POST["username"];
        if (isset($userData[$u])) unset($userData[$u]);
        file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT));
        echo json_encode(["success" => true]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Instagram Monitor</title>
  <style>
    body { font-family: Arial, sans-serif; margin:20px; background:#f9f9f9; }
    table { width:100%; border-collapse:collapse; margin-top:10px; background:#fff; }
    th, td { border:1px solid #ddd; padding:8px; text-align:center; }
    th { background:#eee; }
    .badge { padding:3px 6px; border-radius:5px; }
    .exists { background:#4caf50; color:#fff; }
    .not_found { background:#f44336; color:#fff; }
    .invalid_session { background:#ff9800; color:#fff; }
    .error { background:#9e9e9e; color:#fff; }
    textarea { width:100%; height:60px; }
    input, button, select { margin:5px; padding:5px; }
    .countdown { font-size:12px; color:#555; }
  </style>
</head>
<body>
  <h2>📊 Instagram Username Monitor</h2>

  <div id="banner" style="display:none; background:#ffcccc; padding:10px; margin-bottom:10px;">
    ⚠️ Invalid session! Please update cookies.
  </div>

  <div>
    <h3>Cookies</h3>
    <textarea id="cookiesBox"><?=htmlspecialchars($cookies)?></textarea><br>
    <button onclick="saveCookies()">💾 Save Cookies</button>
  </div>

  <div>
    <h3>Manage Usernames</h3>
    <input id="usernamesInput" placeholder="Enter usernames (comma/space separated)">
    <button onclick="addUsernames()">➕ Add</button>
  </div>

  <div>
    <input id="search" placeholder="Search..." onkeyup="filterTable()">
    <select onchange="setGlobalInterval(this.value)">
      <option value="60">60s</option>
      <option value="120" selected>120s</option>
      <option value="300">5min</option>
    </select>
    <button onclick="refreshAll()">🔄 Refresh All</button>
    <button onclick="toggleAllAuto()">⏱ Auto All</button>
  </div>

  <table>
    <thead>
      <tr>
        <th>Username</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Following</th>
        <th>Last Checked</th>
        <th>Actions</th>
      </tr>
    </thead>
    <tbody id="userTable">
      <?php foreach ($userData as $u => $info): ?>
        <tr id="row-<?=$u?>" data-username="<?=$u?>">
          <td class="copyable" onclick="copyUsername('<?=$u?>')">
            <a href="https://instagram.com/<?=$u?>" target="_blank">@<?=$u?></a>
          </td>
          <td><span class="badge status <?=$info['status']?>"><?=$info['status']?></span></td>
          <td class="followers"><?=$info['followers']?></td>
          <td class="following"><?=$info['following']?></td>
          <td class="last"><?=$info['last_checked']?></td>
          <td>
            <button onclick="refreshUser('<?=$u?>')">🔄</button>
            <button onclick="toggleAuto('<?=$u?>')">⏱</button>
            <button onclick="deleteUser('<?=$u?>')">🗑</button>
            <div class="countdown" id="countdown-<?=$u?>"></div>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ================= JS ================= -->
  <script>
  let interval = 120;
  let timers = {};
  let autoAll = false;

  function setGlobalInterval(val){ interval = parseInt(val); }

  function saveCookies(){
    fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:"action=saveCookies&cookies="+encodeURIComponent(document.getElementById("cookiesBox").value)})
    .then(r=>r.json()).then(d=>alert(d.message));
  }

  function addUsernames(){
    const val=document.getElementById("usernamesInput").value;
    if(!val.trim())return;
    fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:"action=addUsernames&usernames="+encodeURIComponent(val)})
    .then(r=>r.json())
    .then(d=>{
      d.usernames.forEach(u=>{
        if(!document.getElementById("row-"+u)){ addRow(u,d.userData[u]); }
      });
      document.getElementById("usernamesInput").value="";
    });
  }

  function deleteUser(username){
    if(!confirm("Delete @"+username+"?"))return;
    fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},
    body:"action=deleteUser&username="+encodeURIComponent(username)})
    .then(r=>r.json())
    .then(()=>{
      let row=document.getElementById("row-"+username);
      if(row)row.remove();
      if(timers[username]){clearInterval(timers[username].id);delete timers[username];}
    });
  }

  function refreshUser(username){
    return fetch("refresh.php?username="+encodeURIComponent(username))
      .then(res=>res.json())
      .then(data=>{
        let row=document.getElementById("row-"+username);
        if(!row)return;
        if(data.status==="invalid_session"){
          document.getElementById("banner").style.display="block";
        }
        row.querySelector(".status").textContent=data.status.replace("_"," ");
        row.querySelector(".status").className="badge status "+data.status;
        row.querySelector(".followers").textContent=data.followers;
        row.querySelector(".following").textContent=data.following;
        row.querySelector(".last").textContent=data.last_checked||"-";
      });
  }

  function refreshAll(){
    const usernames=Array.from(document.querySelectorAll("#userTable tr[data-username]"))
      .map(r=>r.getAttribute("data-username"));
    Promise.all(usernames.map(u=>refreshUser(u)));
  }

  function toggleAuto(username){
    const countdownEl=document.getElementById("countdown-"+username);
    if(timers[username]){
      clearInterval(timers[username].id);
      delete timers[username];
      countdownEl.innerText="";
      return;
    }
    let seconds=interval;
    countdownEl.innerText="Next "+seconds+"s";
    timers[username]={id:setInterval(()=>{
      seconds--;
      if(seconds<=0){
        refreshUser(username);
        seconds=interval;
      }
      countdownEl.innerText="Next "+seconds+"s";
    },1000)};
  }

  function toggleAllAuto(){
    autoAll=!autoAll;
    const usernames=Array.from(document.querySelectorAll("#userTable tr[data-username]"))
      .map(r=>r.getAttribute("data-username"));
    if(autoAll){
      usernames.forEach(u=>{if(!timers[u])toggleAuto(u);});
    }else{
      usernames.forEach(u=>{
        if(timers[u]){clearInterval(timers[u].id);delete timers[u];}
        document.getElementById("countdown-"+u).innerText="";
      });
    }
  }

  function copyUsername(u){
    navigator.clipboard.writeText(u);
    alert("Copied: @"+u);
  }

  function filterTable(){
    let v=document.getElementById("search").value.toLowerCase();
    document.querySelectorAll("#userTable tr[data-username]").forEach(r=>{
      r.style.display=r.innerText.toLowerCase().includes(v)?"":"none";
    });
  }

  function addRow(username,info){
    const table=document.getElementById("userTable");
    let row=document.createElement("tr");
    row.id="row-"+username;
    row.setAttribute("data-username",username);
    row.innerHTML=`
      <td class="copyable" onclick="copyUsername('${username}')">
        <a href="https://instagram.com/${username}" target="_blank">@${username}</a>
      </td>
      <td><span class="badge status ${info.status}">${info.status}</span></td>
      <td class="followers">${info.followers}</td>
      <td class="following">${info.following}</td>
      <td class="last">${info.last_checked||'-'}</td>
      <td>
        <button onclick="refreshUser('${username}')">🔄</button>
        <button onclick="toggleAuto('${username}')">⏱</button>
        <button onclick="deleteUser('${username}')">🗑</button>
        <div class="countdown" id="countdown-${username}"></div>
      </td>`;
    table.appendChild(row);
  }
  </script>
</body>
</html>
