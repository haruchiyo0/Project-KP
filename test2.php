<?php
require_once __DIR__ . '/config.php';

$username = 'fikri';
$password = 'teknisi123';

echo "Testing Sync Logic for: $username\n";

$statement = $db->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
$statement->execute([$username]);
$user = $statement->fetch();

if (!$user) {
    echo "User not found locally, trying curl...\n";
    if (defined('GOOGLE_SHEETS_URL') && !empty(GOOGLE_SHEETS_URL)) {
        try {
            $ch = curl_init(GOOGLE_SHEETS_URL);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            
            if (!$response) {
                echo "CURL ERROR: " . curl_error($ch) . "\n";
            } else {
                echo "CURL SUCCESS\n";
            }
            
            curl_close($ch);
            
            if ($response) {
                $sheetData = json_decode($response, true);
                if (isset($sheetData['status']) && $sheetData['status'] === 'success' && isset($sheetData['data'])) {
                    foreach ($sheetData['data'] as $sheetUser) {
                        if ($sheetUser['username'] === $username) {
                            echo "Found user in Sheets API: {$sheetUser['username']}\n";
                            if ($user) {
                                echo "Updating existing user...\n";
                            } else {
                                echo "Inserting new user...\n";
                                $insert = $db->prepare('INSERT INTO users (name, nik, username, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)');
                                $insert->execute([
                                    $sheetUser['name'], 
                                    $sheetUser['nik'], 
                                    $sheetUser['username'], 
                                    password_hash($sheetUser['password'], PASSWORD_DEFAULT), 
                                    $sheetUser['role'] === 'admin' ? 'admin' : 'teknisi', 
                                    'active'
                                ]);
                                echo "Inserted successfully!\n";
                            }
                            
                            $statement->execute([$username]);
                            $user = $statement->fetch();
                            break;
                        }
                    }
                } else {
                    echo "Invalid JSON response or status != success\n";
                }
            }
        } catch (\Throwable $t) {
            echo "Throwable Caught: " . $t->getMessage() . "\n";
        }
    } else {
        echo "GOOGLE_SHEETS_URL not defined\n";
    }
} else {
    echo "User already exists locally.\n";
}

if ($user && password_verify($password, $user['password_hash'])) {
    echo "LOGIN SUCCESS!\n";
} else {
    echo "LOGIN FAILED!\n";
    if ($user) {
        echo "Password verification failed. DB Hash: " . $user['password_hash'] . "\n";
    }
}
