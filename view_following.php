<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load cookies
$cookiesFile = "cookies.txt";
if (!file_exists($cookiesFile)) {
    die("<p style='color:red'>❌ Cookies file missing. Please save Instagram cookies first.</p>");
}
$cookies = trim(file_get_contents($cookiesFile));

function igRequest($url, $cookies) {
    $headers = [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        "Accept: */*",
        "Cookie: $cookies",
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

// Get username
$username = $_GET['username'] ?? '';
if (!$username) {
    die("<p style='color:red'>❌ No username provided.</p>");
}

// Step 1: Get user_id
$profileUrl = "https://www.instagram.com/api/v1/users/web_profile_info/?username=" . urlencode($username);
$res = igRequest($profileUrl, $cookies);
$data = json_decode($res, true);

if (!isset($data['data']['user']['id'])) {
    die("<p style='color:red'>❌ Could not fetch user ID. Check cookies or username.</p>");
}
$userId = $data['data']['user']['id'];

// Step 2: Get following list
$next = null;
$following = [];
$count = 0;

do {
    $url = "https://i.instagram.com/api/v1/friendships/$userId/following/?count=50";
    if ($next) $url .= "&max_id=" . urlencode($next);

    $res = igRequest($url, $cookies);
    $json = json_decode($res, true);

    if (!isset($json['users'])) break;

    foreach ($json['users'] as $u) {
        $followersCount = $u['follower_count'] ?? 0;
        if ($followersCount <= 2000) {
            $following[] = [
                "username" => $u['username'],
                "full_name" => $u['full_name'],
                "profile_pic" => $u['profile_pic_url'],
                "followers" => $followersCount
            ];
        }
        $count++;
    }

    $next = $json['next_max_id'] ?? null;
    usleep(500000); // delay 0.5s to avoid rate limit

} while ($next && $count < 2000);

// Step 3: Show result
if (empty($following)) {
    echo "<p>No following found with ≤2k followers.</p>";
} else {
    foreach ($following as $f) {
        echo "<div class='user-card'>";
        echo "<img src='".htmlspecialchars($f['profile_pic'])."' alt='pic'><br>";
        echo "<a href='https://instagram.com/".htmlspecialchars($f['username'])."' target='_blank'>";
        echo "@".htmlspecialchars($f['username'])."</a><br>";
        echo "<small>".htmlspecialchars($f['full_name'])."</small><br>";
        echo "<small>Followers: ".$f['followers']."</small>";
        echo "</div>";
    }
}
