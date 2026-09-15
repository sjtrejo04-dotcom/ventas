<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('filament.admin.auth.login'));
});
