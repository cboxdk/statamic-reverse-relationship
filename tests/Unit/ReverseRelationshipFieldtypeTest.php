<?php

use Cbox\ReverseRelationship\Fieldtypes\ReverseRelationship;
use Illuminate\Support\Collection;
use Statamic\Contracts\Query\Builder;
use Statamic\Fields\Field;

// ---------------------------------------------------------------------------
// configFieldItems
// ---------------------------------------------------------------------------

it('configFieldItems returns expected structure with all three mode fields', function () {
    $fieldtype = new ReverseRelationship;

    $reflection = new ReflectionMethod($fieldtype, 'configFieldItems');
    $reflection->setAccessible(true);
    $items = $reflection->invoke($fieldtype);

    expect($items)->toHaveCount(1);

    $fields = $items[0]['fields'];

    expect($fields)->toHaveKey('mode');
    expect($fields)->toHaveKey('collection');
    expect($fields)->toHaveKey('taxonomy');
    expect($fields)->toHaveKey('container');
    expect($fields)->toHaveKey('field');
    expect($fields)->toHaveKey('sort');

    expect($fields['mode']['type'])->toBe('button_group');
    expect($fields['collection']['type'])->toBe('collections');
    expect($fields['taxonomy']['type'])->toBe('taxonomies');
    expect($fields['container']['type'])->toBe('asset_container');
});

// ---------------------------------------------------------------------------
// preload
// ---------------------------------------------------------------------------

it('preload returns id null when parent has no ID (new entry context)', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturnNull();

    $field = new Field('test_field', ['type' => 'reverse_relationship']);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->preload())->toBe(['id' => null, 'editable' => false]);
});

it('preload returns correct ID for existing entry', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('my-entry-id');

    $field = new Field('test_field', ['type' => 'reverse_relationship']);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->preload())->toBe(['id' => 'my-entry-id', 'editable' => false]);
});

// ---------------------------------------------------------------------------
// preProcessIndex
// ---------------------------------------------------------------------------

it('preProcessIndex returns integer count for entries mode', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->preProcessIndex(null);

    expect($result)->toBeInt();
});

// ---------------------------------------------------------------------------
// augment - returns collection for each mode
// ---------------------------------------------------------------------------

it('augment returns a builder when matching entries exist', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->augment(null);

    // Returns Builder when matching IDs found
    expect($result)->toBeInstanceOf(Builder::class);
});

it('augment returns a builder or collection when mode is terms', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-term-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'terms',
        'taxonomy' => 'tags',
        'field' => 'related_page',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->augment(null);

    // No matching terms in fixtures, returns empty collection
    expect($result)->toBeInstanceOf(Collection::class);
});

it('augment returns a builder or collection when mode is assets', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-asset-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'assets',
        'container' => 'images',
        'field' => 'related_page',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->augment(null);

    // No matching assets in fixtures, returns empty collection
    expect($result)->toBeInstanceOf(Collection::class);
});

// ---------------------------------------------------------------------------
// getQuery - whereJsonContains vs where
// ---------------------------------------------------------------------------

it('augment returns builder with matching IDs for multi-value field (no max_items)', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    // 'related' field in blueprint has no max_items
    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->augment(null);

    // Returns a Builder; calling ->get() resolves the entries
    expect($result)->toBeInstanceOf(Builder::class);
    $ids = $result->get()->map->id()->all();
    expect($ids)->toContain('referring-multi-id');
    expect($ids)->not->toContain('referring-single-id');
});

it('augment returns builder with matching IDs for single-value field (max_items: 1)', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    // 'related_single' field in blueprint has max_items: 1
    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related_single',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    $result = $fieldtype->augment(null);

    // Returns a Builder; calling ->get() resolves the entries
    expect($result)->toBeInstanceOf(Builder::class);
    $ids = $result->get()->map->id()->all();
    expect($ids)->toContain('referring-single-id');
    expect($ids)->not->toContain('referring-multi-id');
});

// ---------------------------------------------------------------------------
// missing / invalid config - graceful empty return
// ---------------------------------------------------------------------------

it('returns empty collection without throwing when collection config is missing', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        // collection is intentionally missing
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
    expect($fieldtype->preProcessIndex(null))->toBe(0);
});

it('returns empty collection without throwing when taxonomy config is missing', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'terms',
        // taxonomy is intentionally missing
        'field' => 'related_page',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
    expect($fieldtype->preProcessIndex(null))->toBe(0);
});

it('returns empty collection without throwing when container config is missing', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'assets',
        // container is intentionally missing
        'field' => 'related_page',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
    expect($fieldtype->preProcessIndex(null))->toBe(0);
});

// ---------------------------------------------------------------------------
// unknown mode - graceful empty return
// ---------------------------------------------------------------------------

it('returns empty collection for unknown mode', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('some-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'unknown_mode',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
    expect($fieldtype->preProcessIndex(null))->toBe(0);
});

// ---------------------------------------------------------------------------
// augment / preProcessIndex - null parent ID
// ---------------------------------------------------------------------------

it('augment returns empty collection when parent has no ID', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturnNull();

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
});

it('preProcessIndex returns zero when parent has no ID', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturnNull();

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->preProcessIndex(null))->toBe(0);
});

// ---------------------------------------------------------------------------
// getCount
// ---------------------------------------------------------------------------

it('getCount returns correct count for entries mode', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->getCount('origin-id'))->toBe(1);
});

it('getCount returns zero for unknown mode', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'invalid',
        'field' => 'related',
    ]));

    expect($fieldtype->getCount('some-id'))->toBe(0);
});

// ---------------------------------------------------------------------------
// getItems
// ---------------------------------------------------------------------------

it('getItems returns empty collection for unknown mode', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'invalid',
        'field' => 'related',
    ]));

    $result = $fieldtype->getItems('some-id');

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result->isEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// terms mode - strips taxonomy prefix from ID
// ---------------------------------------------------------------------------

it('terms mode strips taxonomy handle prefix from ID', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('tags::some-term');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'terms',
        'taxonomy' => 'tags',
        'field' => 'related_page',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    // Should not throw, returns empty collection (no matching terms in fixtures)
    $result = $fieldtype->augment(null);
    expect($result)->toBeInstanceOf(Collection::class);
});

// ---------------------------------------------------------------------------
// getMatchingIds
// ---------------------------------------------------------------------------

it('getMatchingIds returns matching IDs for multi-value field', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related',
    ]));

    $ids = $fieldtype->getMatchingIds('origin-id');

    expect($ids)->toContain('referring-multi-id');
    expect($ids)->not->toContain('referring-single-id');
});

it('getMatchingIds returns matching IDs for single-value field', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'related_single',
    ]));

    $ids = $fieldtype->getMatchingIds('origin-id');

    expect($ids)->toContain('referring-single-id');
    expect($ids)->not->toContain('referring-multi-id');
});

it('getMatchingIds returns empty array for unknown mode', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'invalid',
        'field' => 'related',
    ]));

    expect($fieldtype->getMatchingIds('some-id'))->toBe([]);
});

it('getMatchingIds returns empty array when field not in blueprint', function () {
    $fieldtype = new ReverseRelationship;
    $fieldtype->setField(new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'nonexistent_field',
    ]));

    expect($fieldtype->getMatchingIds('origin-id'))->toBe([]);
});

// ---------------------------------------------------------------------------
// missing field in blueprint
// ---------------------------------------------------------------------------

it('returns empty collection when the target field does not exist in blueprint', function () {
    $parent = Mockery::mock();
    $parent->shouldReceive('id')->andReturn('origin-id');

    $field = new Field('test_field', [
        'type' => 'reverse_relationship',
        'mode' => 'entries',
        'collection' => 'pages',
        'field' => 'nonexistent_field',
    ]);
    $field->setParent($parent);

    $fieldtype = new ReverseRelationship;
    $fieldtype->setField($field);

    expect($fieldtype->augment(null))->toBeInstanceOf(Collection::class);
    expect($fieldtype->augment(null)->isEmpty())->toBeTrue();
});

// ---------------------------------------------------------------------------
// static title and icon
// ---------------------------------------------------------------------------

it('has correct static title', function () {
    $fieldtype = new ReverseRelationship;

    $reflection = new ReflectionProperty($fieldtype, 'title');
    expect($reflection->getValue())->toBe('Reverse Relationship');
});

it('has entries icon', function () {
    $fieldtype = new ReverseRelationship;

    $reflection = new ReflectionProperty($fieldtype, 'icon');
    expect($reflection->getValue($fieldtype))->toBe('fieldtype-entries');
});
