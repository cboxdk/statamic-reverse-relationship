# Reverse Relationship for Statamic

A Statamic addon that lets you traverse relationships in reverse. If entry B points to entry A, this addon lets A see all the B's that reference it — from a single source of truth, with no duplicated data and nothing to keep in sync.

Works with **entries**, **taxonomy terms**, and **assets**.

## The Problem

You have a `comments` collection where each comment points to a blog post. You want the blog post to show its comments. Without this addon, you'd have to manually maintain a list of comment IDs on each post — duplicating data and hoping it stays in sync.

## The Solution

Add the `reverse_relationship` fieldtype to your blog post blueprint, tell it to look in the `comments` collection for entries whose `post` field points here, and you're done:

```yaml
- handle: comments
  field:
    type: reverse_relationship
    collection: comments
    field: post
```

Then use it in your templates — just like any native Statamic field:

```antlers
{{ comments sort="date:desc" limit="5" }}
    <p>{{ author }}: {{ content }}</p>
{{ /comments }}
```

## Features

- **Fieldtype** — add to any blueprint, works like a native `entries` field in templates
- **Antlers tag** — query reverse relationships from any template, no blueprint required
- **Editable mode** — attach/detach related items directly from the CP
- **Lazy query builder** — `limit`, `sort`, `paginate`, `count`, and scoped variables
- **Three modes** — entries, taxonomy terms, and assets

## Quick Start

```bash
composer require cboxdk/statamic-reverse-relationship
```

No config files, no publishing, no migrations.

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for full setup guide, templating examples, and configuration reference.

## Requirements

- PHP 8.2+
- Statamic 6.x
- Laravel 12+

## Development

```bash
composer check    # Pint + PHPStan level 9 + Pest (69 tests)
```

## License

MIT

## Credits

Originally designed and developed by [Sylvester Damgaard](https://github.com/cboxdk) while working at [TV2 Regionerne](https://github.com/tv2regionerne/statamic-reverse-relationship). Now maintained and actively developed under [Cbox](https://github.com/cboxdk) with a full rewrite for Statamic 6, Laravel 12, and Vue 3.
