<?php

namespace Cbox\ReverseRelationship;

use Statamic\Providers\AddonServiceProvider;

class ServiceProvider extends AddonServiceProvider
{
    /** @var array<string, string> */
    protected $routes = [
        'cp' => __DIR__.'/../routes/cp.php',
    ];

    /** @var list<class-string<\Statamic\Fields\Fieldtype>> */
    protected $fieldtypes = [
        Fieldtypes\ReverseRelationship::class,
    ];

    /** @var list<class-string<\Statamic\Tags\Tags>> */
    protected $tags = [
        Tags\ReverseRelationship::class,
    ];

    /** @phpstan-ignore property.defaultValue */
    protected $vite = [
        'input' => [
            'resources/js/addon.js',
        ],
        'publicDirectory' => 'resources/dist',
    ];
}
