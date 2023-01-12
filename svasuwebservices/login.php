<?php
$url = 'https://localhost/moodle38/login/token.php?service=moodle_api';
$username = 'admin';
$password = 'Admin@123';
$fields = (object)array('username' => $username, 'password' => $password);

require_once('./curl.php');

$curl = new curl;
$resp = $curl->post($url, $fields);
$resp = json_decode($resp);
var_dump($resp);
?>