<?php

it('serves the Vue shell for SPA routes', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('<div id="app"></div>', escape: false);
})->with(['/', '/quizzes', '/play/0b7c4c4e-0000-4000-8000-000000000000']);

it('renders unknown API routes with the shared error envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Resource not found.', 'code' => 'not_found']);
});

it('does not hand API paths to the SPA', function () {
    $this->get('/api/v1/does-not-exist')->assertNotFound();
});
