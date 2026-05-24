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
$rolesResponse = Illuminate\Support\Facades\Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/roles/chef_de_projet");

echo "chef_de_projet status: " . $rolesResponse->status() . "\n";
echo "chef_de_projet body: " . $rolesResponse->body() . "\n";

$rolesResponse2 = Illuminate\Support\Facades\Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/roles/developer");
echo "developer status: " . $rolesResponse2->status() . "\n";
echo "developer body: " . $rolesResponse2->body() . "\n";

$usersResponse = Illuminate\Support\Facades\Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/users", [
    'username' => 'aymanejanati@gmail.com',
    'exact' => true
]);
echo "user aymane status: " . $usersResponse->status() . "\n";
if ($usersResponse->successful()) {
    $userId = $usersResponse->json()[0]['id'] ?? null;
    echo "user id: " . $userId . "\n";
    if ($userId) {
        $userRoles = Illuminate\Support\Facades\Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/users/$userId/role-mappings/realm");
        echo "user roles: " . $userRoles->body() . "\n";
    }
}
