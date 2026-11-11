<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_csrf_token_endpoint_returns_token_and_auth_status()
    {
        $response = $this->get('/csrf-token');

        $response->assertStatus(200);
        $response->assertJsonStructure(['token', 'authenticated']);
        $this->assertFalse($response->json('authenticated'));

        $location = Location::create([
            'name' => 'HQ',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'radius' => 100,
        ]);
        $user = User::factory()->create([
            'location_id' => $location->id,
        ]);

        $authResponse = $this->actingAs($user)->get('/csrf-token');

        $authResponse->assertStatus(200);
        $this->assertTrue($authResponse->json('authenticated'));
        $this->assertNotEmpty($authResponse->json('token'));
    }

    public function test_token_mismatch_exception_redirects_to_login_with_flash_error()
    {
        $handler = app()->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request = \Illuminate\Http\Request::create('/attendance', 'POST');
        $response = $handler->render($request, new TokenMismatchException);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(route('login'), $response->headers->get('Location'));
    }

    public function test_http_419_exception_redirects_to_login_with_flash_error()
    {
        $handler = app()->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request = \Illuminate\Http\Request::create('/attendance', 'POST');
        $response = $handler->render($request, new HttpException(419, 'Page Expired'));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(route('login'), $response->headers->get('Location'));
    }

    public function test_json_request_receives_419_json()
    {
        $handler = app()->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $request = \Illuminate\Http\Request::create('/attendance', 'POST');
        $request->headers->set('Accept', 'application/json');
        $response = $handler->render($request, new TokenMismatchException);

        $this->assertEquals(419, $response->getStatusCode());
        $this->assertStringContainsString('Sesi telah kedaluwarsa', $response->getContent());
    }
}
