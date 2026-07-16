<?php

test('migration administration routes require authentication', function () {
    $this->get(route('admin.migrations'))
        ->assertRedirect(route('login'));

    $this->post(route('admin.migrations.run'))
        ->assertRedirect(route('login'));
});
