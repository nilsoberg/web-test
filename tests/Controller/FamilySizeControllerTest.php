<?php

namespace App\Tests\Controller;

use App\Auth\Entity\UserEntity;
use App\Service\DatabaseAuthorizationService;
use App\Tests\BaseControllerTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Zenstruck\Browser\Test\HasBrowser;
use Zenstruck\Foundry\Test\ResetDatabase;

class FamilySizeControllerTest extends BaseControllerTestCase
{
    use HasBrowser;

    public function testUnauthorizedUserReturnsEmptyResponse(): void
    {
        $user = $this->createDefaultUser();

        $this->mockFamilyTable();

        $response = $this->browser()
            ->actingAs($user)
            ->post('/api/calculate-family-size', [
                'body' => [
                    'families' => 'PF00001,PF00002',
                    'dbVersion' => 999,
                ],
            ])
            ->json();

        $data = $response->decoded();
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Invalid database format.', $data['error']);
    }

    public function testEmptyFamilyListReturnsEmptyResponse(): void
    {
        $user = $this->createDefaultUser();

        $this->browser()
            ->actingAs($user)
            ->post('/api/calculate-family-size', [
                'body' => [
                    'families' => '',
                    'dbVersion' => 1,
                ],
            ])
            ->assertSuccessful();
    }

    private function mockFamilyTable(): void
    {
        $schemaManager = $this->entityManager->getConnection()->createSchemaManager();
    
        if (!$schemaManager->tablesExist(['family_info'])) {
            $table = new Table('family_info');
            $table->addColumn('family', 'string', ['length' => 255]);
            $table->addColumn('database_id', 'integer');
            $table->addColumn('short_name', 'string', ['length' => 255]);
            $table->addColumn('num_members', 'integer');
            $table->addColumn('num_uniref50_members', 'integer');
            $table->addColumn('num_uniref90_members', 'integer');
            $table->setPrimaryKey(['family', 'database_id']);
    
            $schemaManager->createTable($table);
        }

        $dbConnection = $this->entityManager->getConnection();

        $dbConnection->insert('family_info', [
            'family' => 'PF00001',
            'database_id' => 1,
            'short_name' => 'FamA',
            'num_members' => 100,
            'num_uniref90_members' => 80,
            'num_uniref50_members' => 50,
        ]);

        $dbConnection->insert('family_info', [
            'family' => 'PF00002',
            'database_id' => 1,
            'short_name' => 'FamB',
            'num_members' => 200,
            'num_uniref90_members' => 160,
            'num_uniref50_members' => 100,
        ]);

        $dbConnection->insert('family_info', [
            'family' => 'PF00002',
            'database_id' => 2,
            'short_name' => 'FamB',
            'num_members' => 200,
            'num_uniref90_members' => 160,
            'num_uniref50_members' => 100,
        ]);
    }
}
