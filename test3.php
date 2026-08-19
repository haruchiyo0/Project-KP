<?php
require_once __DIR__ . '/config.php';
$stmt = $db->query("SELECT * FROM users");
$users = $stmt->fetchAll();
print_r($users);
