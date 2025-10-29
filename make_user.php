<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/config_mysqli.php';


$email = '67160224@go.buu.ac.th';
$name  = 'praweenut';
$plain = '14282548eiei'; // เปลี่ยนตามต้องการ

$hash = password_hash($plain, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('INSERT INTO users (email, display_name, password_hash) VALUES (?, ?, ?)');
$stmt->bind_param('sss', $email, $name, $hash);
$stmt->execute();
$stmt->close();

echo "Created user: $email with password: $plain\n";
