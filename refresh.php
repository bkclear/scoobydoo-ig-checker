<?php
error_reporting(0);
$cookiesFile="cookies.txt";$dataFile="data.json";
$cookies=file_exists($cookiesFile)?trim(file_get_contents($cookiesFile)):"";$csrftoken="";
if(preg_match('/csrftoken=([^;]+)/',$cookies,$m)){$csrftoken=$m[1];}
function checkUser($username,$cookies,$csrftoken){
    $url="https://www.instagram.com/api/v1/users/web_profile_info/?username=".urlencode($username);
    $headers=["User-Agent: Mozilla/5.0","Accept: application/json","Referer: https://www.instagram.com/","X-CSRFToken: $csrftoken","X-IG-App-ID: 936619743392459","Cookie: $cookies"];
    $ch=curl_init();curl_setopt($ch,CURLOPT_URL,$url);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);curl_setopt($ch,CURLOPT_TIMEOUT,10);
    $response=curl_exec($ch);$httpcode=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($httpcode==200){$data=json_decode($response,true);if(isset($data['data']['user'])){$u=$data['data']['user'];return["status"=>"exists","followers"=>$u['edge_followed_by']['count']??0,"following"=>$u['edge_follow']['count']??0];}return["status"=>"not_found","followers"=>0,"following"=>0];}
    elseif($httpcode==401||$httpcode==403){return["status"=>"invalid_session","followers"=>0,"following"=>0];}
    return["status"=>"error","followers"=>0,"following"=>0];
}
$username=trim($_GET['username']??"");
if($username){
    header("Content-Type: application/json");
    $result=checkUser($username,$cookies,$csrftoken);
    $result["last_checked"]=date("Y-m-d H:i:s");
    $data=file_exists($dataFile)?json_decode(file_get_contents($dataFile),true):[];
    if(!is_array($data))$data=[];
    if(!isset($data[$username]))$data[$username]=["history"=>[]];
    $data[$username]=array_merge($data[$username],$result);
    $data[$username]["history"][]=["time"=>$result["last_checked"],"followers"=>$result["followers"],"following"=>$result["following"]];
    file_put_contents($dataFile,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    echo json_encode($result,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
}
