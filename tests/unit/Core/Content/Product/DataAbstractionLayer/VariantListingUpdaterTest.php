<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(VariantListingUpdater::class)]
class VariantListingUpdaterTest extends TestCase
{
    private Connection&MockObject $connection;

    private VariantListingUpdater $updater;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->createMock(Connection::class);
        $this->updater = new VariantListingUpdater($this->connection);
    }

    public function testUpdateWithEmptyIdsDoesNothing(): void
    {
        $context = Context::createDefaultContext();

        $this->connection->expects($this->never())->method('executeStatement');
        $this->connection->expects($this->never())->method('createQueryBuilder');

        $this->updater->update([], $context);
    }

    public function testUpdateCallsCleanupQuery(): void
    {
        $context = Context::createDefaultContext();
        $parentId = Uuid::randomHex();

        $this->setupListingConfigurationQuery([$parentId], []);
        $this->connection->expects($this->exactly(3))
            ->method('prepare')
            ->with(static::stringContains('UPDATE product SET display_group'))
            ->willReturn($this->createMock(Statement::class));

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM product_configurator_setting'),
                static::anything(),
                static::anything()
            );

        $this->updater->update([$parentId, $parentId], $context);
    }

    public function testCleanupHandlesMultipleParents(): void
    {
        $context = Context::createDefaultContext();
        $parent1Id = Uuid::randomHex();
        $parent2Id = Uuid::randomHex();

        $this->setupListingConfigurationQuery([$parent1Id, $parent2Id], [0, 1]);
        $this->connection->expects($this->exactly(3))
            ->method('prepare')
            ->with(static::stringContains('UPDATE product SET display_group'))
            ->willReturn($this->createMock(Statement::class));

        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with(
                static::stringContains('DELETE FROM product_configurator_setting'),
                static::anything(),
                static::anything()
            );

        $this->updater->update([$parent1Id, $parent2Id], $context);
    }

    /**
     * @param array<string> $parentIds
     * @param array<int> $childCounts
     */
    private function setupListingConfigurationQuery(array $parentIds, array $childCounts): void
    {
        $resultData = [];
        foreach ($parentIds as $index => $parentId) {
            $resultData[] = [
                'id' => Uuid::fromHexToBytes($parentId),
                'config' => null,
                'child_count' => $childCounts[$index] ?? 0,
            ];
        }

        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($resultData);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('from')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();

        $queryBuilder->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $this->connection->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);
    }
}
