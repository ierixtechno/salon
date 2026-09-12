<?php

it('shows the welcome/splash screen at the root URL for a guest', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('Log In', false);
});

it('redirects an already-authenticated tenant user from the root URL to their dashboard', function () {
    $owner = onboard();

    $response = $this->actingAs($owner, 'web')->get('/');

    $response->assertRedirect(route('dashboard'));
});
