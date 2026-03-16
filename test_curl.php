<?php

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/login');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => 'allan@mail.com', 'password' => 'password']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['token'] ?? null;
echo "Token: " . substr((string)$token, 0, 10) . "...\n";

if ($token) {
    $ch2 = curl_init();
    curl_setopt($ch2, CURLOPT_URL, 'http://localhost:8000/api/organizations/6/requests');
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $token,
        'Accept: application/json'
    ]);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    $res2 = curl_exec($ch2);
    $status = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    echo "Status: $status\n";
    echo "Body: $res2\n";
}
