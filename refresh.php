<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = $_GET['username'] ?? "";

if ($username) {
    // Dummy API Response (replace with real IG API if you have cookies/session)
    $result = [
        "status" => "exists",
        "followers" => rand(100, 5000),
        "following" => rand(50, 1000)
    ];

    header("Content-Type: application/json");
    echo json_encode($result);
}
