<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Test\DoctrineTestHelper;

class ChoiceFilterTest extends TestCase
{
    private EntityDto $entityDto;

    protected function setUp(): void
    {
        $metadata = new ClassMetadata(self::class);
        $metadata->setIdentifier(['id']);

        $this->entityDto = new EntityDto(self::class, $metadata);
    }

    public function testContainsOneOfWithJsonStorage(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $filter = ChoiceFilter::new('foo')->canSelectMultiple();
        $filterData = FilterDataDto::new(
            0,
            $filter->getAsDto(),
            'o',
            [
                'comparison' => ComparisonType::CONTAINS,
                'value' => ['red', 'green'],
            ]
        );
        $fieldDto = $this->createFieldDto('foo', 'json');

        $filter->apply($queryBuilder, $filterData, $fieldDto, $this->entityDto);

        self::assertSame('SELECT o FROM Object o WHERE o.foo LIKE :foo_0_0 OR o.foo LIKE :foo_0_1', $queryBuilder->getDQL());
        $parameters = $queryBuilder->getParameters()->toArray();
        self::assertCount(2, $parameters);
        self::assertSame('%red%', $parameters[0]->getValue());
        self::assertSame('%green%', $parameters[1]->getValue());
    }

    public function testContainsAllWithSimpleArrayStorage(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $filter = ChoiceFilter::new('foo')->canSelectMultiple();
        $filterData = FilterDataDto::new(
            0,
            $filter->getAsDto(),
            'o',
            [
                'comparison' => ComparisonType::CONTAINS_ALL,
                'value' => ['north', 'south'],
            ]
        );
        $fieldDto = $this->createFieldDto('foo', 'simple_array');

        $filter->apply($queryBuilder, $filterData, $fieldDto, $this->entityDto);

        self::assertSame('SELECT o FROM Object o WHERE o.foo LIKE :foo_0_0 AND o.foo LIKE :foo_0_1', $queryBuilder->getDQL());
        $parameters = $queryBuilder->getParameters()->toArray();
        self::assertCount(2, $parameters);
        self::assertSame('%"north"%', $parameters[0]->getValue());
        self::assertSame('%"south"%', $parameters[1]->getValue());
    }

    public function testDoesNotContainAnyWithJsonStorage(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $filter = ChoiceFilter::new('foo')->canSelectMultiple();
        $filterData = FilterDataDto::new(
            0,
            $filter->getAsDto(),
            'o',
            [
                'comparison' => ComparisonType::NOT_CONTAINS,
                'value' => ['silver', 'gold'],
            ]
        );
        $fieldDto = $this->createFieldDto('foo', 'json');

        $filter->apply($queryBuilder, $filterData, $fieldDto, $this->entityDto);

        self::assertSame('SELECT o FROM Object o WHERE (o.foo NOT LIKE :foo_0_0 AND o.foo NOT LIKE :foo_0_1) OR o.foo IS NULL', $queryBuilder->getDQL());
        $parameters = $queryBuilder->getParameters()->toArray();
        self::assertCount(2, $parameters);
        self::assertSame('%silver%', $parameters[0]->getValue());
        self::assertSame('%gold%', $parameters[1]->getValue());
    }

    public function testContainsOneOfWithScalarStorageFallsBackToInComparison(): void
    {
        $queryBuilder = $this->createQueryBuilder();
        $filter = ChoiceFilter::new('foo')->canSelectMultiple();
        $filterData = FilterDataDto::new(
            0,
            $filter->getAsDto(),
            'o',
            [
                'comparison' => ComparisonType::CONTAINS,
                'value' => ['east'],
            ]
        );

        $filter->apply($queryBuilder, $filterData, null, $this->entityDto);

        self::assertSame('SELECT o FROM Object o WHERE o.foo IN (:foo_0)', $queryBuilder->getDQL());
        $parameters = $queryBuilder->getParameters()->toArray();
        self::assertCount(1, $parameters);
        self::assertSame(['east'], $parameters[0]->getValue());
    }

    private function createQueryBuilder(): QueryBuilder
    {
        $entityManager = DoctrineTestHelper::createTestEntityManager();
        $queryBuilder = new QueryBuilder($entityManager);
        $queryBuilder->select('o')->from('Object', 'o');

        return $queryBuilder;
    }

    private function createFieldDto(string $propertyName, string $doctrineType): FieldDto
    {
        $fieldDto = new FieldDto();
        $fieldDto->setProperty($propertyName);
        $fieldDto->setDoctrineMetadata(['type' => $doctrineType]);

        return $fieldDto;
    }
}
