<?php
$url = 'https://script.google.com/macros/s/AKfycbyo6wiwsHb4V5ylZnNy5xabXtEba7SaOVB93615r7HZoCXggY8WoEfQ-TnKiveJ4Th0/exec';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);
echo "RESPONSE:\n";
var_dump($response);
echo "\nERROR:\n";
var_dump($error);
