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
$createRoleResponse = Illuminate\Support\Facades\Http::withToken($adminToken)->post("$baseUrl/admin/realms/$realm/roles", [
    'name' => 'chef_de_projet',
    'description' => 'Chef de projet'
]);
echo "create role status: " . $createRoleResponse->status() . "\n";
echo "create role body: " . $createRoleResponse->body() . "\n";
