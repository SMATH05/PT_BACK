<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$baseUrl = rtrim(env('KEYCLOAK_BASE_URL', 'http://localhost:8088'), '/');
$realm = env('KEYCLOAK_REALM', 'PT');

$tokenResponse = Illuminate\Support\Facades\Http::asForm()->post("$baseUrl/realms/$realm/protocol/openid-connect/token", [
    'grant_type' => 'client_credentials',
    'client_id' => env('KEYCLOAK_ADMIN_CLIENT_ID'),
    'client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET'),
]);
$adminToken = $tokenResponse->json('access_token');
$userId = '82f845bd-617b-40a1-a5d5-1ca8f4d1ff8f';
$rolesResponse = Illuminate\Support\Facades\Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/users/$userId/role-mappings/realm/available");
echo $rolesResponse->status() . "\n";
echo $rolesResponse->body() . "\n";
