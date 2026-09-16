<?php

declare(strict_types=1);

namespace App\Tests\Admin;

use App\Admin\LoadMorePaginator;
use App\Entity\Region;
use App\Repository\RegionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

final class LoadMorePaginatorTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private RegionRepository $regions;
    private LoadMorePaginator $paginator;

    public static function setUpBeforeClass(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropSchema($em->getMetadataFactory()->getAllMetadata());
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->regions = self::getContainer()->get(RegionRepository::class);
        $this->paginator = self::getContainer()->get(LoadMorePaginator::class);

        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
        $connection->executeStatement('TRUNCATE TABLE region');
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');

        for ($i = 1; $i <= 60; $i++) {
            $this->em->persist((new Region())->setCode(sprintf('R-%02d', $i))->setName("Region {$i}"));
        }
        $this->em->flush();
        $this->em->clear();
    }

    public function testDefaultsToTwentyFiveItems(): void
    {
        $page = $this->paginator->paginate($this->regions, new Request(), ['code' => 'ASC']);

        self::assertCount(25, $page->items);
        self::assertSame(60, $page->total);
        self::assertTrue($page->hasMore());
        self::assertSame(35, $page->remaining());
        self::assertSame(50, $page->nextLimit());
    }

    public function testShowQueryParamGrowsThePage(): void
    {
        $page = $this->paginator->paginate($this->regions, new Request(query: ['show' => '50']), ['code' => 'ASC']);

        self::assertCount(50, $page->items);
        self::assertTrue($page->hasMore());
        self::assertSame(10, $page->remaining());
    }

    public function testNoMoreLeftOnceEverythingIsShown(): void
    {
        $page = $this->paginator->paginate($this->regions, new Request(query: ['show' => '60']), ['code' => 'ASC']);

        self::assertCount(60, $page->items);
        self::assertFalse($page->hasMore());
    }

    public function testShowBelowTheDefaultIsIgnored(): void
    {
        // a tampered/negative "show" must never shrink the page below the default
        $page = $this->paginator->paginate($this->regions, new Request(query: ['show' => '1']), ['code' => 'ASC']);

        self::assertCount(25, $page->items);
    }
}
