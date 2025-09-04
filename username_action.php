<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error"=>"Not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$action = $_POST["action"] ?? "";

if ($action === "add_bulk") {
    $usernames = explode("\n", trim($_POST["usernames"] ?? ""));
    $added = 0;
    foreach ($usernames as $u) {
        $u = trim($u);
        if ($u === "") continue;
        try {
            $stmt = $db->prepare("INSERT INTO usernames (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $u]);
            $added++;
        } catch (Exception $e) {}
    }
    echo json_encode(["success"=>true, "added"=>$added]);
    exit;
}

if ($action === "delete") {
    $id = intval($_POST["id"]);
    $stmt = $db->prepare("DELETE FROM usernames WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    echo json_encode(["success"=>true]);
    exit;
}
