<?php

namespace App\Tests\Controller;

use App\Home\Entity\PublicDB;
use App\Tests\BaseControllerTestCase;
use App\Tests\Factory\UserEntityFactory;
use Zenstruck\Browser\Test\HasBrowser;

class WebAdminControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testRoleUserCannotAccessWebAdminDashboard(): void
    {
        $user = UserEntityFactory::createOne();

        $this->browser()
            ->actingAs($user)
            ->get('/webadmin')
            ->assertStatus(403);
    }

    public function testDataAdminCanAccessWebAdminDashboard(): void
    {
        $dataAdmin = UserEntityFactory::new()->asDataAdmin()->create();

        $this->browser()
            ->actingAs($dataAdmin)
            ->get('/webadmin')
            ->assertSuccessful();
    }

    public function testRoleUserCannotCreatePublicDatabase(): void
    {
        $user = UserEntityFactory::createOne();

        $this->browser()
            ->actingAs($user)
            ->post('/webadmin/public-database/create', [
                'body' => [
                    'uniprot' => '2026_01',
                    'interpro' => '101.0',
                    'ena' => 'ENA-TEST',
                ],
            ])
            ->assertStatus(403);

        $count = $this->entityManager->getRepository(PublicDB::class)->count([]);
        $this->assertSame(0, $count);
    }
}
