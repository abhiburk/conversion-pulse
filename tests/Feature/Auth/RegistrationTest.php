<?php

use App\Actions\CreateWorkspace;
use App\Enums\WorkspaceRole;
use App\Models\User;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration creates an owned workspace and makes it current', function () {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'test@example.com')->sole();
    expect($user->workspaces)->toHaveCount(1);
    expect($user->current_workspace_id)->toBe($user->workspaces->first()->id);
    expect($user->workspaces->first()->name)->toBe("Test User's Workspace");
    expect($user->workspaces->first()->pivot->role)->toBe(WorkspaceRole::Owner->value);
});

test('registration does not create the user when workspace creation fails', function () {
    $this->mock(CreateWorkspace::class)
        ->shouldReceive('handle')
        ->andThrow(new RuntimeException('Workspace creation failed.'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(RuntimeException::class, 'Workspace creation failed.');

    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    $this->assertDatabaseCount('workspaces', 0);
});
