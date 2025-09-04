<?php
header("Content-Type: application/json");

$username = $_GET["username"] ?? "";
$username = trim($username);

if ($username === "") {
    echo json_encode(["status" => "error", "message" => "No username provided"]);
    exit;
}

// Load cookies
$cookiesFile = "cookies.txt";
if (!file_exists($cookiesFile) || trim(file_get_contents($cookiesFile)) === "") {
    echo json_encode(["status" => "error", "message" => "No cookies set."]);
    exit;
}
$cookies = trim(file_get_contents($cookiesFile));

// Build Instagram URL (web profile API)
$url = "https://www.instagram.com/$username/?__a=1&__d=dis";

// cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: $cookies",
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0 Safari/537.36",
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpcode === 403) {
    echo json_encode(["status" => "invalid_session"]);
    exit;
}
if ($httpcode === 404) {
    echo json_encode(["status" => "not_found"]);
    exit;
}
if ($httpcode !== 200 || !$response) {
    echo json_encode(["status" => "error", "message" => "HTTP $httpcode"]);
    exit;
}

// Parse JSON
$data = json_decode($response, true);
if (!$data) {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
    exit;
}

try {
    // Instagram changes structure often, try multiple paths
    if (isset($data["graphql"]["user"])) {
        $user = $data["graphql"]["user"];
        $followers = $user["edge_followed_by"]["count"] ?? 0;
        $following = $user["edge_follow"]["count"] ?? 0;

        echo json_encode([
            "status" => "exists",
            "followers" => $followers,
            "following" => $following
        ]);
        exit;
    } elseif (isset($data["user"])) {
        // Fallback path
        $user = $data["user"];
        $followers = $user["edge_followed_by"]["count"] ?? 0;
        $following = $user["edge_follow"]["count"] ?? 0;

        echo json_encode([
            "status" => "exists",
            "followers" => $followers,
            "following" => $following
        ]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "User data missing"]);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
    exit;
}
