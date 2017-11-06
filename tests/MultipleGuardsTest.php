<?php

namespace Idsign\Permission\Test;

use Idsign\Permission\Models\Permission;
use Idsign\Permission\Models\Section;

class MultipleGuardsTest extends TestCase
{
    /** @test */
    public function it_can_give_a_permission_to_a_model_that_is_used_by_multiple_guards()
    {
        $this->testUser->givePermissionTo(Permission::create([
            'name' => 'do_this',
            'guard_name' => 'web',
        ]),Section::create([
            'name' => 'section1',
            'guard_name' => 'web'
        ]));

        $this->testUser->givePermissionTo(Permission::create([
            'name' => 'do_that',
            'guard_name' => 'api',
        ]),Section::create([
            'name' => 'section2',
            'guard_name' => 'api'
        ]));

        $this->assertTrue($this->testUser->hasPermissionTo('do_this', 'section1','web'));
        $this->assertTrue($this->testUser->hasPermissionTo('do_that', 'section2','api'));
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('auth.guards', [
            'web' => ['driver' => 'session', 'provider' => 'users'],
            'api' => ['driver' => 'jwt', 'provider' => 'users'],
        ]);
    }
}
