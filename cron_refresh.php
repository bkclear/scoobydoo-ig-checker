<?php
error_reporting(0);

$cookiesFile = "cookies.txt";   // keep outside public_html for safety
$dataFile    = "data.json";

$cookies = file_exists($cookiesFile) ? trim(file_get_contents($cookiesFile)) : "";
$csrftoken = "";
if (preg_match('/csrftoken=([^;]+)/', $cookies, $m)) {
    $csrftoken = $m[1];
}

/**
 * Send alerts (optional)
 */
function sendTelegram($msg) {
    $botToken = "YOUR_TELEGRAM_BOT_TOKEN";
    $chatId   = "YOUR_CHAT_ID";
    if (!$botToken || !$chatId) return;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = ["chat_id" => $chatId, "text" => $msg];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

function checkUser($username, $cookies, $csrftoken) {
    if (!preg_match('/^[A-Za-z0-9._]{1,30}$/', $username)) {
        return ["status" => "invalid_username", "followers" => 0, "following" => 0];
    }

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
    } elseif ($httpcode == 401 || $httpcode == 403) {
        return ["status" => "invalid_session", "followers" => 0, "following" => 0];
    }
    return ["status" => "error", "followers" => 0, "following" => 0];
}

// Load current data
$userData = file_exists($dataFile) ? json_decode(file_get_contents($dataFile), true) : [];
if (!is_array($userData) || empty($userData)) {
    die("No usernames to refresh.\n");
}

// Refresh each user
foreach ($userData as $username => $info) {
    $result = checkUser($username, $cookies, $csrftoken);

    $oldFollowers = isset($userData[$username]['followers']) ? (int)$userData[$username]['followers'] : null;

    $userData[$username] = [
        "status" => $result['status'],
        "followers" => $result['followers'],
        "following" => $result['following'],
        "last_checked" => date("Y-m-d H:i:s"),
        "history" => $userData[$username]['history'] ?? []
    ];

    // Keep last 50 history entries max
    $userData[$username]['history'][] = [
        "time" => date("Y-m-d H:i:s"),
        "followers" => $result['followers'],
        "following" => $result['following']
    ];
    if (count($userData[$username]['history']) > 50) {
        $userData[$username]['history'] = array_slice($userData[$username]['history'], -50);
    }

    // Alerts
    if ($result['status'] === "not_found") {
        sendTelegram("⚠️ @$username is not found anymore!");
    }
    if ($oldFollowers !== null && $result['followers'] < $oldFollowers) {
        sendTelegram("📉 @$username lost followers! Old: $oldFollowers → New: ".$result['followers']);
    }

    echo "Refreshed @$username → Followers: ".$result['followers']." | Following: ".$result['following']."\n";
}

// Save JSON
file_put_contents($dataFile, json_encode($userData, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

echo "✅ Done.\n";
