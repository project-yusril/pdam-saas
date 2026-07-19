<?php

namespace Tests\Feature;

use App\Models\PdamOrganization;
use App\Models\PlatformAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_login_uses_session_and_does_not_expose_a_token(): void
    {
        [$org, $user] = $this->fixture();

        $response = $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/login', [
                'pdam_code' => $org->code,
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('user.id', $user->id)
            ->assertCookie(config('session.cookie'));
        $sessionCookie = collect($response->baseResponse->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertTrue($sessionCookie->isHttpOnly());
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->getJson('/api/v1/session')
            ->assertOk()
            ->assertJsonPath('type', 'tenant')
            ->assertJsonPath('user.id', $user->id);

        $this->postJson('/api/v1/refresh-token')->assertForbidden();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_platform_web_login_uses_its_session_guard_without_exposing_a_token(): void
    {
        $admin = PlatformAdmin::create([
            'full_name' => 'Platform Admin',
            'email' => 'platform@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);

        $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/platform/login', [
                'email' => $admin->email,
                'password' => 'password',
            ])->assertOk()
            ->assertJsonMissingPath('token')
            ->assertJsonPath('admin.id', $admin->id);

        $this->assertAuthenticatedAs($admin, 'platform');
        $this->getJson('/api/v1/session')
            ->assertOk()
            ->assertJsonPath('type', 'platform')
            ->assertJsonPath('admin.id', $admin->id);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_mobile_login_explicitly_receives_a_working_bearer_token(): void
    {
        [$org, $user] = $this->fixture();

        $response = $this->postJson('/api/v1/login', [
            'pdam_code' => $org->code,
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'contract-test-mobile',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user', 'organization']);
        $token = $response->json('token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }

    private function fixture(): array
    {
        $org = PdamOrganization::create([
            'code' => 'auth-'.uniqid(),
            'name' => 'PDAM Auth Test',
            'subscription_status' => 'active',
        ]);
        $user = User::create([
            'pdam_org_id' => $org->id,
            'name' => 'Auth User',
            'email' => uniqid().'@auth.test',
            'password' => 'password',
            'is_tenant_admin' => true,
            'is_active' => true,
        ]);

        return [$org, $user];
    }
}
