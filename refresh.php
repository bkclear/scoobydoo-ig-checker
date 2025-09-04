<?php
// Returns JSON: { status: "exists"|"not_found"|"invalid_session"|"error", followers, following, message?, raw? }
header('Content-Type: application/json');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($username === '') {
    echo json_encode(['status'=>'error','message'=>'No username provided']);
    exit;
}

$cookiesFile = 'cookies.txt';
if (!file_exists($cookiesFile) || trim(file_get_contents($cookiesFile)) === '') {
    echo json_encode(['status'=>'error','message'=>'No cookies set']);
    exit;
}
$cookies = trim(file_get_contents($cookiesFile));

// Use the web_profile_info endpoint (more stable lately)
$url = 'https://www.instagram.com/api/v1/users/web_profile_info/?username=' . urlencode($username);

$headers = [
    "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
    "Accept: application/json",
    "Referer: https://www.instagram.com/",
    "X-IG-App-ID: 936619743392459",
    "Cookie: $cookies"
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HEADER => false,
]);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Common failure codes
if ($httpcode === 401 || $httpcode === 403) {
    echo json_encode(['status'=>'invalid_session','followers'=>0,'following'=>0,'message'=>'Cookies expired or blocked']);
    exit;
}
if ($httpcode === 404) {
    echo json_encode(['status'=>'not_found','followers'=>0,'following'=>0]);
    exit;
}
if ($httpcode !== 200 || !$response) {
    echo json_encode(['status'=>'error','followers'=>0,'following'=>0,'message'=>"Unexpected HTTP code $httpcode",'raw'=>substr((string)$response,0,200)]);
    exit;
}

// Parse JSON safely
$data = json_decode($response, true);
if (!is_array($data)) {
    echo json_encode(['status'=>'error','followers'=>0,'following'=>0,'message'=>'Invalid JSON','raw'=>substr((string)$response,0,200)]);
    exit;
}

if (isset($data['data']['user'])) {
    $u = $data['data']['user'];
    $followers = $u['edge_followed_by']['count'] ?? 0;
    $following = $u['edge_follow']['count'] ?? 0;
    echo json_encode(['status'=>'exists','followers'=>$followers,'following'=>$following]);
    exit;
}

// Fallback—Instagram changed shape
echo json_encode(['status'=>'error','followers'=>0,'following'=>0,'message'=>'User data missing','raw'=>substr((string)$response,0,200)]);
