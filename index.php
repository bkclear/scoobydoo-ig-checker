<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$userFile = "usernames.json";
$settingsFile = "settings.json";

// Ensure storage files exist
if (!file_exists($userFile)) file_put_contents($userFile, "[]");
if (!file_exists($settingsFile)) file_put_contents($settingsFile, json_encode([
    "sessionid" => "",
    "csrftoken" => "",
    "ds_user_id" => ""
], JSON_PRETTY_PRINT));

function readJson($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

$tab = $_GET['tab'] ?? 'usernames';

// --- Handle Usernames actions ---
if ($tab === 'usernames') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
        $username = trim($_POST['username']);
        if ($username !== "") {
            $data = readJson($userFile);
            $data[] = [
                'id' => bin2hex(random_bytes(6)),
                'username' => $username,
                'created_at' => date('c')
            ];
            saveJson($userFile, $data);
        }
        header("Location: index.php?tab=usernames");
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
        $id = $_POST['delete_id'];
        $data = readJson($userFile);
        $data = array_values(array_filter($data, fn($r) => $r['id'] !== $id));
        saveJson($userFile, $data);
        header("Location: index.php?tab=usernames");
        exit;
    }
    $data = readJson($userFile);
    $settings = readJson($settingsFile);
}

// --- Handle Settings actions ---
if ($tab === 'settings') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $settings = [
            "sessionid" => trim($_POST['sessionid'] ?? ""),
            "csrftoken" => trim($_POST['csrftoken'] ?? ""),
            "ds_user_id" => trim($_POST['ds_user_id'] ?? "")
        ];
        saveJson($settingsFile, $settings);
        header("Location: index.php?tab=settings&saved=1");
        exit;
    }
    $settings = readJson($settingsFile);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Scooby-Doo IG Checker</title>
<style>
  body { font-family: Arial, sans-serif; background:#111; color:#eee; margin:0; padding:0; }
  nav { display:flex; background:#222; }
  nav a { flex:1; padding:1rem; text-align:center; color:#fff; text-decoration:none; }
  nav a.active { background:#28a745; }
  .container { padding:1rem; }
  h2 { margin-top:0; }
  table { width:100%; border-collapse:collapse; background:#222; margin-top:1rem; }
  th, td { padding:.75rem; border-bottom:1px solid #444; }
  th { background:#333; }
  tr:hover { background:#2a2a2a; }
  input[type="text"], textarea { width:100%; padding:.5rem; border-radius:6px; border:none; margin-bottom:.5rem; }
  button { padding:.5rem 1rem; border:none; border-radius:6px; cursor:pointer; }
  button.add { background:#28a745; color:#fff; }
  button.delete { background:#dc3545; color:#fff; }
  button.save { background:#007bff; color:#fff; margin-top:.5rem; }
  .time { font-size:.9em; color:#aaa; }
  @media(max-width:600px){
    table, thead, tbody, th, td, tr { display:block; }
    th { display:none; }
    td { border:none; padding:.5rem 0; }
    td::before { font-weight:bold; display:block; }
    td:nth-child(1)::before { content:"Username"; }
    td:nth-child(2)::before { content:"Added"; }
    td:nth-child(3)::before { content:"Action"; }
  }
  .status-box { background:#222; padding:1rem; border-radius:8px; margin-top:1rem; }
  .status-box p { margin:.3rem 0; }
</style>
</head>
<body>
<nav>
  <a href="?tab=usernames" class="<?= $tab==='usernames'?'active':'' ?>">Usernames</a>
  <a href="?tab=settings" class="<?= $tab==='settings'?'active':'' ?>">Settings</a>
</nav>
<div class="container">

<?php if ($tab==='usernames'): ?>
  <h2>Manage Usernames</h2>

  <form method="post" style="display:flex; gap:.5rem;">
    <input type="text" name="username" placeholder="Enter username..." required>
    <button type="submit" class="add">Add</button>
  </form>

  <table>
    <thead><tr><th>Username</th><th>Added</th><th>Action</th></tr></thead>
    <tbody>
      <?php foreach ($data as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td class="time" data-ts="<?= htmlspecialchars($row['created_at']) ?>">
            <?= date('M d, Y H:i', strtotime($row['created_at'])) ?>
          </td>
          <td>
            <form method="post" style="display:inline" onsubmit="return confirmDelete('<?= htmlspecialchars($row['username']) ?>')">
              <input type="hidden" name="delete_id" value="<?= htmlspecialchars($row['id']) ?>">
              <button type="submit" class="delete">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="status-box">
    <h3>🔑 Current Settings</h3>
    <p><strong>sessionid:</strong> <?= $settings['sessionid'] ? substr($settings['sessionid'],0,6).'...' : '<i>Not set</i>' ?></p>
    <p><strong>csrftoken:</strong> <?= $settings['csrftoken'] ? substr($settings['csrftoken'],0,6).'...' : '<i>Not set</i>' ?></p>
    <p><strong>ds_user_id:</strong> <?= $settings['ds_user_id'] ?: '<i>Not set</i>' ?></p>
  </div>

<?php elseif ($tab==='settings'): ?>
  <h2>Settings</h2>
  <?php if (!empty($_GET['saved'])): ?><p style="color:#28a745;">✅ Settings saved!</p><?php endif; ?>
  <form method="post">
    <label>sessionid</label>
    <input type="text" name="sessionid" value="<?= htmlspecialchars($settings['sessionid']) ?>">
    <label>csrftoken</label>
    <input type="text" name="csrftoken" value="<?= htmlspecialchars($settings['csrftoken']) ?>">
    <label>ds_user_id</label>
    <input type="text" name="ds_user_id" value="<?= htmlspecialchars($settings['ds_user_id']) ?>">
    <button type="submit" class="save">Save Settings</button>
  </form>
<?php endif; ?>

</div>
<script>
function confirmDelete(username) {
  return confirm(`Delete "${username}"?`);
}

// Friendly "time ago"
function timeAgo(ts) {
  const diff = (Date.now() - new Date(ts).getTime())/1000;
  const units = [
    ['year',31536000],['month',2592000],['day',86400],
    ['hour',3600],['minute',60],['second',1]
  ];
  for (const [n,sec] of units) {
    const v = Math.floor(diff/sec);
    if (v>=1) return v+" "+n+(v>1?"s":"")+" ago";
  }
  return "just now";
}
document.querySelectorAll('[data-ts]').forEach(td=>{
  const ts=td.getAttribute('data-ts');
  td.textContent=timeAgo(ts);
  td.title=new Date(ts).toLocaleString();
});
setInterval(()=>{
  document.querySelectorAll('[data-ts]').forEach(td=>{
    const ts=td.getAttribute('data-ts');
    td.textContent=timeAgo(ts);
  });
},60000);
</script>
</body>
</html>
