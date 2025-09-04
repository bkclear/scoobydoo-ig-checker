<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error"=>"Not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$action = $_POST["action"] ?? "";

if ($action === "add") {
    $username = trim($_POST["username"] ?? "");
    if ($username === "") {
        echo json_encode(["error"=>"Empty username"]);
        exit;
    }
    $stmt = $db->prepare("INSERT INTO usernames (user_id, name) VALUES (?, ?)");
    try {
        $stmt->execute([$user_id, $username]);
        echo json_encode(["success"=>true]);
    } catch (Exception $e) {
        echo json_encode(["error"=>"Already exists"]);
    }
    exit;
}

if ($action === "delete") {
    $id = intval($_POST["id"] ?? 0);
    $stmt = $db->prepare("DELETE FROM usernames WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    echo json_encode(["success"=>true]);
    exit;
}

echo json_encode(["error"=>"Invalid action"]);
