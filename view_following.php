<?php
header("Content-Type: application/json");

$cookies = file_exists("cookies.txt") ? trim(file_get_contents("cookies.txt")) : "";
if (empty($cookies)) {
    echo json_encode(["error" => "No cookies found. Please save cookies first."]);
    exit;
}

$username = $_GET["username"] ?? "";
if (empty($username)) {
    echo json_encode(["error" => "No username provided"]);
    exit;
}

$ch = curl_init("https://www.instagram.com/$username/?__a=1&__d=dis");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: $cookies",
    "User-Agent: Mozilla/5.0"
]);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(["error" => "Failed to fetch profile"]);
    exit;
}

$data = json_decode($response, true);

if (!isset($data["graphql"]["user"]["id"])) {
    echo json_encode(["error" => "User not found or cookies expired"]);
    exit;
}

$userId = $data["graphql"]["user"]["id"];

// Fetch following (limit ~50 per page)
$ch = curl_init("https://i.instagram.com/api/v1/friendships/$userId/following/?count=50");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: $cookies",
    "User-Agent: Instagram 155.0.0.37.107"
]);
$response = curl_exec($ch);
curl_close($ch);

if (!$response) {
    echo json_encode(["error" => "Failed to fetch following"]);
    exit;
}

$json = json_decode($response, true);
$following = [];

if (isset($json["users"])) {
    foreach ($json["users"] as $u) {
        $following[] = [
            "username" => $u["username"],
            "full_name" => $u["full_name"],
            "profile_pic" => $u["profile_pic_url"],
            "follower_count" => $u["follower_count"] ?? 0
        ];
    }
}

echo json_encode(["following" => $following]);
