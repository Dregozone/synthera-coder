<?php

test('returns a successful response', function (): void {
    $response = $this->get(route('home'));

    $response->assertRedirect();

    expect($response->headers->get('Location'))->toContain('sessionId=');
});
