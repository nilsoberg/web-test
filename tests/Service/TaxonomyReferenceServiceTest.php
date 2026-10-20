<?php

namespace App\Tests\Service;

use App\Service\TaxonomyReferenceService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\TestCase;

class TaxonomyReferenceServiceTest extends TestCase
{
    public function testSearchAutocompleteWithValidRank(): void
    {
        // 1. Create mocks for the DBAL Connection and Result
        $connectionMock = $this->createMock(Connection::class);
        $resultMock = $this->createMock(Result::class);

        // 2. Expect quoteIdentifier to be called to secure the column name
        $connectionMock->expects($this->once())
            ->method('quoteIdentifier')
            ->with('phylum')
            ->willReturn('`phylum`');

        // 3. Expect the query to be executed with the exact SQL and parameters
        $connectionMock->expects($this->once())
            ->method('executeQuery')
            ->with(
                $this->matchesRegularExpression('/select distinct `phylum` as rank_value/i'),
                $this->equalTo(['query' => 'Asco%', 'limit' => 1]),
                $this->equalTo(['limit' => 1])
            )
            ->willReturn($resultMock);

        // 4. Mock the final array return
        $resultMock->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['rank_value' => 'Ascomycota']]);

        $service = new TaxonomyReferenceService($connectionMock, 'taxonomy');
        $data = $service->searchAutocomplete('phylum', 'Asco', 1);

        $this->assertEquals([['rank_value' => 'Ascomycota']], $data);
    }

    public function testSearchAutocompleteThrowsExceptionOnInvalidRank(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $service = new TaxonomyReferenceService($connectionMock, 'taxonomy');

        // We expect the whitelist to block 'invalid_column' and throw an exception
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid rank "invalid_column" specified in taxonomy search.');

        $service->searchAutocomplete('invalid_column', 'test');
    }
}
