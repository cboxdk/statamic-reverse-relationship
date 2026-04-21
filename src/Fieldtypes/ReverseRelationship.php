<?php

namespace Cbox\ReverseRelationship\Fieldtypes;

use Illuminate\Support\Collection;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Query\Builder;
use Statamic\Facades\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry;
use Statamic\Facades\Taxonomy;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Fields\Fieldtype;
use Statamic\Query\OrderedQueryBuilder;

class ReverseRelationship extends Fieldtype
{
    /** @var string */
    protected static $title = 'Reverse Relationship';

    /** @var string */
    protected $icon = 'fieldtype-entries';

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function configFieldItems(): array
    {
        return [
            [
                'display' => __('Appearance & Behavior'),
                'fields' => [
                    'mode' => [
                        'display' => __('Type'),
                        'instructions' => __('The relationship type'),
                        'type' => 'button_group',
                        'default' => 'entries',
                        'options' => [
                            'entries' => __('Entries'),
                            'terms' => __('Terms'),
                            'assets' => __('Assets'),
                        ],
                    ],
                    'collection' => [
                        'display' => __('Collection'),
                        'instructions' => __('The related collection'),
                        'type' => 'collections',
                        'max_items' => 1,
                        'validate' => 'required_if:mode,entries',
                        'if' => [
                            'mode' => 'entries',
                        ],
                    ],
                    'taxonomy' => [
                        'display' => __('Taxonomy'),
                        'instructions' => __('The related taxonomy'),
                        'type' => 'taxonomies',
                        'max_items' => 1,
                        'validate' => 'required_if:mode,terms',
                        'if' => [
                            'mode' => 'terms',
                        ],
                    ],
                    'container' => [
                        'display' => __('Container'),
                        'instructions' => __('The related container'),
                        'type' => 'asset_container',
                        'max_items' => 1,
                        'validate' => 'required_if:mode,assets',
                        'if' => [
                            'mode' => 'assets',
                        ],
                    ],
                    'field' => [
                        'display' => __('Field'),
                        'instructions' => __('The relationship field on the related items that points back here'),
                        'type' => 'reverse_relationship_field_select',
                        'validate' => 'required',
                    ],
                    'sort' => [
                        'display' => __('Sort'),
                        'instructions' => __('The related item sort order'),
                        'type' => 'text',
                    ],
                    'editable' => [
                        'display' => __('Editable'),
                        'instructions' => __('Allow adding and removing related items'),
                        'type' => 'toggle',
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array{id: string|null, editable: bool}
     */
    public function preload(): array
    {
        return [
            'id' => $this->field()?->parent()?->id(),
            'editable' => (bool) $this->config('editable', false),
        ];
    }

    /**
     * @return Collection<int, mixed>|Builder
     */
    public function augment(mixed $value): Collection|Builder
    {
        $id = $this->field()?->parent()?->id();

        if ($id === null) {
            return collect();
        }

        $matchingIds = $this->getMatchingIds($id);
        if ($matchingIds === []) {
            return collect();
        }

        $query = $this->getBaseQuery($id);
        if ($query === null) {
            return collect();
        }

        /** @var OrderedQueryBuilder */
        return (new OrderedQueryBuilder($query, $matchingIds))
            ->whereIn('id', $matchingIds); /** @phpstan-ignore method.notFound */
    }

    public function preProcessIndex(mixed $data): int
    {
        $id = $this->field()?->parent()?->id();

        if ($id === null) {
            return 0;
        }

        return $this->getCount($id);
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getItems(string $id): Collection
    {
        return $this->getFilteredResults($id);
    }

    public function getCount(string $id): int
    {
        return $this->getFilteredResults($id)->count();
    }

    /**
     * Get the base query for the configured mode, without the relationship filter.
     */
    protected function getBaseQuery(string $id): ?Builder
    {
        $mode = $this->config('mode', 'entries');

        if ($mode === 'entries') {
            $collection = $this->config('collection');
            if (! $collection) {
                return null;
            }

            /** @var Builder */
            return Entry::query()
                ->where('collection', $collection)
                ->whereStatus('published');
        }

        if ($mode === 'terms') {
            $taxonomy = $this->config('taxonomy');
            if (! $taxonomy) {
                return null;
            }

            return Term::query()->where('taxonomy', $taxonomy);
        }

        if ($mode === 'assets') {
            $container = $this->config('container');
            if (! $container) {
                return null;
            }

            return Asset::query()->where('container', $container);
        }

        return null;
    }

    /**
     * Get the IDs of items that reference the given ID through the configured field.
     *
     * We avoid using whereJsonContains/where on the field name because
     * Statamic's Stache query builder uses getQueryableValue() which can
     * collide with built-in Entry methods (e.g. page(), collection()).
     *
     * @return list<string>
     */
    public function getMatchingIds(string $id): array
    {
        $mode = $this->config('mode', 'entries');

        if ($mode === 'terms') {
            $id = str($id)->after('::')->value();
        }

        $query = $this->getBaseQuery($id);
        if ($query === null) {
            return [];
        }

        $field = $this->getField();
        if ($field === null) {
            return [];
        }

        $fieldHandle = $this->config('field');
        $key = $field->type() === 'assets' ? 'max_files' : 'max_items';
        $isSingleValue = $field->get($key) === 1;
        $sortField = $this->config('sort') ?? 'title';

        /** @var list<string> */
        return $query
            ->orderBy($sortField)
            ->get()
            ->filter(function (mixed $item) use ($fieldHandle, $id, $isSingleValue): bool {
                /** @var Augmentable $item */
                $value = method_exists($item, 'value')
                    ? $item->value($fieldHandle)
                    : $item->get($fieldHandle); /** @phpstan-ignore method.notFound */
                if ($isSingleValue) {
                    return $value === $id;
                }

                return is_array($value) && in_array($id, $value, true);
            })
            ->map(fn (mixed $item): string => $item->id()) /** @phpstan-ignore method.nonObject */
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, mixed>
     */
    protected function getFilteredResults(string $id): Collection
    {
        $matchingIds = $this->getMatchingIds($id);
        if ($matchingIds === []) {
            return collect();
        }

        $query = $this->getBaseQuery($id);
        if ($query === null) {
            return collect();
        }

        return $query->whereIn('id', $matchingIds)->get(); /** @phpstan-ignore method.notFound */
    }

    protected function getField(): ?Field
    {
        $mode = $this->config('mode', 'entries');

        if ($mode === 'entries') {
            $collection = $this->config('collection');
            $blueprint = $collection
                ? CollectionFacade::findByHandle($collection)?->entryBlueprint()
                : null;
        } elseif ($mode === 'terms') {
            $taxonomy = $this->config('taxonomy');
            $blueprint = $taxonomy
                ? Taxonomy::findByHandle($taxonomy)?->termBlueprint()
                : null;
        } elseif ($mode === 'assets') {
            $container = $this->config('container');
            $blueprint = $container
                ? AssetContainer::findByHandle($container)?->blueprint()
                : null;
        } else {
            return null;
        }

        /** @var ?Field */
        return $blueprint?->fields()->get($this->config('field'));
    }
}
