<?php
// ============================
// Scooby Doo — Username Checker (no login)
// Stores:
//   - cookies in cookies.txt
//   - usernames in usernames.txt
// ============================

error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$usernamesFile = "usernames.txt";
$lastUpdated = file_exists("cookies_updated.txt") ? trim(file_get_contents("cookies_updated.txt")) : "Never";

// ---------- Helpers ----------
function load_usernames($file) {
    if (!file_exists($file)) return [];
    $arr = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $arr = array_map('trim', $arr);
    $arr = array_values(array_unique(array_filter($arr)));
    return $arr;
}
function save_usernames($file, $arr) {
    $arr = array_map('trim', $arr);
    $arr = array_values(array_unique(array_filter($arr)));
    file_put_contents($file, implode("\n", $arr) . (count($arr) ? "\n" : ""));
}

// ---------- AJAX (add/delete) ----------
if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    if ($action === 'add_bulk') {
        $incoming = trim($_POST['usernames'] ?? '');
        $list = preg_split('/\r\n|\r|\n/', $incoming);
        $list = array_map('trim', $list);
        $list = array_filter($list);

        $current = load_usernames($usernamesFile);
        $merged = array_values(array_unique(array_merge($current, $list)));
        save_usernames($usernamesFile, $merged);

        echo json_encode(['success' => true, 'count' => count($merged)]);
        exit;
    } elseif ($action === 'delete_one') {
        $name = trim($_POST['username'] ?? '');
        if ($name === '') {
            echo json_encode(['success' => false, 'error' => 'Empty username']);
            exit;
        }
        $current = load_usernames($usernamesFile);
        $filtered = array_values(array_filter($current, fn($u) => strcasecmp($u, $name) !== 0));
        save_usernames($usernamesFile, $filtered);
        echo json_encode(['success' => true, 'count' => count($filtered)]);
        exit;
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        exit;
    }
}

// ---------- Normal POST (save cookies / replace all usernames) ----------
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
$savedUsernames = load_usernames($usernamesFile);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['ajax'])) {
    $action = $_POST['action'] ?? '';
    if ($action === 'saveCookies') {
        $cookies = trim($_POST['cookies'] ?? '');
        file_put_contents($cookiesFile, $cookies);
        file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
        $lastUpdated = trim(file_get_contents("cookies_updated.txt"));
    } elseif ($action === 'replaceUsernames') {
        $incoming = trim($_POST['usernames'] ?? '');
        $list = preg_split('/\r\n|\r|\n/', $incoming);
        save_usernames($usernamesFile, $list);
        $savedUsernames = load_usernames($usernamesFile);
    }
    // refresh local (in case changed)
    $cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
    $savedUsernames = load_usernames($usernamesFile);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<title>Scooby Doo - Username Checker</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  :root{
    --bg:#000; --panel:#0b0b0b; --grid:#133113; --text:#00ff88; --accent:#00ffaa; --warn:#ffd000; --bad:#ff3b3b; --muted:#7affc7;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--text);font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace}
  header{padding:14px 16px;border-bottom:1px solid #0f2919;background:linear-gradient(180deg,#061a10,#050505)}
  .brand{display:flex;align-items:center;gap:10px}
  .logo{width:28px;height:28px;border-radius:6px;background:#093;display:grid;place-items:center;font-weight:900;color:#021;box-shadow:0 0 10px #093 inset}
  .title{font-weight:800;letter-spacing:.5px}
  .container{padding:16px;max-width:1100px;margin:0 auto}
  .card{background:linear-gradient(180deg,#070d07,#050505);border:1px solid #0e2d18;border-radius:12px;padding:14px 14px 10px 14px;box-shadow:0 0 30px rgba(0,255,100,.06)}
  textarea,input[type="number"],input[type="text"]{width:100%;background:#040704;color:var(--muted);border:1px solid #173a24;border-radius:8px;padding:10px;outline:none}
  textarea::placeholder{color:#3f6}
  .row{display:grid;grid-template-columns:1fr;gap:12px}
  .actions{display:flex;flex-wrap:wrap;gap:8px}
  button{border:1px solid #1f5a3a;background:#0f351f;color:var(--text);padding:10px 12px;border-radius:8px;cursor:pointer;font-weight:700}
  button:hover{filter:brightness(1.1)}
  .btn-save{background:#0b3; border-color:#0d4}
  .btn-replace{background:#003b3b; border-color:#066}
  .btn-refresh{background:#2c2c05; border-color:#665}
  .btn-auto{background:#033a3a; border-color:#066}
  .btn-del{background:#3b0505; border-color:#a22}
  .grid{margin-top:16px;overflow:auto;border:1px solid #0e2d18;border-radius:10px}
  table{width:100%;border-collapse:collapse;background:var(--panel)}
  th,td{padding:10px;border-bottom:1px solid #0d2818}
  th{position:sticky;top:0;background:#06150d;text-align:left}
  .badge{padding:2px 8px;border-radius:999px;font-size:12px;font-weight:800;color:#021;display:inline-block}
  .exists{background:#00ff88}
  .not_found{background:#ff3b3b;color:#fff}
  .invalid_session{background:#7d7d7d;color:#fff}
  .error{background:#ffd000;color:#000}
  .countdown{font-size:12px;color:#86ffc5;margin-top:6px}
  .copy-tip{font-size:11px;color:#5cffb0}
  .username-btn{background:none;border:none;color:var(--accent);font-weight:800;cursor:pointer;padding:0}
  .username-btn:hover{text-decoration:underline}
  .pill{display:inline-flex;align-items:center;gap:8px}
  .muted{color:#8effd6}
  .right{justify-content:flex-end}
  @media (min-width:800px){
    .row{grid-template-columns:2fr 3fr}
  }
</style>
</head>
<body>
<header>
  <div class="brand">
    <div class="logo">SD</div>
    <div class="title">🐶 Scooby Doo — Instagram Username Checker</div>
  </div>
</header>

<div class="container">
  <!-- Cookies / Bulk add -->
  <div class="card">
    <div class="row">
      <div>
        <label>Paste Instagram cookies</label>
        <textarea rows="3" name="cookies" id="cookies" placeholder="ig_did=...; mid=...; csrftoken=...; sessionid=...;"><?php echo htmlspecialchars($cookies); ?></textarea>
        <div class="actions">
          <button class="btn-save" onclick="saveCookies()">💾 Save Cookies</button>
          <span class="pill muted">Last updated: <strong><?php echo htmlspecialchars($lastUpdated); ?></strong></span>
        </div>
      </div>
      <div>
        <label>Bulk add usernames (one per line)</label>
        <textarea rows="3" id="bulkNames" placeholder="username1&#10;username2"></textarea>
        <div class="actions">
          <button class="btn-save" onclick="addBulk()">➕ Add</button>
          <button class="btn-replace" onclick="replaceAll()">♻ Replace All</button>
          <div style="display:flex;align-items:center;gap:8px;margin-left:auto">
            <label class="muted">Auto refresh interval (sec)</label>
            <input type="number" id="interval" value="<?php echo isset($_POST['refreshTime']) ? (int)$_POST['refreshTime'] : 120; ?>" min="5" style="max-width:120px" />
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="grid">
    <table id="tbl">
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
      <tbody id="tbody">
        <?php foreach ($savedUsernames as $i => $username): 
          $safe = htmlspecialchars($username);
        ?>
        <tr id="row-<?php echo $i; ?>" data-username="<?php echo $safe; ?>">
          <td>
            <button class="username-btn" title="Click to copy">@<?php echo $safe; ?></button>
            <div class="copy-tip">tap to copy • open: instagram.com/<?php echo $safe; ?></div>
          </td>
          <td><span class="badge" id="status-<?php echo $i; ?>">-</span></td>
          <td id="followers-<?php echo $i; ?>">-</td>
          <td id="following-<?php echo $i; ?>">-</td>
          <td id="last-<?php echo $i; ?>">Never</td>
          <td>
            <div class="actions right">
              <button class="btn-refresh" onclick="refreshUser('<?php echo $safe; ?>', <?php echo $i; ?>)">🔄 Refresh</button>
              <button class="btn-auto" onclick="toggleAuto('<?php echo $safe; ?>', <?php echo $i; ?>)">⏱ Auto</button>
              <button class="btn-del" onclick="deleteUser('<?php echo $safe; ?>', <?php echo $i; ?>)">🗑 Delete</button>
            </div>
            <div class="countdown" id="countdown-<?php echo $i; ?>"></div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($savedUsernames)): ?>
        <tr><td colspan="6" class="muted">No usernames yet. Add some above.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const timers = {}; // id -> { intervalId, seconds }
function $(id){ return document.getElementById(id); }

function saveCookies(){
  const cookies = $('cookies').value.trim();
  const form = new FormData();
  form.append('action','saveCookies');
  form.append('cookies', cookies);
  fetch(location.pathname, {method:'POST', body:form})
    .then(()=>alert('Cookies saved ✅'));
}

function addBulk(){
  const text = $('bulkNames').value.trim();
  if (!text) return alert('Enter at least one username');
  const form = new URLSearchParams();
  form.append('ajax','1');
  form.append('action','add_bulk');
  form.append('usernames', text);
  fetch(location.pathname, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: form.toString()
  }).then(r=>r.json()).then(j=>{
    if(j.success){ location.reload(); } else { alert(j.error||'Failed'); }
  });
}

function replaceAll(){
  const text = $('bulkNames').value.trim();
  const form = new FormData();
  form.append('action','replaceUsernames');
  form.append('usernames', text);
  form.append('refreshTime', $('interval').value || '120');
  fetch(location.pathname, {method:'POST', body:form})
    .then(()=>location.reload());
}

function deleteUser(username, idx){
  if (!confirm('Delete @' + username + '?')) return;
  const form = new URLSearchParams();
  form.append('ajax','1');
  form.append('action','delete_one');
  form.append('username', username);
  fetch(location.pathname, {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body: form.toString()
  }).then(r=>r.json()).then(j=>{
    if(j.success){
      const row = document.querySelector(`#row-${idx}`);
      if (row) row.remove();
      // stop timer if running
      if (timers[idx]) { clearInterval(timers[idx].intervalId); delete timers[idx]; }
    } else {
      alert(j.error||'Delete failed');
    }
  });
}

function refreshUser(username, idx){
  fetch('refresh.php?username=' + encodeURIComponent(username))
    .then(res => res.json())
    .then(data => {
      const s = $('status-'+idx), f1 = $('followers-'+idx), f2 = $('following-'+idx), last = $('last-'+idx);
      if (!s || !f1 || !f2 || !last) return;

      if (data.status === 'exists') {
        s.textContent = '✔ Exists';
        s.className = 'badge exists';
        f1.textContent = data.followers;
        f2.textContent = data.following;
      } else if (data.status === 'not_found') {
        s.textContent = '❌ Not Found';
        s.className = 'badge not_found';
        f1.textContent = '-'; f2.textContent = '-';
      } else if (data.status === 'invalid_session') {
        s.textContent = '⚠ Invalid Session';
        s.className = 'badge invalid_session';
        f1.textContent = '-'; f2.textContent = '-';
      } else if (data.status === 'error') {
        s.textContent = '⚠ Error' + (data.message ? (': ' + data.message) : '');
        s.className = 'badge error';
        f1.textContent = '-'; f2.textContent = '-';
        console.log('Debug:', data.raw || '');
      } else {
        s.textContent = '⚠ Unexpected';
        s.className = 'badge error';
        f1.textContent = '-'; f2.textContent = '-';
      }
      last.textContent = new Date().toLocaleTimeString();
    })
    .catch(err => {
      const s = $('status-'+idx);
      if (s) { s.textContent = '⚠ Fetch Error'; s.className = 'badge error'; }
      console.error(err);
    });
}

function toggleAuto(username, idx){
  const intervalInput = $('interval');
  let base = parseInt(intervalInput.value || '120', 10);
  if (isNaN(base) || base < 5) base = 120;

  if (timers[idx]) {
    clearInterval(timers[idx].intervalId);
    delete timers[idx];
    const cd = $('countdown-'+idx);
    if (cd) cd.textContent = '';
    return;
  }

  const cd = $('countdown-'+idx);
  let seconds = base;
  if (cd) cd.textContent = 'Next in ' + seconds + 's';

  timers[idx] = {
    intervalId: setInterval(()=>{
      seconds--;
      if (seconds <= 0) {
        refreshUser(username, idx);
        seconds = parseInt(intervalInput.value || '120', 10);
        if (isNaN(seconds) || seconds < 5) seconds = 120;
      }
      if (cd) cd.textContent = 'Next in ' + seconds + 's';
    }, 1000)
  };
}

// copy username on click + also open instagram in new tab on long-press (mobile workaround)
document.addEventListener('click', (e)=>{
  if (e.target && e.target.classList.contains('username-btn')) {
    const cell = e.target.closest('tr');
    const uname = cell?.dataset?.username || '';
    if (!uname) return;
    navigator.clipboard.writeText(uname).then(()=>{
      e.target.textContent = '@' + uname + '  (copied)';
      setTimeout(()=>{ e.target.textContent = '@' + uname; }, 1200);
      // also open profile in new tab
      window.open('https://instagram.com/' + encodeURIComponent(uname), '_blank');
    }).catch(()=>{
      // fallback: just open
      window.open('https://instagram.com/' + encodeURIComponent(uname), '_blank');
    });
  }
});
</script>
</body>
</html>
