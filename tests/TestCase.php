<?php

namespace Cbox\ReverseRelationship\Tests;

use Cbox\ReverseRelationship\ServiceProvider;
use Statamic\Testing\AddonTestCase;

abstract class TestCase extends AddonTestCase
{
    protected string $addonServiceProvider = ServiceProvider::class;

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $fixturesDir = __DIR__.'/__fixtures__';

        $app['config']->set('statamic.system.blueprints_path', $fixturesDir.'/blueprints');

        // Required for HTTP feature tests (sessions, cookies, CSRF)
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }
}
