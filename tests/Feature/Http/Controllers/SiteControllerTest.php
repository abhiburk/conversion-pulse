<?php

use App\Models\Campaign;
use App\Models\Site;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('index', function () {
    test('guests are redirected to the login page', function () {
        $this->get(route('sites.index'))->assertRedirect(route('login'));
    });

    test('only sites from the current workspace are listed', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create(['name' => 'Storefront']);
        Campaign::factory()->for($site)->count(2)->create();
        Site::factory()->create(['name' => 'Someone else\'s site']);

        $response = $this->actingAs($user)->get(route('sites.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('sites/Index')
            ->has('sites', 1)
            ->where('sites.0.name', 'Storefront')
            ->where('sites.0.campaigns_count', 2)
        );
    });
});

describe('store', function () {
    test('a site is added to the current workspace with a normalized domain and a site key', function () {
        $user = User::factory()->withWorkspace()->create();

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'name' => 'Storefront',
            'domain' => ' https://Shop.Example.com/pricing?ref=ad ',
        ]);

        $site = Site::sole();
        $response->assertRedirect(route('sites.edit', $site));
        expect($site->workspace_id)->toBe($user->current_workspace_id);
        expect($site->domain)->toBe('shop.example.com');
        expect($site->site_key)->toStartWith('pk_')->toHaveLength(35);
    });

    test('name and domain are required', function () {
        $user = User::factory()->withWorkspace()->create();

        $response = $this->actingAs($user)->post(route('sites.store'), []);

        $response->assertSessionHasErrors([
            'name' => 'The name field is required.',
            'domain' => 'The domain field is required.',
        ]);
        $this->assertDatabaseCount('sites', 0);
    });

    test('an invalid domain is rejected', function (string $domain) {
        $user = User::factory()->withWorkspace()->create();

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'name' => 'Storefront',
            'domain' => $domain,
        ]);

        $response->assertSessionHasErrors(['domain' => 'Enter a valid domain, like example.com.']);
        $this->assertDatabaseCount('sites', 0);
    })->with([
        'no top-level domain' => 'example',
        'spaces' => 'my shop.com',
        'leading hyphen' => '-shop.example.com',
    ]);

    test('a domain already in the workspace is rejected', function () {
        $user = User::factory()->withWorkspace()->create();
        Site::factory()->for($user->currentWorkspace)->create(['domain' => 'example.com']);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'name' => 'Duplicate',
            'domain' => 'https://example.com',
        ]);

        $response->assertSessionHasErrors(['domain' => 'This domain is already added to your workspace.']);
        $this->assertDatabaseCount('sites', 1);
    });

    test('a domain used by another workspace can still be added', function () {
        $user = User::factory()->withWorkspace()->create();
        Site::factory()->create(['domain' => 'example.com']);

        $response = $this->actingAs($user)->post(route('sites.store'), [
            'name' => 'Storefront',
            'domain' => 'example.com',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('sites', [
            'workspace_id' => $user->current_workspace_id,
            'domain' => 'example.com',
        ]);
    });
});

describe('edit', function () {
    test('the edit page shows the site key', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();

        $response = $this->actingAs($user)->get(route('sites.edit', $site));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('sites/Edit')
            ->where('site.site_key', $site->site_key)
        );
    });

    test('a site from another workspace returns 404', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherSite = Site::factory()->create();

        $this->actingAs($user)->get(route('sites.edit', $otherSite))->assertNotFound();
    });
});

describe('update', function () {
    test('the site is updated while keeping its own domain', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create(['domain' => 'example.com']);

        $response = $this->actingAs($user)->put(route('sites.update', $site), [
            'name' => 'Renamed',
            'domain' => 'example.com',
        ]);

        $response->assertRedirect(route('sites.edit', $site));
        expect($site->fresh()->name)->toBe('Renamed');
    });

    test('a site from another workspace returns 404 and is unchanged', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherSite = Site::factory()->create(['name' => 'Original']);

        $response = $this->actingAs($user)->put(route('sites.update', $otherSite), [
            'name' => 'Hijacked',
            'domain' => 'hijacked.com',
        ]);

        $response->assertNotFound();
        expect($otherSite->fresh()->name)->toBe('Original');
    });
});

describe('destroy', function () {
    test('the site and its campaigns are deleted', function () {
        $user = User::factory()->withWorkspace()->create();
        $site = Site::factory()->for($user->currentWorkspace)->create();
        $campaign = Campaign::factory()->for($site)->create();

        $response = $this->actingAs($user)->delete(route('sites.destroy', $site));

        $response->assertRedirect(route('sites.index'));
        $this->assertModelMissing($site);
        $this->assertModelMissing($campaign);
    });

    test('a site from another workspace returns 404 and is kept', function () {
        $user = User::factory()->withWorkspace()->create();
        $otherSite = Site::factory()->create();

        $response = $this->actingAs($user)->delete(route('sites.destroy', $otherSite));

        $response->assertNotFound();
        $this->assertModelExists($otherSite);
    });
});
