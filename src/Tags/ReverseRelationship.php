<?php

namespace Cbox\ReverseRelationship\Tags;

use Cbox\ReverseRelationship\Fieldtypes\ReverseRelationship as ReverseRelationshipFieldtype;
use Statamic\Facades\Asset;
use Statamic\Facades\Entry;
use Statamic\Facades\Term;
use Statamic\Fields\Field;
use Statamic\Query\OrderedQueryBuilder;
use Statamic\Tags\Concerns;
use Statamic\Tags\Tags;

class ReverseRelationship extends Tags
{
    use Concerns\GetsQueryResults;
    use Concerns\OutputsItems;
    use Concerns\QueriesConditions;
    use Concerns\QueriesOrderBys;

    /** @var string */
    protected static $handle = 'reverse_relationship';

    /** @var string */
    protected $defaultAsKey = 'items';

    /**
     * {{ reverse_relationship }} ... {{ /reverse_relationship }}
     *
     * @return mixed
     */
    public function index()
    {
        $query = $this->query();

        if ($query === null) {
            return $this->output(collect());
        }

        return $this->output($this->results($query));
    }

    /**
     * {{ reverse_relationship:count }}
     */
    public function count(): int
    {
        $query = $this->query();

        if ($query === null) {
            return 0;
        }

        return $query->count();
    }

    protected function query(): ?OrderedQueryBuilder
    {
        $id = $this->resolveId();
        if ($id === null) {
            return null;
        }

        $fieldtype = $this->buildFieldtype();
        if ($fieldtype === null) {
            return null;
        }

        $matchingIds = $fieldtype->getMatchingIds($id);
        if ($matchingIds === []) {
            return null;
        }

        $baseQuery = $this->getBaseQuery();
        if ($baseQuery === null) {
            return null;
        }

        $query = new OrderedQueryBuilder($baseQuery, $matchingIds);
        $query->whereIn('id', $matchingIds); /** @phpstan-ignore method.notFound */
        $this->queryOrderBys($query);
        $this->queryConditions($query);

        return $query;
    }

    protected function resolveId(): ?string
    {
        $id = $this->params->get('id');

        if ($id !== null && $id !== '') {
            return (string) $id;
        }

        $contextId = $this->context->get('id');

        return $contextId !== null && $contextId !== '' ? (string) $contextId : null;
    }

    protected function buildFieldtype(): ?ReverseRelationshipFieldtype
    {
        $mode = $this->params->get('mode', 'entries');
        $fieldHandle = $this->params->get('field');

        if ($fieldHandle === null) {
            return null;
        }

        $config = [
            'type' => 'reverse_relationship',
            'mode' => $mode,
            'field' => $fieldHandle,
        ];

        if ($mode === 'entries') {
            $collection = $this->params->get('collection');
            if ($collection === null) {
                return null;
            }
            $config['collection'] = $collection;
        } elseif ($mode === 'terms') {
            $taxonomy = $this->params->get('taxonomy');
            if ($taxonomy === null) {
                return null;
            }
            $config['taxonomy'] = $taxonomy;
        } elseif ($mode === 'assets') {
            $container = $this->params->get('container');
            if ($container === null) {
                return null;
            }
            $config['container'] = $container;
        }

        if ($this->params->has('sort_field')) {
            $config['sort'] = $this->params->get('sort_field');
        }

        $fieldtype = new ReverseRelationshipFieldtype;
        $fieldtype->setField(new Field('reverse_relationship_tag', $config));

        return $fieldtype;
    }

    /**
     * @return \Statamic\Contracts\Query\Builder|null
     */
    protected function getBaseQuery()
    {
        $mode = $this->params->get('mode', 'entries');

        if ($mode === 'entries') {
            $collection = $this->params->get('collection');
            if ($collection === null) {
                return null;
            }

            /** @var \Statamic\Contracts\Query\Builder */
            return Entry::query()
                ->where('collection', $collection)
                ->whereStatus('published');
        }

        if ($mode === 'terms') {
            $taxonomy = $this->params->get('taxonomy');
            if ($taxonomy === null) {
                return null;
            }

            return Term::query()->where('taxonomy', $taxonomy);
        }

        if ($mode === 'assets') {
            $container = $this->params->get('container');
            if ($container === null) {
                return null;
            }

            return Asset::query()->where('container', $container);
        }

        return null;
    }
}
