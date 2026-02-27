<?php

use Cbox\ReverseRelationship\Tags\ReverseRelationship;
use Statamic\Facades\Antlers;

// ---------------------------------------------------------------------------
// index - basic loop
// ---------------------------------------------------------------------------

it('renders matching entries in a basic loop', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" id="origin-id" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect($result)->toContain('Referring Multi');
});

it('renders matching entries for single-value field', function () {
    $template = '{{ reverse_relationship collection="pages" field="related_single" id="origin-id" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect($result)->toContain('Referring Single');
    expect($result)->not->toContain('Referring Multi');
});

it('renders empty when no matches found', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" id="nonexistent-id" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('');
});

// ---------------------------------------------------------------------------
// count
// ---------------------------------------------------------------------------

it('returns count of matching entries', function () {
    $template = '{{ reverse_relationship:count collection="pages" field="related" id="origin-id" }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('1');
});

it('returns zero count when no matches', function () {
    $template = '{{ reverse_relationship:count collection="pages" field="related" id="nonexistent-id" }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('0');
});

// ---------------------------------------------------------------------------
// context ID fallback
// ---------------------------------------------------------------------------

it('falls back to context ID when id param is not provided', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template, ['id' => 'origin-id']);

    expect($result)->toContain('Referring Multi');
});

// ---------------------------------------------------------------------------
// missing / invalid params
// ---------------------------------------------------------------------------

it('returns empty when collection param is missing', function () {
    $template = '{{ reverse_relationship field="related" id="origin-id" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('');
});

it('returns empty when field param is missing', function () {
    $template = '{{ reverse_relationship collection="pages" id="origin-id" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('');
});

it('returns empty when no ID available', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('');
});

it('count returns zero when collection param is missing', function () {
    $template = '{{ reverse_relationship:count field="related" id="origin-id" }}';
    $result = (string) Antlers::parse($template);

    expect(trim($result))->toBe('0');
});

// ---------------------------------------------------------------------------
// limit parameter
// ---------------------------------------------------------------------------

it('respects limit parameter', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" id="origin-id" limit="1" }}{{ title }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    // Only one match exists, but limit should still work without errors
    expect($result)->toContain('Referring Multi');
});

// ---------------------------------------------------------------------------
// as parameter
// ---------------------------------------------------------------------------

it('supports as parameter for named output', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" id="origin-id" as="comments" }}{{ comments }}{{ title }}{{ /comments }}{{ if no_results }}none{{ /if }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect($result)->toContain('Referring Multi');
});

it('supports no_results with as parameter when empty', function () {
    $template = '{{ reverse_relationship collection="pages" field="related" id="nonexistent-id" as="comments" }}{{ comments }}{{ title }}{{ /comments }}{{ if no_results }}none{{ /if }}{{ /reverse_relationship }}';
    $result = (string) Antlers::parse($template);

    expect($result)->toContain('none');
});

// ---------------------------------------------------------------------------
// static handle
// ---------------------------------------------------------------------------

it('has correct static handle', function () {
    $reflection = new ReflectionProperty(ReverseRelationship::class, 'handle');
    expect($reflection->getValue())->toBe('reverse_relationship');
});
