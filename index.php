<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile   = "cookies.txt";
$usernamesFile = "usernames.txt";
$dataFile      = "data.json";
$lastUpdated   = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Load usernames
$savedUsernames = file_exists($usernamesFile) ? file($usernamesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Load user data
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($userData)) $userData = [];

// Ensure consistency
foreach ($savedUsernames as $u) {
    if (!isset($userData[$u])) {
        $userData[$u] = [
            "status" => "-",
            "followers" => "-",
            "following" => "-",
            "last_checked" => null,
            "history" => []
        ];
    }
}
file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

// Handle AJAX actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
        echo json_encode(["ok" => true, "message" => "Cookies saved"]);
        exit;
    }

    if ($action === "addUsernames") {
        $raw = trim($_POST['usernames'] ?? '');
        $newList = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));

        // Load existing
        $savedUsernames = file_exists($usernamesFile) ? file($usernamesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        // Merge + dedupe
        $merged = array_values(array_unique(array_merge($savedUsernames, $newList)));

        file_put_contents($usernamesFile, implode("\n", $merged));
        $savedUsernames = $merged;

        // Update data file
        foreach ($savedUsernames as $u) {
            if (!isset($userData[$u])) {
                $userData[$u] = [
                    "status" => "-",
                    "followers" => "-",
                    "following" => "-",
                    "last_checked" => null,
                    "history" => []
                ];
            }
        }
        foreach ($userData as $u => $_v) {
            if (!in_array($u, $savedUsernames, true)) unset($userData[$u]);
        }
        file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        echo json_encode(["ok" => true, "usernames" => $savedUsernames, "userData" => $userData]);
        exit;
    }

    if ($action === "deleteUser" && !empty($_POST['username'])) {
        $usernameToDelete = trim($_POST['username']);
        $savedUsernames = array_filter($savedUsernames, fn($u) => $u !== $usernameToDelete);
        file_put_contents($usernamesFile, implode("\n", $savedUsernames));
        if (isset($userData[$usernameToDelete])) {
            unset($userData[$usernameToDelete]);
            file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
        echo json_encode(["ok" => true, "usernames" => array_values($savedUsernames)]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Scooby Doo 🕵️</title>
<style>
body{background:#000;color:#0f0;font-family:Courier,monospace;margin:0;padding:20px;overflow-x:hidden}
h1{text-align:center;color:#0f0;margin-bottom:20px;text-shadow:0 0 8px #0f0,0 0 15px #0f0;position:relative;z-index:2}
.container{max-width:1000px;margin:auto;position:relative;z-index:2}
.card{background:rgba(0,0,0,0.85);padding:20px;border:1px solid #0f0;border-radius:10px;margin-bottom:20px;box-shadow:0 0 10px #0f0 inset}
.card h2{margin-top:0;color:#0f0;font-size:18px;text-shadow:0 0 5px #0f0}
textarea,input{width:100%;padding:10px;margin-top:8px;background:#000;color:#0f0;border:1px solid #0f0;border-radius:5px;font-family:inherit;box-shadow:0 0 5px #0f0 inset}
button{margin:5px;padding:8px 14px;border:none;border-radius:5px;cursor:pointer;font-weight:bold;transition:0.2s}
button:hover{transform:scale(1.05)}
.saveBtn,.checkBtn{background:#0f0;color:#000}
.refreshBtn{background:#00f;color:#fff}
.autoBtn{background:#060;color:#0f0}
.deleteBtn{background:#f00;color:#fff}
.bulkBtn{background:#ff0;color:#000}
.table-wrapper{overflow-x:auto;max-height:500px;overflow-y:auto}
table{width:100%;border-collapse:collapse;background:#111}
th,td{padding:10px;border:1px solid #0f0;text-align:center}
th{position:sticky;top:0;background:#222;z-index:1}
.badge{padding:4px 10px;border-radius:12px;font-size:13px;font-weight:bold}
.exists{background:#0f0;color:#000}
.not_found{background:#f00;color:#fff}
.error{background:#ff0;color:#000}
.invalid_session{background:#555;color:#fff}
.copyable{cursor:pointer}
.copyable:hover{text-decoration:underline;color:#ff0}
.countdown{font-size:12px;color:#0f0;margin-top:4px}
#search{margin-bottom:10px}
.legend span{margin-right:10px}
#matrix{position:fixed;top:0;left:0;width:100%;height:100%;background:black;z-index:0}
.banner{background:#f00;color:#fff;padding:10px;text-align:center;margin-bottom:10px;border-radius:5px}
</style>
</head>
<body>
<canvas id="matrix"></canvas>
<h1>🕵️ Scooby Doo</h1>
<div class="container">

<div id="banner" class="banner" style="display:none">⚠️ Session expired – please update cookies.</div>

<div class="card">
  <h2>🔑 Instagram Cookies</h2>
  <textarea id="cookiesBox" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
  <small>Last updated: <?php echo $lastUpdated; ?></small><br><br>
  <button onclick="saveCookies()" class="saveBtn">💾 Save Cookies</button>
</div>

<div class="card">
  <h2>📋 Manage Usernames</h2>
  <textarea id="usernamesInput" rows="4" placeholder="Enter one username per line..."></textarea><br>
  <button onclick="addUsernames()" class="checkBtn">▶ Save Usernames</button>
</div>

<?php if (!empty($savedUsernames)): ?>
<div class="card">
  <h2>📊 Username Status</h2>
  <input type="text" id="search" placeholder="🔍 Search username..." onkeyup="filterTable()">
  <div class="legend">
    <span class="badge exists">Exists</span>
    <span class="badge not_found">Not Found</span>
    <span class="badge invalid_session">Invalid Session</span>
    <span class="badge error">Error</span>
  </div>
  <br>
  <button onclick="refreshAll()" class="bulkBtn">🔄 Refresh All</button>
  <button onclick="toggleAllAuto()" class="bulkBtn">⏱ Auto All</button>
  <label>Interval:
    <select id="intervalSelect" onchange="setGlobalInterval(this.value)">
      <option value="30">30s</option>
      <option value="60">60s</option>
      <option value="120" selected>120s</option>
      <option value="300">5m</option>
    </select>
  </label>
  <div class="table-wrapper">
    <table id="userTable">
      <tr>
        <th>Username</th>
        <th>Status</th>
        <th>Followers</th>
        <th>Following</th>
        <th>Last Checked</th>
        <th>Actions</th>
      </tr>
      <?php foreach ($savedUsernames as $i => $username): ?>
      <?php $info = $userData[$username] ?? ["status"=>"-","followers"=>"-","following"=>"-","last_checked"=>null]; ?>
      <tr id="row<?php echo $i; ?>">
        <td class="copyable" onclick="copyUsername('<?php echo $username; ?>')">
          <a href="https://instagram.com/<?php echo htmlspecialchars($username); ?>" target="_blank">@<?php echo htmlspecialchars($username); ?></a>
        </td>
        <td><span class="badge <?php echo $info['status']; ?>" id="status<?php echo $i; ?>"><?php echo $info['status']; ?></span></td>
        <td id="followers<?php echo $i; ?>"><?php echo $info['followers']; ?></td>
        <td id="following<?php echo $i; ?>"><?php echo $info['following']; ?></td>
        <td id="last<?php echo $i; ?>"><?php echo $info['last_checked'] ?? '-'; ?></td>
        <td>
          <button class="refreshBtn" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄</button>
          <button class="autoBtn" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱</button>
          <button class="deleteBtn" onclick="deleteUser('<?php echo $username; ?>')">🗑</button>
          <div class="countdown" id="countdown<?php echo $i; ?>"></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php endif; ?>

</div>
<script>
let interval=120;let timers={};let autoAll=false;
function setGlobalInterval(val){interval=parseInt(val);}
function saveCookies(){
  fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=saveCookies&cookies="+encodeURIComponent(document.getElementById("cookiesBox").value)})
  .then(r=>r.json()).then(d=>alert(d.message));
}
function addUsernames(){
  const val=document.getElementById("usernamesInput").value;
  if(!val.trim())return;
  fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=addUsernames&usernames="+encodeURIComponent(val)})
  .then(r=>r.json()).then(()=>location.reload());
}
function deleteUser(username){
  if(!confirm("Delete @"+username+"?"))return;
  fetch("index.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:"action=deleteUser&username="+encodeURIComponent(username)})
  .then(r=>r.json()).then(()=>location.reload());
}
function refreshUser(username,index){
  fetch("refresh.php?username="+encodeURIComponent(username))
    .then(res=>res.json())
    .then(data=>{
      if(data.status==="invalid_session"){document.getElementById("banner").style.display="block";}
      document.getElementById("status"+index).textContent=data.status.replace("_"," ");
      document.getElementById("status"+index).className="badge "+data.status;
      document.getElementById("followers"+index).textContent=data.followers;
      document.getElementById("following"+index).textContent=data.following;
      document.getElementById("last"+index).textContent=data.last_checked||"-";
    });
}
function refreshAll(){document.querySelectorAll("#userTable tr").forEach((row,i)=>{if(i>0){let u=row.cells[0].innerText.replace("@","");refreshUser(u,i-1);}});}
function toggleAuto(username,index){
  if(timers[index]){clearInterval(timers[index].id);delete timers[index];document.getElementById("countdown"+index).innerText="";return;}
  let seconds=interval;
  document.getElementById("countdown"+index).innerText="Next "+seconds+"s";
  timers[index]={id:setInterval(()=>{seconds--;if(seconds<=0){refreshUser(username,index);seconds=interval;}document.getElementById("countdown"+index).innerText="Next "+seconds+"s";},1000)};
}
function toggleAllAuto(){
  autoAll=!autoAll;
  if(autoAll){refreshAll();document.querySelectorAll("#userTable tr").forEach((row,i)=>{if(i>0){let u=row.cells[0].innerText.replace("@","");toggleAuto(u,i-1);}});}
  else{for(let i in timers)clearInterval(timers[i].id);timers={};document.querySelectorAll(".countdown").forEach(el=>el.innerText="");}
}
function copyUsername(u){navigator.clipboard.writeText(u);alert("Copied: @"+u);}
function filterTable(){
  let v=document.getElementById("search").value.toLowerCase();
  document.querySelectorAll("#userTable tr").forEach((row,i)=>{if(i===0)return;row.style.display=row.innerText.toLowerCase().includes(v)?"":"none";});
}
// Matrix BG
const c=document.getElementById("matrix"),ctx=c.getContext("2d");c.height=window.innerHeight;c.width=window.innerWidth;
const letters="アァイィウヴエェオカガキギクグケゲコゴサザシジスズセゼソゾABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789".split("");const fs=14,cols=c.width/fs;let drops=[];for(let x=0;x<cols;x++)drops[x]=1;
function draw(){ctx.fillStyle="rgba(0,0,0,0.08)";ctx.fillRect(0,0,c.width,c.height);ctx.fillStyle="#0F0";ctx.font=fs+"px monospace";for(let i=0;i<drops.length;i++){const t=letters[Math.floor(Math.random()*letters.length)];ctx.fillText(t,i*fs,drops[i]*fs);if(drops[i]*fs>c.height&&Math.random()>0.975)drops[i]=0;drops[i]++;}}setInterval(draw,33);
</script>
</body>
</html>
