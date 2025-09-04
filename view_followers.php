<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Content-Type: application/json");

// =============================
// 📌 Load cookies
// =============================
$cookiesFile = "cookies.txt";
if (!file_exists($cookiesFile)) {
    echo json_encode(["error" => "No cookies saved"]);
    exit;
}
$cookies = trim(file_get_contents($cookiesFile));

// =============================
// 📌 Get target username
// =============================
$username = $_GET['username'] ?? '';
if (!$username) {
    echo json_encode(["error" => "No username provided"]);
    exit;
}

// =============================
// 📌 Helper function to request
// =============================
function igRequest($url, $cookies) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "User-Agent: Instagram 155.0.0.37.107",
        "Cookie: $cookies",
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// =============================
// 📌 Step 1: Get user_id from username
// =============================
$profileUrl = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);
$profileRes = igRequest($profileUrl, $cookies);
$profileData = json_decode($profileRes, true);

if (!$profileData || !isset($profileData['data']['user']['id'])) {
    echo json_encode(["error" => "Could not fetch user_id for @$username"]);
    exit;
}
$userId = $profileData['data']['user']['id'];

// =============================
// 📌 Step 2: Get followers with pagination
// =============================
$followers = [];
$maxId = null;
$limit = 2000; // max followers to fetch
$perPage = 50; // fetch 50 per page

while (count($followers) < $limit) {
    $url = "https://i.instagram.com/api/v1/friendships/$userId/followers/?count=$perPage";
    if ($maxId) {
        $url .= "&max_id=" . urlencode($maxId);
    }

    $res = igRequest($url, $cookies);
    $data = json_decode($res, true);

    if (!$data || !isset($data['users'])) {
        break; // stop if Instagram returns empty
    }

    foreach ($data['users'] as $u) {
        $followers[] = [
            "username" => $u['username'],
            "full_name" => $u['full_name'],
            "profile_pic" => $u['profile_pic_url'],
            "follower_count" => $u['follower_count'] ?? 0
        ];
        if (count($followers) >= $limit) break;
    }

    if (isset($data['next_max_id'])) {
        $maxId = $data['next_max_id'];
        sleep(2); // ⏳ delay 2 sec to avoid rate limit
    } else {
        break;
    }
}

// =============================
// 📌 Step 3: Return JSON
// =============================
echo json_encode([
    "target" => $username,
    "count" => count($followers),
    "followers" => $followers
], JSON_PRETTY_PRINT);
