<?php
error_reporting(0);

$cookies = file_exists("cookies.txt") ? trim(file_get_contents("cookies.txt")) : "";
$username = $_GET['username'] ?? "";

if (!$username || !$cookies) {
    echo json_encode([]);
    exit;
}

// Step 1: Get user ID
$url = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);
$headers = [
    "User-Agent: Mozilla/5.0",
    "Accept: application/json",
    "Cookie: $cookies"
];
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
curl_close($ch);
$data = json_decode($res, true);

if (!isset($data['data']['user']['id'])) {
    echo json_encode([]);
    exit;
}
$userId = $data['data']['user']['id'];

// Step 2: Get followers list
function getFollowersList($userId, $cookies, $limit = 2000) {
    $followers = [];
    $maxId = "";
    $headers = [
        "User-Agent: Instagram 155.0.0.37.107 (iPhone; CPU iPhone OS 12_4 like Mac OS X)",
        "Cookie: $cookies"
    ];

    while (count($followers) < $limit) {
        $url = "https://i.instagram.com/api/v1/friendships/$userId/followers/?count=50" . 
               ($maxId ? "&max_id=$maxId" : "");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($res, true);
        if (!isset($data['users'])) break;

        foreach ($data['users'] as $u) {
            $followers[] = [
                "username" => $u['username'],
                "pic" => $u['profile_pic_url']
            ];
            if (count($followers) >= $limit) break;
        }

        if (isset($data['next_max_id'])) {
            $maxId = $data['next_max_id'];
        } else {
            break;
        }
    }

    return $followers;
}

echo json_encode(getFollowersList($userId, $cookies, 2000));
