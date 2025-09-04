<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$username = $_GET['username'] ?? "";

if ($username) {
    // Dummy API response (replace with real Instagram request if you want)
    $result = [
        "status" => "exists",
        "followers" => rand(100, 5000),
        "following" => rand(50, 1000)
    ];
    header("Content-Type: application/json");
    echo json_encode($result);
}
