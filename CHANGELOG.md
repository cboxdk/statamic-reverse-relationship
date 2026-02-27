# Changelog

All notable changes to this package will be documented in this file.

## [2.0.0] - 2026-02-27

Complete rewrite for Statamic 6, Laravel 12, Vue 3, and PHP 8.2+. This version is not backwards-compatible with the original `tv2regionerne/statamic-reverse-relationship` package.

### Added
- **Antlers tag** `{{ reverse_relationship }}` — query reverse relationships from any template without a blueprint field
  - Full parameter support: `limit`, `offset`, `sort`, `paginate`, `as`, conditions
  - `{{ reverse_relationship:count }}` for counting
  - Automatic context ID fallback — uses the current entry's ID when no `id` param is given
- **Query builder return from `augment()`** — the fieldtype returns a lazy `OrderedQueryBuilder` instead of an eager `Collection`, enabling template-level `limit`/`sort` chaining and deferred augmentation
- **Editable mode** — attach/detach related items directly from the CP
- **CP search endpoint** for finding related items
- Vue 3 Composition API components with `<script setup>` and Kitt UI
- Vite build pipeline for addon assets
- Pest v3 test suite with PHPStan level 9 (69 tests)
- Support for three relationship modes: `entries`, `terms`, `assets`
- `whereStatus('published')` filter for entries queries

### Changed
- PHP namespace: `Tv2regionerne\StatamicReverseRelationship` → `Cbox\ReverseRelationship`
- Composer package: `tv2regionerne/statamic-reverse-relationship` → `cboxdk/statamic-reverse-relationship`
- ServiceProvider uses Statamic v6 property-based registration
- Vue components migrated from Options API to Composition API

### Removed
- `pixelfear/composer-dist-plugin` dependency
- Vue 2 lifecycle hooks and Options API patterns
