<?php

namespace Cbox\ReverseRelationship\Fieldtypes;

use Statamic\Fields\Fieldtype;

class ReverseRelationshipFieldSelect extends Fieldtype
{
    /** @var bool */
    protected $selectable = false;

    /** @var string */
    protected static $title = 'Reverse Relationship Field Select';

    /** @var string */
    protected $icon = 'text';
}
