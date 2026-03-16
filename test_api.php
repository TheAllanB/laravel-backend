<?php

use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;

$user = App\Models\User::find(1); // allan
Sanctum::actingAs($user, ['*']);

$request = Request::create('/api/organizations/6/requests', 'GET');
$response = app()->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Body: " . $response->getContent() . "\n";
