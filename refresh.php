<?php
error_reporting(0);

$cookiesFile = "cookies.txt";
$dataFile    = "data.json";

$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
$csrftoken = "";
if (preg_match('/csrftoken=([^;]+)/', $cookies, $m)) {
    $csrftoken = $m[1];
}

function checkUser($username, $cookies, $csrftoken) {
    $url = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);

    $headers = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        "Accept: application/json",
        "Referer: https://www.instagram.com/",
        "X-CSRFToken: $csrftoken",
        "X-IG-App-ID: 936619743392459",
        "Cookie: $cookies"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8); // faster timeout
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpcode == 200) {
        $data = json_decode($response, true);
        if (isset($data['data']['user'])) {
            $user = $data['data']['user'];
            return [
                "status" => "exists",
                "followers" => $user['edge_followed_by']['count'] ?? 0,
                "following" => $user['edge_follow']['count'] ?? 0
            ];
        }
        return ["status" => "not_found", "followers" => 0, "following" => 0];
    }
    elseif ($httpcode == 401 || $httpcode == 403) {
        return ["status" => "invalid_session", "followers" => 0, "following" => 0];
    }
    return ["status" => "error", "followers" => 0, "following" => 0];
}

if (!isset($_GET['username']) || empty($_GET['username'])) {
    echo json_encode(["status"=>"error","message"=>"No username"]);
    exit;
}

$username = trim($_GET['username']);
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($userData)) $userData = [];

$result = checkUser($username, $cookies, $csrftoken);

$userData[$username] = [
    "status" => $result['status'],
    "followers" => $result['followers'],
    "following" => $result['following'],
    "last_checked" => date("Y-m-d H:i:s"),
    "history" => $userData[$username]['history'] ?? []
];

$userData[$username]['history'][] = [
    "time" => date("Y-m-d H:i:s"),
    "followers" => $result['followers'],
    "following" => $result['following']
];

// save back
file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo json_encode([
    "username" => $username,
    "status" => $result['status'],
    "followers" => $result['followers'],
    "following" => $result['following'],
    "last_checked" => date("Y-m-d H:i:s")
]);
