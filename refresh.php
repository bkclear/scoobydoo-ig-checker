<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["error"=>"Not logged in"]);
    exit;
}

$user_id = $_SESSION["user_id"];
$stmt = $db->prepare("SELECT cookies FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
$cookies = $user["cookies"] ?? "";

if (empty($cookies)) {
    echo json_encode(["status"=>"invalid_session","followers"=>0,"following"=>0]);
    exit;
}

$username = $_GET["username"] ?? "";
if (!$username) {
    echo json_encode(["status"=>"error","followers"=>0,"following"=>0]);
    exit;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://www.instagram.com/$username/?__a=1&__d=dis");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: $cookies",
    "User-Agent: Mozilla/5.0"
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (!$data) {
    echo json_encode(["status"=>"error","followers"=>0,"following"=>0]);
    exit;
}

$userData = $data["graphql"]["user"] ?? [];
echo json_encode([
    "status" => isset($userData["id"]) ? "exists" : "not_found",
    "followers" => $userData["edge_followed_by"]["count"] ?? 0,
    "following" => $userData["edge_follow"]["count"] ?? 0
]);
