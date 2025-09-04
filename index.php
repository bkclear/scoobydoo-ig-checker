<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$file = "usernames.json";
if (!file_exists($file)) {
    file_put_contents($file, "[]");
}

function readData($file) {
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveData($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Handle add username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = trim($_POST['username']);
    if ($username !== "") {
        $data = readData($file);
        $data[] = [
            'id' => bin2hex(random_bytes(6)),
            'username' => $username,
            'created_at' => date('c')
        ];
        saveData($file, $data);
    }
    header("Location: index.php");
    exit;
}

// Handle delete username
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = $_POST['delete_id'];
    $data = readData($file);
    $data = array_values(array_filter($data, fn($r) => $r['id'] !== $id));
    saveData($file, $data);
    header("Location: index.php");
    exit;
}

$data = readData($file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Scooby-Doo IG Checker</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 0; padding: 1rem; background: #111; color: #eee; }
    h1 { text-align: center; }
    form { display: flex; gap: .5rem; justify-content: center; margin-bottom: 1rem; }
    input[type="text"] { flex: 1; padding: .5rem; border-radius: 8px; border: none; }
    button { padding: .5rem 1rem; border: none; border-radius: 8px; cursor: pointer; }
    button.add { background: #28a745; color: #fff; }
    button.delete { background: #dc3545; color: #fff; }
    table { width: 100%; border-collapse: collapse; background: #222; }
    th, td { padding: .75rem; text-align: left; border-bottom: 1px solid #444; }
    th { background: #333; }
    tr:hover { background: #2a2a2a; }
    .time { font-size: .9em; color: #aaa; }
    @media(max-width:600px){
      table, thead, tbody, th, td, tr { display: block; }
      th { display: none; }
      td { border: none; padding: .5rem 0; }
      td::before { font-weight: bold; display: block; }
      td:nth-child(1)::before { content: "Username"; }
      td:nth-child(2)::before { content: "Added"; }
    }
  </style>
</head>
<body>
  <h1>🔍 Scooby-Doo IG Checker</h1>

  <form method="post">
    <input type="text" name="username" placeholder="Enter username..." required>
    <button type="submit" class="add">Add</button>
  </form>

  <table>
    <thead>
      <tr>
        <th>Username</th>
        <th>Added</th>
        <th>Actions</th>
      </tr>
    </thead>
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

<script>
function confirmDelete(username) {
  return confirm(`Are you sure you want to delete "${username}"?`);
}

function timeAgo(ts) {
  const diff = (Date.now() - new Date(ts).getTime()) / 1000;
  const units = [
    ['year', 31536000], ['month', 2592000],
    ['day', 86400], ['hour', 3600],
    ['minute', 60], ['second', 1]
  ];
  for (const [name, secs] of units) {
    const v = Math.floor(diff / secs);
    if (v >= 1) return `${v} ${name}${v>1?'s':''} ago`;
  }
  return 'just now';
}

document.querySelectorAll('[data-ts]').forEach(td => {
  const ts = td.getAttribute('data-ts');
  td.textContent = timeAgo(ts);
  td.setAttribute('title', new Date(ts).toLocaleString());
});

setInterval(() => {
  document.querySelectorAll('[data-ts]').forEach(td => {
    const ts = td.getAttribute('data-ts');
    td.textContent = timeAgo(ts);
  });
}, 60000);
</script>
</body>
</html>
