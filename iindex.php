<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$cookiesFile = "cookies.txt";
$usernamesFile = "usernames.txt";
$dataFile = "data.json";
$lastUpdated = file_exists("cookies_updated.txt") ? file_get_contents("cookies_updated.txt") : "Never";

// Load cookies
$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";

// Load usernames
$savedUsernames = file_exists($usernamesFile) ? file($usernamesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// Load user data (status/followers/following/history)
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($userData)) $userData = [];

// Ensure every saved username exists in data.json
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

/**
 * AJAX actions (no page reload)
 * - addUsernames: replaces usernames.txt with provided list
 * - deleteUser: removes one username
 * Each returns updated state as JSON for the frontend to re-render.
 */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['ajax']) && $_POST['ajax'] === '1') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';

    if ($action === 'saveCookies') {
        $newCookies = trim($_POST['cookies'] ?? '');
        if ($newCookies !== '') {
            file_put_contents($cookiesFile, $newCookies);
            file_put_contents("cookies_updated.txt", date("Y-m-d H:i:s"));
            $cookies = $newCookies;
        }
        echo json_encode([
            "ok" => true,
            "message" => "Cookies saved",
            "lastUpdated" => file_get_contents("cookies_updated.txt")
        ]);
        exit;
    }

    if ($action === 'addUsernames') {
        $raw = trim($_POST['usernames'] ?? '');
        $list = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $raw)));
        // Persist usernames
        file_put_contents($usernamesFile, implode("\n", $list));
        $savedUsernames = $list;

        // Merge placeholders in data
        $userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
        if (!is_array($userData)) $userData = [];
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
        // Remove entries no longer in list (keeps file clean)
        foreach ($userData as $u => $_v) {
            if (!in_array($u, $savedUsernames, true)) unset($userData[$u]);
        }
        file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

        echo json_encode([
            "ok" => true,
            "usernames" => array_values($savedUsernames),
            "userData" => $userData,
            "message" => "Usernames saved"
        ]);
        exit;
    }

    if ($action === 'deleteUser') {
        $username = trim($_POST['username'] ?? '');
        if ($username !== '') {
            // Remove from usernames
            $savedUsernames = array_values(array_filter($savedUsernames, fn($u) => $u !== $username));
            file_put_contents($usernamesFile, implode("\n", $savedUsernames));
            // Remove from data
            $userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
            if (isset($userData[$username])) unset($userData[$username]);
            file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }
        echo json_encode([
            "ok" => true,
            "usernames" => array_values($savedUsernames),
            "userData" => $userData,
            "message" => "@$username deleted"
        ]);
        exit;
    }

    echo json_encode(["ok" => false, "message" => "Unknown action"]);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Scooby Doo 🕵️</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <style>
    :root{
      --neon:#0f0;
      --bg:#000;
      --card:#111;
      --cardAlpha: rgba(0,0,0,0.82);
      --border:#0f0;
      --accent:#00f;
      --danger:#f00;
      --muted:#555;
    }
    *{box-sizing:border-box}
    body{
      margin:0; padding:20px; font-family:Courier, monospace;
      background:var(--bg); color:var(--neon); overflow-x:hidden;
    }
    h1{
      text-align:center; margin:10px 0 22px;
      text-shadow:0 0 8px var(--neon), 0 0 15px var(--neon);
      position:relative; z-index:2;
    }
    .container{ max-width:1100px; margin:0 auto; position:relative; z-index:2; }

    .banner{
      display:none;
      background:rgba(255,0,0,0.1);
      border:1px solid var(--danger);
      color:#fff; padding:10px 14px; border-radius:8px;
      margin-bottom:16px;
      box-shadow:0 0 12px rgba(255,0,0,0.35) inset;
    }

    .grid{
      display:grid; gap:18px;
      grid-template-columns: 1fr;
    }

    .card{
      background:var(--cardAlpha);
      border:1px solid var(--border);
      border-radius:12px; padding:18px;
      box-shadow:0 0 10px var(--neon) inset;
    }
    .card h2{
      margin:0 0 10px; font-size:18px; text-shadow:0 0 5px var(--neon);
    }

    textarea, input, select{
      width:100%; background:var(--bg); color:var(--neon);
      border:1px solid var(--border); border-radius:8px;
      padding:10px; margin-top:8px; box-shadow:0 0 6px var(--neon) inset;
      font-family:inherit;
    }
    textarea:focus, input:focus, select:focus{ outline:none; box-shadow:0 0 10px var(--neon); }

    .row{ display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
    .row > *{ flex:1 1 auto; }

    button{
      background:var(--neon); color:#000; font-weight:700; border:none;
      border-radius:8px; padding:9px 14px; cursor:pointer;
      box-shadow:0 0 10px var(--neon); transition:transform .15s, box-shadow .15s;
    }
    button:hover{ transform:scale(1.05); box-shadow:0 0 18px var(--neon); }
    .btn-blue{ background:var(--accent); color:#fff; box-shadow:0 0 10px var(--accent); }
    .btn-red{ background:var(--danger); color:#fff; box-shadow:0 0 10px var(--danger); }
    .btn-muted{ background:#063; color:var(--neon); }

    .controls{ display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
    .controls > * { flex:0 0 auto; }

    .table-wrap{ margin-top:12px; overflow:auto; max-height:65vh; border-radius:10px; }
    table{ width:100%; border-collapse:collapse; background:#111; box-shadow:0 0 10px var(--neon) inset; }
    th, td{ border:1px solid var(--border); padding:10px; text-align:center; }
    th{ background:#222; text-shadow:0 0 5px var(--neon); position:sticky; top:0; z-index:1; }
    tr:nth-child(even){ background:#151515; }

    .badge{ padding:4px 10px; border-radius:12px; font-weight:700; box-shadow:0 0 8px currentColor; display:inline-block; min-width:90px; }
    .exists{ background:var(--neon); color:#000; }
    .not_found{ background:var(--danger); color:#fff; }
    .error{ background:#ff0; color:#000; }
    .invalid_session{ background:var(--muted); color:#fff; }
    .private{ background:#ffa500; color:#000; }

    .copyable{ cursor:pointer; }
    .copyable:hover{ text-decoration:underline; color:#ff0; }

    .count{ font-size:12px; color:var(--neon); margin-top:4px; }

    .legend{ font-size:12px; display:flex; gap:12px; flex-wrap:wrap; margin-top:10px; }
    .legend .chip{ display:flex; align-items:center; gap:6px; }
    .legend .dot{ width:14px; height:14px; border-radius:7px; box-shadow:0 0 6px currentColor; display:inline-block; }
    .dot.exists{ background:var(--neon); color:#000; }
    .dot.not_found{ background:var(--danger); }
    .dot.error{ background:#ff0; color:#000; }
    .dot.invalid_session{ background:var(--muted); }
    .dot.private{ background:#ffa500; }

    /* Matrix canvas */
    #matrix{ position:fixed; inset:0; z-index:0; background:#000; }
    @media (min-width: 900px) {
      .grid{ grid-template-columns: 1fr 1fr; }
      .wide{ grid-column: 1 / -1; }
    }
  </style>
</head>
<body>
  <canvas id="matrix"></canvas>

  <div class="container">
    <h1>🕵️ Scooby Doo</h1>

    <div id="banner" class="banner">⚠️ Session expired or invalid cookies. Please update cookies.</div>

    <div class="grid">
      <div class="card">
        <h2>🔑 Instagram Cookies</h2>
        <div>Last updated: <span id="cookies-updated"><?php echo htmlspecialchars($lastUpdated); ?></span></div>
        <textarea id="cookies" rows="3"><?php echo htmlspecialchars($cookies); ?></textarea>
        <div class="controls">
          <button id="saveCookiesBtn">💾 Save Cookies</button>
        </div>
      </div>

      <div class="card">
        <h2>📋 Manage Usernames</h2>
        <textarea id="usernamesInput" rows="6" placeholder="one username per line"></textarea>
        <div class="controls">
          <button id="saveUsernamesBtn">▶ Save Usernames</button>
          <input id="searchBox" placeholder="Search username…" />
        </div>
        <div class="legend">
          <div class="chip"><span class="dot exists"></span> exists</div>
          <div class="chip"><span class="dot not_found"></span> not found</div>
          <div class="chip"><span class="dot invalid_session"></span> invalid session</div>
          <div class="chip"><span class="dot error"></span> error</div>
          <div class="chip"><span class="dot private"></span> private</div>
        </div>
      </div>

      <div class="card wide">
        <h2>📊 Username Status</h2>
        <div class="row">
          <div style="flex:0 0 220px">
            <label for="intervalSel">Refresh interval</label>
            <select id="intervalSel">
              <option value="30">30s</option>
              <option value="60" selected>60s</option>
              <option value="120">120s</option>
              <option value="300">5m</option>
            </select>
          </div>
          <div class="controls">
            <button id="refreshAll" class="btn-blue">🔄 Refresh All</button>
            <button id="autoAllStart" class="btn-muted">⏱ Start Auto (All)</button>
            <button id="autoAllStop" class="btn-red">⏹ Stop Auto (All)</button>
          </div>
        </div>

        <div class="table-wrap">
          <table id="table">
            <thead>
              <tr>
                <th style="min-width:220px">Username</th>
                <th>Status</th>
                <th>Followers</th>
                <th>Following</th>
                <th>Last&nbsp;Checked</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="tbody">
              <?php foreach ($savedUsernames as $username): 
                $info = $userData[$username] ?? ["status"=>"-", "followers"=>"-", "following"=>"-", "last_checked"=>null];
              ?>
              <tr data-username="<?php echo htmlspecialchars($username); ?>">
                <td class="copyable" onclick="copyUsername('<?php echo $username; ?>')">
                  <a href="https://instagram.com/<?php echo htmlspecialchars($username); ?>" target="_blank">@<?php echo htmlspecialchars($username); ?></a>
                </td>
                <td><span class="badge <?php echo htmlspecialchars($info['status']); ?>" id="status-<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($info['status']); ?></span></td>
                <td id="followers-<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($info['followers']); ?></td>
                <td id="following-<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($info['following']); ?></td>
                <td id="last-<?php echo htmlspecialchars($username); ?>"><?php echo htmlspecialchars($info['last_checked'] ?? '—'); ?></td>
                <td>
                  <button class="btn-blue" onclick="refreshUser('<?php echo htmlspecialchars($username); ?>')">🔄 Refresh</button>
                  <button class="btn-muted" onclick="toggleAuto('<?php echo htmlspecialchars($username); ?>')">⏱ Auto</button>
                  <button class="btn-red" onclick="deleteUser('<?php echo htmlspecialchars($username); ?>')">🗑 Delete</button>
                  <div class="count" id="count-<?php echo htmlspecialchars($username); ?>"></div>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </div>
    </div>
  </div>

  <script>
    // ====== MATRIX BACKGROUND (dim) ======
    const canvas = document.getElementById('matrix');
    const ctx = canvas.getContext('2d');
    function sizeCanvas(){ canvas.width=window.innerWidth; canvas.height=window.innerHeight; }
    sizeCanvas(); window.addEventListener('resize', sizeCanvas);
    const letters = "アァイィウヴエェオカガキギクグケゲコゴサザシジスズセゼソゾタダチヂッツヅテデトドナニヌネノハバパヒビピフブプヘベペホボポマミムメモヤユヨラリルレロワンABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    const matrix = letters.split(""); const font_size=14;
    let columns = 0, drops = [];
    function resetDrops(){ columns = Math.floor(canvas.width / font_size); drops = new Array(columns).fill(1); }
    resetDrops(); window.addEventListener('resize', resetDrops);
    function draw(){
      ctx.fillStyle = "rgba(0,0,0,0.09)"; ctx.fillRect(0,0,canvas.width,canvas.height);
      ctx.fillStyle = "rgba(0,255,0,0.65)"; ctx.font = font_size + "px monospace";
      for(let i=0;i<drops.length;i++){
        const text = matrix[Math.floor(Math.random()*matrix.length)];
        ctx.fillText(text, i*font_size, drops[i]*font_size);
        if(drops[i]*font_size > canvas.height && Math.random() > 0.975) drops[i] = 0;
        drops[i]++;
      }
    }
    setInterval(draw, 33);

    // ====== STATE ======
    let timers = {};          // timers[username] = {intervalId, seconds}
    function currentInterval(){ return parseInt(document.getElementById('intervalSel').value,10); }

    // ====== UTIL ======
    function $(sel){ return document.querySelector(sel); }
    function qsa(sel){ return Array.from(document.querySelectorAll(sel)); }
    function sanitize(u){ return u.replace(/[^A-Za-z0-9_.-]/g,'_'); }

    function setRow(username, data){
      const u = sanitize(username);
      const st = document.getElementById('status-'+u);
      const fo = document.getElementById('followers-'+u);
      const fi = document.getElementById('following-'+u);
      const la = document.getElementById('last-'+u);
      if (st){ st.textContent = (data.status||'-').replace('_',' '); st.className = 'badge ' + (data.status||'-'); }
      if (fo){ fo.textContent = data.followers ?? '-'; }
      if (fi){ fi.textContent = data.following ?? '-'; }
      if (la){ la.textContent = data.last_checked ?? '—'; }
    }

    function copyUsername(u){
      navigator.clipboard.writeText(u);
      alert('Copied: @'+u);
    }

    // ====== AJAX HELPERS ======
    async function postForm(payload){
      const fd = new FormData();
      for (const k in payload) fd.append(k, payload[k]);
      const res = await fetch('index.php', { method:'POST', body:fd });
      return res.json();
    }

    // ====== COOKIES SAVE ======
    document.getElementById('saveCookiesBtn').onclick = async ()=>{
      const cookies = document.getElementById('cookies').value;
      const out = await postForm({ ajax:'1', action:'saveCookies', cookies });
      if (out.ok){
        document.getElementById('cookies-updated').textContent = out.lastUpdated || '';
      }
    };

    // ====== SAVE USERNAMES (AJAX, NO RELOAD) ======
    document.getElementById('saveUsernamesBtn').onclick = async ()=>{
      stopAll();
      const raw = document.getElementById('usernamesInput').value.trim();
      const out = await postForm({ ajax:'1', action:'addUsernames', usernames:raw });
      if(out.ok){
        document.getElementById('usernamesInput').value = ''; // clear
        renderTable(out.usernames, out.userData);
      }
    };

    // ====== DELETE USER (AJAX) ======
    async function deleteUser(username){
      if (!confirm('Delete @'+username+'?')) return;
      stop(username);
      const out = await postForm({ ajax:'1', action:'deleteUser', username });
      if(out.ok){
        renderTable(out.usernames, out.userData);
      }
    }

    // ====== RENDER TABLE ======
    function renderTable(usernames, data){
      // stop all timers and rebuild rows
      stopAll();
      const tbody = document.getElementById('tbody');
      tbody.innerHTML = '';
      (usernames||[]).forEach(username=>{
        const u = sanitize(username);
        const info = data && data[username] ? data[username] : {status:'-', followers:'-', following:'-', last_checked:null};
        const tr = document.createElement('tr');
        tr.setAttribute('data-username', username);
        tr.innerHTML = `
          <td class="copyable">
            <a href="https://instagram.com/${encodeURIComponent(username)}" target="_blank">@${username}</a>
          </td>
          <td><span class="badge ${info.status||'-'}" id="status-${u}">${(info.status||'-')}</span></td>
          <td id="followers-${u}">${info.followers ?? '-'}</td>
          <td id="following-${u}">${info.following ?? '-'}</td>
          <td id="last-${u}">${info.last_checked ?? '—'}</td>
          <td>
            <button class="btn-blue" onclick="refreshUser('${username}')">🔄 Refresh</button>
            <button class="btn-muted" onclick="toggleAuto('${username}')">⏱ Auto</button>
            <button class="btn-red" onclick="deleteUser('${username}')">🗑 Delete</button>
            <div class="count" id="count-${u}"></div>
          </td>
        `;
        tbody.appendChild(tr);
        // re-attach copy click
        tr.querySelector('.copyable').onclick = ()=>copyUsername(username);
      });
      applyFilter(); // keep search filter effective
    }

    // ====== FILTER ======
    document.getElementById('searchBox').addEventListener('input', applyFilter);
    function applyFilter(){
      const q = document.getElementById('searchBox').value.trim().toLowerCase();
      qsa('#tbody tr').forEach(tr=>{
        const un = (tr.getAttribute('data-username')||'').toLowerCase();
        tr.style.display = (!q || un.includes(q)) ? '' : 'none';
      });
    }

    // ====== REFRESH (ONE/ALL) ======
    const banner = document.getElementById('banner');

    async function refreshUser(username){
      const res = await fetch('refresh.php?username=' + encodeURIComponent(username));
      const data = await res.json();
      if (data && data.status){
        if (data.status === 'invalid_session'){
          banner.style.display = 'block';
        }
        // also fetch last_checked stored by refresh.php with a small fetch to data.json? 
        // We don't fetch file; instead we just set now:
        const now = new Date();
        const ts = now.getFullYear()+'-'+String(now.getMonth()+1).padStart(2,'0')+'-'+String(now.getDate()).padStart(2,'0')+' '+String(now.getHours()).padStart(2,'0')+':'+String(now.getMinutes()).padStart(2,'0')+':'+String(now.getSeconds()).padStart(2,'0');
        setRow(username, { ...data, last_checked: ts });
      }
    }

    document.getElementById('refreshAll').onclick = async ()=>{
      const rows = qsa('#tbody tr').filter(tr=>tr.style.display!=='none'); // visible rows (after filter)
      let delay = 0;
      rows.forEach(tr=>{
        const u = tr.getAttribute('data-username');
        setTimeout(()=>refreshUser(u), delay);
        delay += 600; // 0.6s between calls to be gentlgentler
    });
    };

    // ====== AUTO MODE (ONE/ALL) ======
    function tick(username){
      const u = sanitize(username);
      const c = document.getElementById('count-'+u);
      const t = timers[username];
      if (!t) return;
      t.seconds--;
      if (t.seconds <= 0){
        refreshUser(username);
        t.seconds = currentInterval();
      }
      if (c) c.textContent = 'Next refresh in '+ t.seconds +'s';
    }

    function toggleAuto(username){
      if (timers[username]){
        stop(username);
      }else{
        const id = setInterval(()=>tick(username), 1000);
        timers[username] = { intervalId:id, seconds: currentInterval() };
        const u = sanitize(username);
        const c = document.getElementById('count-'+u);
        if (c) c.textContent = 'Next refresh in '+ timers[username].seconds +'s';
      }
    }

    function stop(username){
      if (timers[username]){
        clearInterval(timers[username].intervalId);
        delete timers[username];
        const u = sanitize(username);
        const c = document.getElementById('count-'+u);
        if (c) c.textContent = '';
      }
    }

    function stopAll(){
      Object.keys(timers).forEach(k=>clearInterval(timers[k].intervalId));
      timers = {};
      qsa('.count').forEach(el=>el.textContent='');
    }

    document.getElementById('autoAllStart').onclick = ()=>{
      qsa('#tbody tr').forEach(tr=>{
        const u = tr.getAttribute('data-username');
        if (tr.style.display === 'none') return; // respect filter
        if (!timers[u]) toggleAuto(u);
      });
    };
    document.getElementById('autoAllStop').onclick = stopAll;

    document.getElementById('intervalSel').addEventListener('change', ()=>{
      // Reset countdowns to the new interval but keep timers running
      Object.keys(timers).forEach(u=>{ timers[u].seconds = currentInterval(); });
    });

    // ====== END OF SCRIPT ======
  </script>
</body>
</html>
