<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiDocumentationTest extends TestCase
{
    public function test_openapi_ui_is_accessible(): void
    {
        $response = $this->get('/docs/api');

        $response->assertStatus(200);
        $response->assertSee('Kirana People API Documentation');
    }

    public function test_openapi_json_specification_is_accessible(): void
    {
        $response = $this->get('/docs/api.json');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'openapi',
            'info' => [
                'title',
                'version',
                'description',
            ],
            'paths',
        ]);
        $response->assertJsonPath('info.title', 'Kirana People API Documentation');
    }
}
