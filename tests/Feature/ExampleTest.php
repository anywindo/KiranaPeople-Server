<?php

it('returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('returns dynamic application version in root api endpoint', function () {
    config(['app.version' => '1.0.1-custom']);

    $response = $this->getJson('/api');

    $response->assertOk()
        ->assertJson([
            'name' => config('app.name'),
            'version' => '1.0.1-custom',
            'status' => 'healthy',
        ]);
});
