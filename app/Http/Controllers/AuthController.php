<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\Developer;
use App\Models\ChefDeProjet;
use App\Models\Manager;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'firstName' => 'required|string',
            'lastName'  => 'required|string',
            'email'     => 'required|email',
            'password'  => 'required|string|min:4',
            'role'      => 'nullable|string|in:developer,manager,chef_de_projet',
        ]);

        $baseUrl = rtrim(env('KEYCLOAK_BASE_URL', 'http://localhost:8088'), '/');
        $realm = env('KEYCLOAK_REALM', 'PT');

        // 1. Get Admin Token
        $tokenResponse = Http::asForm()->post("$baseUrl/realms/$realm/protocol/openid-connect/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => env('KEYCLOAK_ADMIN_CLIENT_ID'),
            'client_secret' => env('KEYCLOAK_ADMIN_CLIENT_SECRET'),
        ]);

        if ($tokenResponse->failed()) {
            return response()->json([
                'error' => 'Failed to obtain admin token from Keycloak',
                'details' => $tokenResponse->json()
            ], 500);
        }

        $adminToken = $tokenResponse->json('access_token');

        // 2. Create User
        $createUserResponse = Http::withToken($adminToken)
            ->post("$baseUrl/admin/realms/$realm/users", [
                'username'  => $request->email, // Using email as username
                'email'     => $request->email,
                'firstName' => $request->firstName,
                'lastName'  => $request->lastName,
                'enabled'   => true,
                'credentials' => [
                    [
                        'type'      => 'password',
                        'value'     => $request->password,
                        'temporary' => false,
                    ]
                ]
            ]);

        if ($createUserResponse->failed()) {
            $status = $createUserResponse->status();
            if ($status === 409) {
                return response()->json(['error' => 'User with this email already exists'], 409);
            }
            return response()->json([
                'error' => 'Failed to create user in Keycloak',
                'details' => $createUserResponse->json()
            ], $status);
        }

        // 3. Assign Role if provided
        $roleName = $request->input('role', 'developer');
        $name = trim($request->firstName . ' ' . $request->lastName);

        // Fallback: create the actor in the local database so AuthenticateWithKeycloak infers the role automatically
        $manager = Manager::first();
        $managerId = $manager ? $manager->id : 1;

        if ($roleName === 'developer') {
            Developer::firstOrCreate(
                ['email' => $request->email], 
                ['name' => $name, 'manager_id' => $managerId]
            );
        } elseif ($roleName === 'chef_de_projet') {
            ChefDeProjet::firstOrCreate(
                ['email' => $request->email], 
                ['name' => $name, 'manager_id' => $managerId]
            );
        } elseif ($roleName === 'manager') {
            Manager::firstOrCreate(['email' => $request->email], ['name' => $name]);
        }

        
        $usersResponse = Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/users", [
            'username' => $request->email,
            'exact' => true
        ]);
        
        $userId = $usersResponse->json()[0]['id'] ?? null;

        if ($userId) {
            $roleResponse = Http::withToken($adminToken)->get("$baseUrl/admin/realms/$realm/roles/$roleName");
            if ($roleResponse->successful()) {
                $roleData = $roleResponse->json();
                
                Http::withToken($adminToken)
                    ->post("$baseUrl/admin/realms/$realm/users/$userId/role-mappings/realm", [
                        [
                            'id' => $roleData['id'],
                            'name' => $roleData['name']
                        ]
                    ]);
            }
        }

        return response()->json(['message' => 'User registered successfully'], 201);
    }
}
