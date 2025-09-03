<?php
error_reporting(0);

// Load cookies
$cookies = file_exists("cookies.txt") ? trim(file_get_contents("cookies.txt")) : "";
preg_match('/csrftoken=([^;]+)/', $cookies, $match);
$csrftoken = $match[1] ?? "";

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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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

$username = $_GET['username'] ?? "";
if ($username) {
    echo json_encode(checkUser($username, $cookies, $csrftoken));
}