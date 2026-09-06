<?php

it('redirects the root URL to the tenant login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});
