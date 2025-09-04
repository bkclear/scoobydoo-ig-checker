<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile   = "cookies.txt";
$filename      = "usernames.txt";
$dataFile      = "data.json";
$lastUpdated   = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Load usernames
$savedUsernames = file_exists($filename) ? file($filename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Load user data (status/followers/following)
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];

// Ensure all usernames exist in data.json
foreach ($savedUsernames as $u) {
    if (!isset($userData[$u])) {
        $userData[$u] = ["status" => "-", "followers" => "-", "following" => "-"];
    }
}
file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT));

// Handle actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'] ?? '';

    if ($action === "saveCookies" && !empty($_POST['cookies'])) {
        $cookies = trim($_POST['cookies']);
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
    }

    if ($action === "startChecking") {
        $postedUsernames = explode("\n", trim($_POST["usernames"] ?? ""));
        $postedUsernames = array_map("trim", $postedUsernames);
        $postedUsernames = array_filter($postedUsernames);

        if (!empty($postedUsernames)) {
            file_put_contents($filename, implode("\n", $postedUsernames));
        }
        $savedUsernames = $postedUsernames;

        // Ensure new usernames have entries
        foreach ($savedUsernames as $u) {
            if (!isset($userData[$u])) {
                $userData[$u] = ["status" => "-", "followers" => "-", "following" => "-"];
            }
        }
        file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT));

        // clear textarea
        $_POST["usernames"] = "";
    }

    if ($action === "deleteUser" && !empty($_POST['username'])) {
        $usernameToDelete = trim($_POST['username']);
        $savedUsernames = array_filter($savedUsernames, fn($u) => $u !== $usernameToDelete);
        file_put_contents($filename, implode("\n", $savedUsernames));

        if (isset($userData[$usernameToDelete])) {
            unset($userData[$usernameToDelete]);
            file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Scooby Doo 🕵️</title>
  <style>
    body { 
      background:#000; 
      color:#0f0; 
      font-family:Courier, monospace; 
      margin:0; 
      padding:20px; 
      overflow-x:hidden;
    }
    h1 { 
      text-align:center; 
      color:#0f0; 
      margin-bottom:20px; 
      text-shadow:0 0 8px #0f0, 0 0 15px #0f0;
      position:relative;
      z-index:2;
    }
    .container { max-width:900px; margin:auto; position:relative; z-index:2; }

    .card { 
      background:rgba(0,0,0,0.8); 
      padding:20px; 
      border:1px solid #0f0; 
      border-radius:10px; 
      margin-bottom:20px; 
      box-shadow:0 0 10px #0f0 inset;
    }
    .card h2 { 
      margin-top:0; 
      color:#0f0; 
      font-size:18px; 
      text-shadow:0 0 5px #0f0;
    }

    textarea, input { 
      width:100%; 
      padding:10px; 
      margin-top:8px; 
      background:#000; 
      color:#0f0; 
      border:1px solid #0f0; 
      border-radius:5px; 
      font-family:inherit; 
      box-shadow:0 0 5px #0f0 inset;
    }
    textarea:focus, input:focus {
      outline:none; 
      box-shadow:0 0 10px #0f0;
    }

    button { 
      margin:5px; 
      padding:8px 14px; 
      border:none; 
      border-radius:5px; 
      cursor:pointer; 
      font-weight:bold; 
      transition:0.2s;
      text-shadow:0 0 3px #000;
    }
    button:hover { transform:scale(1.05); }

    .saveBtn, .checkBtn { background:#0f0; color:#000; box-shadow:0 0 10px #0f0; }
    .refreshBtn { background:#00f; color:#fff; box-shadow:0 0 10px #00f; }
    .autoBtn { background:#060; color:#0f0; box-shadow:0 0 10px #0f0; }
    .deleteBtn { background:#f00; color:#fff; box-shadow:0 0 10px #f00; }

    table { 
      width:100%; 
      border-collapse:collapse; 
      margin-top:10px; 
      background:#111; 
      box-shadow:0 0 10px #0f0 inset;
    }
    th, td { 
      padding:10px; 
      border:1px solid #0f0; 
      text-align:center; 
    }
    th { background:#222; text-shadow:0 0 5px #0f0; }
    tr:nth-child(even) { background:#151515; }

    .badge { 
      padding:4px 10px; 
      border-radius:12px; 
      font-size:13px; 
      font-weight:bold; 
      box-shadow:0 0 8px currentColor;
    }
    .exists { background:#0f0; color:#000; }
    .not_found { background:#f00; color:#fff; }
    .error { background:#ff0; color:#000; }
    .invalid_session { background:#555; color:#fff; }
    .private { background:#ffa500; color:#000; }

    .copyable { cursor:pointer; }
    .copyable:hover { text-decoration:underline; color:#ff0; }

    .countdown { font-size:12px; color:#0f0; margin-top:4px; }

    .table-wrapper { overflow-x:auto; }

    /* Matrix canvas behind everything */
    #matrix {
      position:fixed;
      top:0;
      left:0;
      width:100%;
      height:100%;
      background:black;
      z-index:0;
    }
  </style>
</head>
<body>
  <canvas id="matrix"></canvas>
  <h1>🕵️ Scooby Doo</h1>
  <div class="container">
    
    <div class="card">
      <h2>🔑 Instagram Cookies</h2>
      <form method="post">
        <textarea name="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
        <small>Last updated: <?php echo $lastUpdated; ?></small><br><br>
        <button type="submit" name="action" value="saveCookies" class="saveBtn">💾 Save Cookies</button>
      </form>
    </div>

    <div class="card">
      <h2>📋 Manage Usernames</h2>
      <form method="post" onsubmit="stopAllTimers(); this.querySelector('textarea').value=''">
        <textarea name="usernames" rows="6"></textarea><br>
        <button type="submit" name="action" value="startChecking" class="checkBtn">▶ Save Usernames</button>
      </form>
    </div>

    <?php if (!empty($savedUsernames)): ?>
    <div class="card">
      <h2>📊 Username Status</h2>
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
          <?php $info = $userData[$username] ?? ["status" => "-", "followers" => "-", "following" => "-"]; ?>
          <tr id="row<?php echo $i; ?>">
            <td class="copyable" onclick="copyUsername('<?php echo $username; ?>')">
              <a href="https://instagram.com/<?php echo htmlspecialchars($username); ?>" target="_blank">
                @<?php echo htmlspecialchars($username); ?>
              </a>
            </td>
            <td><span class="badge <?php echo $info['status']; ?>" id="status<?php echo $i; ?>"><?php echo $info['status']; ?></span></td>
            <td id="followers<?php echo $i; ?>"><?php echo $info['followers']; ?></td>
            <td id="following<?php echo $i; ?>"><?php echo $info['following']; ?></td>
            <td>
              <button type="button" class="refreshBtn" onclick="refreshUser('<?php echo $username; ?>',<?php echo $i; ?>)">🔄 Refresh</button>
              <button type="button" class="autoBtn" onclick="toggleAuto('<?php echo $username; ?>',<?php echo $i; ?>)">⏱ Auto</button>
              <form method="post" style="display:inline;" 
                    onsubmit="stopTimer(<?php echo $i; ?>); return confirm('Delete @<?php echo $username; ?>?');">
                <input type="hidden" name="username" value="<?php echo htmlspecialchars($username); ?>">
                <button type="submit" name="action" value="deleteUser" class="deleteBtn">🗑 Delete</button>
              </form>
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
let interval = 120;
let timers = {};

function refreshUser(username, index) {
  fetch("refresh.php?username=" + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      let statusEl = document.getElementById("status" + index);
      let followersEl = document.getElementById("followers" + index);
      let followingEl = document.getElementById("following" + index);

      statusEl.textContent = data.status.replace("_"," ");
      statusEl.className = "badge " + data.status;
      followersEl.textContent = data.followers;
      followingEl.textContent = data.following;
    })
    .catch(err => console.error("Error:", err));
}

function toggleAuto(username, index) {
  if (timers[index]) {
    clearInterval(timers[index].intervalId);
    document.getElementById("countdown" + index).innerText = "";
    delete timers[index];
  } else {
    let seconds = interval;
    document.getElementById("countdown" + index).innerText = "Next refresh in " + seconds + "s";
    timers[index] = {
      intervalId: setInterval(() => {
        seconds--;
        if (seconds <= 0) {
          refreshUser(username, index);
          seconds = interval;
        }
        document.getElementById("countdown" + index).innerText = "Next refresh in " + seconds + "s";
      }, 1000)
    };
  }
}

function stopTimer(index) {
  if (timers[index]) {
    clearInterval(timers[index].intervalId);
    delete timers[index];
  }
}

function stopAllTimers() {
  for (let i in timers) {
    clearInterval(timers[i].intervalId);
  }
  timers = {};
}

function copyUsername(username) {
  navigator.clipboard.writeText(username);
  alert("Copied: @" + username);
}

// Matrix background
const canvas = document.getElementById('matrix');
const ctx = canvas.getContext('2d');
canvas.height = window.innerHeight;
canvas.width = window.innerWidth;
const letters = "アァイィウヴエェオカガキギクグケゲコゴサザシジスズセゼソゾタダチヂッツヅテデトドナニヌネノハバパヒビピフブプヘベペホボポマミムメモヤユヨラリルレロワンABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
const matrix = letters.split("");
const font_size = 14;
const columns = canvas.width / font_size;
let drops = [];
for(let x = 0; x < columns; x++) drops[x] = 1;
function draw() {
  ctx.fillStyle = "rgba(0, 0, 0, 0.08)";
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.fillStyle = "#0F0";
  ctx.font = font_size + "px monospace";
  for(let i = 0; i < drops.length; i++) {
    const text = matrix[Math.floor(Math.random()*matrix.length)];
    ctx.fillText(text, i*font_size, drops[i]*font_size);
    if(drops[i]*font_size > canvas.height && Math.random() > 0.975) drops[i] = 0;
    drops[i]++;
  }
}
setInterval(draw, 33);
</script>
</body>
</html>
