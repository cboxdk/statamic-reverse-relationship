<?php

namespace Cbox\ReverseRelationship\Http\Controllers;

use Facades\Statamic\Fields\FieldtypeRepository as Fieldtype;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\ValidationException;
use Statamic\Assets\Asset as AssetData;
use Statamic\Entries\Entry as EntryData;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Facades\User;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;

class ReverseRelationshipController
{
    /** @var list<string> */
    private const ALLOWED_CONFIG_KEYS = ['mode', 'collection', 'taxonomy', 'container', 'field', 'sort'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'config' => ['required', 'string'],
        ]);

        /** @var string $encodedConfig */
        $encodedConfig = $request->input('config');
        $config = $this->decodeAndValidateConfig($encodedConfig);

        $fieldtype = Fieldtype::find('reverse_relationship')->setField(
            new Field('reverse_relationship', $config)
        );

        /** @var string $id */
        $id = $request->input('id', '');
        $items = $fieldtype->getItems($id);

        if ($fieldtype->field()->get('mode') === 'assets') {
            $items = $items->map(function ($asset) {
                /** @var AssetData $asset */
                $data = $asset->toArray();
                if ($asset->isImage() || $asset->isSvg()) {
                    $data['thumbnail'] = $asset->thumbnailUrl('small');
                }

                return $data;
            });
        }

        return JsonResource::collection($items);
    }

    public function sync(Request $request): JsonResponse
    {
        $request->validate([
            'id' => ['required', 'string'],
            'config' => ['required', 'string'],
            'related' => ['present', 'array'],
            'related.*' => ['string'],
        ]);

        /** @var string $encodedConfig */
        $encodedConfig = $request->input('config');
        $config = $this->decodeAndValidateConfig($encodedConfig);

        /** @var string $id */
        $id = $request->input('id');

        /** @var list<string> $related */
        $related = $request->input('related', []);

        /** @var string $mode */
        $mode = $config['mode'] ?? 'entries';
        /** @var string $fieldHandle */
        $fieldHandle = $config['field'] ?? '';

        $field = $this->resolveField($config);
        if ($field === null) {
            return response()->json(['error' => 'Field not found in blueprint.'], 422);
        }

        $key = $field->type() === 'assets' ? 'max_files' : 'max_items';
        $isSingleValue = $field->get($key) === 1;

        // Get current related IDs
        $fieldtype = Fieldtype::find('reverse_relationship')->setField(
            new Field('reverse_relationship', $config)
        );
        $currentItems = $fieldtype->getItems($id);

        /** @var list<string> $currentIds */
        $currentIds = $currentItems->map(function (mixed $item) use ($mode): string {
            return $this->getItemId($item, $mode);
        })->values()->all();

        $toAttach = array_diff($related, $currentIds);
        $toDetach = array_diff($currentIds, $related);

        /** @var list<string> $errors */
        $errors = [];

        $parentId = $mode === 'terms' ? str($id)->after('::')->value() : $id;

        // Detach items
        foreach ($toDetach as $itemId) {
            $item = $this->findItem($itemId, $mode, $config);
            if ($item === null) {
                continue;
            }

            if (! $this->canUpdate($item, $mode)) {
                $errors[] = "No permission to update: {$itemId}";

                continue;
            }

            $this->detachFromItem($item, $fieldHandle, $parentId, $isSingleValue);
            $this->saveItem($item, $mode);
        }

        // Attach items
        foreach ($toAttach as $itemId) {
            $item = $this->findItem($itemId, $mode, $config);
            if ($item === null) {
                continue;
            }

            if (! $this->canUpdate($item, $mode)) {
                $errors[] = "No permission to update: {$itemId}";

                continue;
            }

            $this->attachToItem($item, $fieldHandle, $parentId, $isSingleValue);
            $this->saveItem($item, $mode);
        }

        // Refresh items list
        $refreshed = $fieldtype->getItems($id);

        if ($mode === 'assets') {
            $refreshed = $refreshed->map(function ($asset) {
                /** @var AssetData $asset */
                $data = $asset->toArray();
                if ($asset->isImage() || $asset->isSvg()) {
                    $data['thumbnail'] = $asset->thumbnailUrl('small');
                }

                return $data;
            });
        }

        return response()->json([
            'data' => $refreshed->values()->all(),
            'errors' => $errors,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'config' => ['required', 'string'],
            'query' => ['nullable', 'string'],
            'exclude' => ['nullable', 'string'],
        ]);

        /** @var string $encodedConfig */
        $encodedConfig = $request->input('config');
        $config = $this->decodeAndValidateConfig($encodedConfig);

        /** @var string $mode */
        $mode = $config['mode'] ?? 'entries';

        /** @var string $searchQuery */
        $searchQuery = $request->string('query')->value();

        /** @var string $excludeRaw */
        $excludeRaw = $request->string('exclude')->value();
        /** @var list<string> $excludeIds */
        $excludeIds = array_values(array_filter(explode(',', $excludeRaw)));

        $results = $this->searchItems($config, $mode, $searchQuery, $excludeIds);

        return response()->json([
            'data' => $results,
        ]);
    }

    public function fields(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => ['required', 'string', 'in:entries,terms,assets'],
            'handle' => ['required', 'string'],
        ]);

        /** @var string $mode */
        $mode = $request->input('mode');
        /** @var string $handle */
        $handle = $request->input('handle');

        $blueprints = collect();

        if ($mode === 'entries') {
            $collection = CollectionFacade::findByHandle($handle);
            $blueprints = $collection ? $collection->entryBlueprints() : collect();
        } elseif ($mode === 'terms') {
            $taxonomy = Taxonomy::findByHandle($handle);
            $blueprints = $taxonomy ? collect([$taxonomy->termBlueprint()]) : collect();
        } elseif ($mode === 'assets') {
            $container = AssetContainer::findByHandle($handle);
            $blueprints = $container ? collect([$container->blueprint()]) : collect();
        }

        $relationshipTypes = ['entries', 'terms', 'assets', 'taxonomy_terms'];
        /** @var array<string, true> $seen */
        $seen = [];
        /** @var list<array{handle: string, display: string, type: string}> $options */
        $options = [];

        foreach ($blueprints as $blueprint) {
            foreach ($blueprint->fields()->all() as $field) {
                if (in_array($field->type(), $relationshipTypes, true) && ! isset($seen[$field->handle()])) {
                    $seen[$field->handle()] = true;
                    $options[] = [
                        'handle' => $field->handle(),
                        'display' => $field->display(),
                        'type' => $field->type(),
                    ];
                }
            }
        }

        return response()->json(['data' => $options]);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>  $excludeIds
     * @return list<array<string, mixed>>
     */
    private function searchItems(array $config, string $mode, string $query, array $excludeIds): array
    {
        if ($mode === 'entries') {
            /** @var string|null $collection */
            $collection = $config['collection'] ?? null;
            if ($collection === null || $collection === '' || CollectionFacade::findByHandle($collection) === null) {
                return [];
            }

            $entries = Entry::query()
                ->where('collection', $collection)
                ->whereStatus('published')
                ->orderBy('title')
                ->limit(50)
                ->get();

            /** @var list<array<string, mixed>> */
            return $entries
                ->filter(function (mixed $entry) use ($query, $excludeIds): bool {
                    /** @var EntryData $entry */
                    if (in_array($entry->id(), $excludeIds, true)) {
                        return false;
                    }
                    if ($query !== '') {
                        return str_contains(
                            mb_strtolower((string) $entry->get('title', '')),
                            mb_strtolower($query)
                        );
                    }

                    return true;
                })
                ->take(20)
                ->map(function (mixed $entry): array {
                    /** @var EntryData $entry */
                    return [
                        'id' => $entry->id(),
                        'title' => $entry->get('title', $entry->slug()),
                        'status' => $entry->status(),
                        'hint' => $entry->collection()?->title() ?? '',
                        'edit_url' => $entry->editUrl(),
                    ];
                })
                ->values()
                ->all();
        }

        if ($mode === 'terms') {
            /** @var string|null $taxonomy */
            $taxonomy = $config['taxonomy'] ?? null;
            if ($taxonomy === null || $taxonomy === '' || Taxonomy::findByHandle($taxonomy) === null) {
                return [];
            }

            $terms = Term::query()
                ->where('taxonomy', $taxonomy)
                ->orderBy('title')
                ->limit(50)
                ->get();

            /** @var list<array<string, mixed>> */
            return $terms
                ->filter(function (mixed $term) use ($query, $excludeIds): bool {
                    /** @var LocalizedTerm $term */
                    if (in_array($term->id(), $excludeIds, true)) {
                        return false;
                    }
                    if ($query !== '') {
                        return str_contains(
                            mb_strtolower((string) $term->get('title', '')),
                            mb_strtolower($query)
                        );
                    }

                    return true;
                })
                ->take(20)
                ->map(function (mixed $term): array {
                    /** @var LocalizedTerm $term */
                    return [
                        'id' => $term->id(),
                        'title' => $term->get('title', $term->slug()),
                        'edit_url' => $term->editUrl(),
                    ];
                })
                ->values()
                ->all();
        }

        if ($mode === 'assets') {
            /** @var string|null $container */
            $container = $config['container'] ?? null;
            if ($container === null || $container === '' || AssetContainer::findByHandle($container) === null) {
                return [];
            }

            $assets = Asset::query()
                ->where('container', $container)
                ->limit(50)
                ->get();

            /** @var list<array<string, mixed>> */
            return $assets
                ->filter(function (mixed $asset) use ($query, $excludeIds): bool {
                    /** @var AssetData $asset */
                    if (in_array($asset->id(), $excludeIds, true)) {
                        return false;
                    }
                    if ($query !== '') {
                        $path = $asset->path();

                        return is_string($path) && str_contains(
                            mb_strtolower($path),
                            mb_strtolower($query)
                        );
                    }

                    return true;
                })
                ->take(20)
                ->map(function (mixed $asset): array {
                    /** @var AssetData $asset */
                    $data = [
                        'id' => $asset->id(),
                        'title' => $asset->basename(),
                        'path' => $asset->path(),
                        'edit_url' => $asset->editUrl(),
                    ];
                    if ($asset->isImage() || $asset->isSvg()) {
                        $data['thumbnail'] = $asset->thumbnailUrl('small');
                    }

                    return $data;
                })
                ->values()
                ->all();
        }

        return [];
    }

    private function getItemId(mixed $item, string $mode): string
    {
        if ($mode === 'assets') {
            /** @var AssetData $item */
            return $item->id();
        }

        /** @var EntryData|LocalizedTerm $item */
        return $item->id();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function findItem(string $id, string $mode, array $config): EntryData|LocalizedTerm|AssetData|null
    {
        if ($mode === 'entries') {
            /** @var EntryData|null */
            return Entry::find($id);
        }

        if ($mode === 'terms') {
            /** @var LocalizedTerm|null */
            return Term::find($id);
        }

        if ($mode === 'assets') {
            /** @var string $container */
            $container = $config['container'] ?? '';

            /** @var AssetData|null */
            return Asset::find("{$container}::{$id}");
        }

        return null;
    }

    private function canUpdate(EntryData|LocalizedTerm|AssetData $item, string $mode): bool
    {
        /** @var \Statamic\Auth\File\User|null $user */
        $user = User::current();
        if ($user === null) {
            return false;
        }

        if ($mode === 'assets') {
            return $user->can('edit', $item);
        }

        return $user->can('update', $item);
    }

    private function detachFromItem(EntryData|LocalizedTerm|AssetData $item, string $fieldHandle, string $parentId, bool $isSingleValue): void
    {
        if ($isSingleValue) {
            $item->set($fieldHandle, null);
        } else {
            /** @var array<int, string>|null $current */
            $current = $item->get($fieldHandle);
            if (is_array($current)) {
                $item->set($fieldHandle, array_values(array_diff($current, [$parentId])));
            }
        }
    }

    private function attachToItem(EntryData|LocalizedTerm|AssetData $item, string $fieldHandle, string $parentId, bool $isSingleValue): void
    {
        if ($isSingleValue) {
            $item->set($fieldHandle, $parentId);
        } else {
            /** @var array<int, string>|null $current */
            $current = $item->get($fieldHandle) ?? [];
            if (! is_array($current)) {
                $current = [];
            }
            if (! in_array($parentId, $current, true)) {
                $current[] = $parentId;
            }
            $item->set($fieldHandle, array_values($current));
        }
    }

    private function saveItem(EntryData|LocalizedTerm|AssetData $item, string $mode): void
    {
        if ($item instanceof AssetData) {
            $item->save();

            return;
        }

        if ($item instanceof EntryData && $item->revisionsEnabled() && $item->published()) {
            $item->makeWorkingCopy()->save();
        } else {
            /** @var \Statamic\Auth\File\User|null $user */
            $user = User::current();
            if ($user !== null && $item instanceof EntryData) {
                $item->updateLastModified($user);
            }
            $item->save();
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function resolveField(array $config): ?Field
    {
        /** @var string $mode */
        $mode = $config['mode'] ?? 'entries';
        /** @var string $fieldHandle */
        $fieldHandle = $config['field'] ?? '';

        if ($mode === 'entries') {
            /** @var string|null $collection */
            $collection = $config['collection'] ?? null;
            $blueprint = is_string($collection)
                ? CollectionFacade::findByHandle($collection)?->entryBlueprint()
                : null;
        } elseif ($mode === 'terms') {
            /** @var string|null $taxonomy */
            $taxonomy = $config['taxonomy'] ?? null;
            $blueprint = is_string($taxonomy)
                ? Taxonomy::findByHandle($taxonomy)?->termBlueprint()
                : null;
        } elseif ($mode === 'assets') {
            /** @var string|null $container */
            $container = $config['container'] ?? null;
            $blueprint = is_string($container)
                ? AssetContainer::findByHandle($container)?->blueprint()
                : null;
        } else {
            return null;
        }

        /** @var ?Field */
        return $blueprint?->fields()->get($fieldHandle);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeAndValidateConfig(string $encoded): array
    {
        $json = base64_decode($encoded, strict: true);

        if ($json === false) {
            throw ValidationException::withMessages([
                'config' => 'Config must be valid base64.',
            ]);
        }

        $config = json_decode($json, true);

        if (! is_array($config)) {
            throw ValidationException::withMessages([
                'config' => 'Config must be a valid JSON object.',
            ]);
        }

        $unknownKeys = array_diff(array_keys($config), self::ALLOWED_CONFIG_KEYS);

        if (! empty($unknownKeys)) {
            throw ValidationException::withMessages([
                'config' => 'Config contains unknown keys: '.implode(', ', $unknownKeys),
            ]);
        }

        /** @var array<string, mixed> $config */
        return $config;
    }
}
