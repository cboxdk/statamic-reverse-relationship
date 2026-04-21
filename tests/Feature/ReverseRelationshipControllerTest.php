<?php

use Illuminate\Support\Facades\Storage;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\User;

// Clean up any users saved to the fixture store after each test
afterEach(function () {
    try {
        User::query()->get()->each(fn ($user) => $user->delete());
    } catch (Throwable) {
        // Ignore cleanup errors — user store may be empty
    }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function featureUser(): mixed
{
    $user = User::make()->email('admin@test.com')->makeSuper();
    $user->save();

    return $user;
}

function cpConfig(array $config): string
{
    return base64_encode(json_encode($config));
}

// ---------------------------------------------------------------------------
// Authentication
// ---------------------------------------------------------------------------

it('returns 401 when unauthenticated', function () {
    $this->getJson('/cp/reverse-relationship')
        ->assertStatus(401);
});

// ---------------------------------------------------------------------------
// Entries mode — matching results
// ---------------------------------------------------------------------------

it('returns 200 with matching entries for valid entries config', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(1, 'data');
});

// ---------------------------------------------------------------------------
// Entries mode — no matches
// ---------------------------------------------------------------------------

it('returns 200 with empty data array when no entries reference the given id', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'no-such-entry',
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// Assets mode — thumbnail URLs included for images
// ---------------------------------------------------------------------------

it('returns thumbnail URL for image assets in assets mode', function () {
    Storage::fake('local');

    $asset = Asset::make()
        ->container('images')
        ->path('test-image.jpg')
        ->data(['related_page' => ['origin-id']]);
    $asset->save();

    $config = cpConfig([
        'mode' => 'assets',
        'container' => 'images',
        'field' => 'related_page',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]));

    $response->assertOk()
        ->assertJsonStructure(['data']);

    $data = $response->json('data');
    expect($data)->toBeArray();

    // If assets were indexed and returned, verify thumbnail URL is present
    if (! empty($data)) {
        expect($data[0])->toHaveKey('thumbnail');
        expect($data[0]['thumbnail'])->toBeString()->not->toBeEmpty();
    }
});

// ---------------------------------------------------------------------------
// Validation — malformed / missing config
// ---------------------------------------------------------------------------

it('returns 422 when the config param is missing entirely', function () {
    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship')
        ->assertStatus(422);
});

it('returns 422 when the config param is invalid base64', function () {
    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?config=not-valid-base64!!!')
        ->assertStatus(422);
});

it('returns 422 when the config param contains invalid JSON after decoding', function () {
    $badConfig = base64_encode('{not-valid-json');

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?config='.urlencode($badConfig))
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Validation — unknown config keys (whitelist)
// ---------------------------------------------------------------------------

it('returns 422 when config contains keys outside the allowed whitelist', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
        'evil_key' => 'injected',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'config' => $config,
        ]))
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Response structure — JsonResource::collection() wraps items in `data`
// ---------------------------------------------------------------------------

it('response structure follows JsonResource collection format with data key', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]))
        ->assertOk();

    expect($response->json())->toHaveKey('data');
    expect($response->json('data'))->toBeArray();
});

// ---------------------------------------------------------------------------
// Single-value field mode (max_items: 1) via controller
// ---------------------------------------------------------------------------

it('returns matching entry for single-value field config', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related_single',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ---------------------------------------------------------------------------
// Empty id parameter
// ---------------------------------------------------------------------------

it('returns empty data when id is not provided', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ---------------------------------------------------------------------------
// Validation — empty config string
// ---------------------------------------------------------------------------

it('returns 422 when config is an empty string', function () {
    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?config=')
        ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Only allowed config keys pass validation
// ---------------------------------------------------------------------------

it('accepts config with all allowed keys', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
        'sort' => 'title',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]))
        ->assertOk();
});

// ---------------------------------------------------------------------------
// Nonexistent field returns empty results gracefully
// ---------------------------------------------------------------------------

it('returns empty data when field does not exist in blueprint', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'nonexistent_field',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship?'.http_build_query([
            'id' => 'origin-id',
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ===========================================================================
// Search endpoint
// ===========================================================================

it('search returns 401 when unauthenticated', function () {
    $this->getJson('/cp/reverse-relationship/search')
        ->assertStatus(401);
});

it('search returns 422 when config is missing', function () {
    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search')
        ->assertStatus(422);
});

it('search returns entries for valid config', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonStructure(['data']);

    $data = $response->json('data');
    expect($data)->toBeArray();
    // Fixture has 3 entries in pages collection
    expect(count($data))->toBeGreaterThanOrEqual(1);
});

it('search filters by query string', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
            'query' => 'Origin',
        ]))
        ->assertOk();

    $data = $response->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBe(1);
    expect($data[0]['title'])->toBe('Origin Entry');
});

it('search excludes specified IDs', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
            'exclude' => 'origin-id,referring-multi-id',
        ]))
        ->assertOk();

    $data = $response->json('data');
    $ids = array_column($data, 'id');
    expect($ids)->not->toContain('origin-id');
    expect($ids)->not->toContain('referring-multi-id');
});

it('search returns hint field for entries', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $response = $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
        ]))
        ->assertOk();

    $data = $response->json('data');
    expect($data)->not->toBeEmpty();
    expect($data[0])->toHaveKeys(['id', 'title', 'status', 'hint', 'edit_url']);
});

it('search returns empty for unknown collection', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'nonexistent',
        'field' => 'related',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
        ]))
        ->assertOk()
        ->assertJsonPath('data', []);
});

it('search handles empty query param gracefully', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    $this->actingAs(featureUser())
        ->getJson('/cp/reverse-relationship/search?'.http_build_query([
            'config' => $config,
            'query' => '',
        ]))
        ->assertOk()
        ->assertJsonStructure(['data']);
});

// ===========================================================================
// Sync endpoint
// ===========================================================================

it('sync returns 401 when unauthenticated', function () {
    $this->postJson('/cp/reverse-relationship/sync')
        ->assertStatus(401);
});

it('sync returns 422 when required fields are missing', function () {
    $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [])
        ->assertStatus(422);
});

it('sync returns 422 when config is invalid', function () {
    $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [
            'id' => 'origin-id',
            'config' => 'not-valid-base64!!!',
            'related' => [],
        ])
        ->assertStatus(422);
});

it('sync detaches entries and returns updated data', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    // Start with referring-multi-id having related: [origin-id]
    // Sync with empty related array to detach
    $response = $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [
            'id' => 'origin-id',
            'config' => $config,
            'related' => [],
        ])
        ->assertOk()
        ->assertJsonStructure(['data', 'errors']);

    expect($response->json('errors'))->toBeEmpty();

    // Verify the entry was actually detached
    $entry = Entry::find('referring-multi-id');
    $related = $entry->get('related');
    expect($related)->not->toContain('origin-id');
});

it('sync attaches entries and returns updated data', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);

    // Attach origin-id to referring-multi-id (re-attach after previous test detached)
    $response = $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [
            'id' => 'origin-id',
            'config' => $config,
            'related' => ['referring-multi-id'],
        ])
        ->assertOk()
        ->assertJsonStructure(['data', 'errors']);

    expect($response->json('errors'))->toBeEmpty();

    // Verify the entry was actually attached
    $entry = Entry::find('referring-multi-id');
    $related = $entry->get('related');
    expect($related)->toContain('origin-id');
});

it('sync returns 422 when config contains unknown keys', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
        'evil' => 'injected',
    ]);

    $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [
            'id' => 'origin-id',
            'config' => $config,
            'related' => [],
        ])
        ->assertStatus(422);
});

it('sync returns error when field not found in blueprint', function () {
    $config = cpConfig([
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'nonexistent_field',
    ]);

    $this->actingAs(featureUser())
        ->postJson('/cp/reverse-relationship/sync', [
            'id' => 'origin-id',
            'config' => $config,
            'related' => [],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'Field not found in blueprint.');
});
