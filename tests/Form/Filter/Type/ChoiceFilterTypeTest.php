<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Tests\Form\Filter\Type;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Query\Parameter;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;

class ChoiceFilterTypeTest extends FilterTypeTest
{
    protected const FILTER_TYPE = ChoiceFilterType::class;

    /**
     * @dataProvider getDataProvider
     */
    public function testSubmitAndFilter($submittedData, $data, array $options, string $dql, array $params, ?array $metadata = null)
    {
        $form = $this->factory->create(static::FILTER_TYPE, null, $options);
        $form->submit($submittedData);
        $this->assertSame($data, $form->getData());
        $this->assertSame($submittedData, $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertTrue($form->isSynchronized());

        $filter = $this->filterRegistry->resolveType($form);
        $filter->filter($this->qb, $form, $metadata ?? ['field' => 'foo']);
        $this->assertSame(static::FILTER_TYPE, $filter::class);
        $this->assertSame($dql, $this->qb->getDQL());
        $this->assertSameDoctrineParams($params, $this->qb->getParameters()->toArray());
    }

    public static function getDataProvider(): iterable
    {
        yield [
            ['comparison' => ComparisonType::EQ, 'value' => null],
            ['comparison' => 'IS NULL', 'value' => null],
            [
                'value_type_options' => [
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IS NULL',
            [],
        ];

        yield [
            ['comparison' => ComparisonType::NEQ, 'value' => null],
            ['comparison' => 'IS NOT NULL', 'value' => null],
            [
                'value_type_options' => [
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IS NOT NULL',
            [],
        ];

        yield [
            ['comparison' => ComparisonType::EQ, 'value' => 'a'],
            ['comparison' => '=', 'value' => 'a'],
            [
                'value_type_options' => [
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo = (:foo_1)',
            [new Parameter('foo_1', 'a', \PDO::PARAM_STR)],
        ];

        yield [
            ['comparison' => ComparisonType::NEQ, 'value' => 'b'],
            ['comparison' => '!=', 'value' => 'b'],
            [
                'value_type_options' => [
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo != (:foo_1) OR o.foo IS NULL',
            [new Parameter('foo_1', 'b', \PDO::PARAM_STR)],
        ];

        yield [
            ['comparison' => ComparisonType::EQ, 'value' => []],
            ['comparison' => 'IS NULL', 'value' => []],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IS NULL',
            [],
        ];

        yield [
            ['comparison' => ComparisonType::NEQ, 'value' => []],
            ['comparison' => 'IS NOT NULL', 'value' => []],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IS NOT NULL',
            [],
        ];

        yield [
            ['comparison' => ComparisonType::EQ, 'value' => ['a', 'b']],
            ['comparison' => ComparisonType::CONTAINS, 'value' => ['a', 'b']],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IN (:foo_1)',
            [new Parameter('foo_1', ['a', 'b'], Connection::PARAM_STR_ARRAY)],
        ];

        yield [
            ['comparison' => ComparisonType::NEQ, 'value' => ['b', 'c']],
            ['comparison' => ComparisonType::NOT_CONTAINS, 'value' => ['b', 'c']],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo NOT IN (:foo_1) OR o.foo IS NULL',
            [new Parameter('foo_1', ['b', 'c'], Connection::PARAM_STR_ARRAY)],
        ];

        yield [
            ['comparison' => ComparisonType::CONTAINS, 'value' => ['a', 'c']],
            ['comparison' => ComparisonType::CONTAINS, 'value' => ['a', 'c']],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo IN (:foo_1)',
            [new Parameter('foo_1', ['a', 'c'], Connection::PARAM_STR_ARRAY)],
        ];

        yield [
            ['comparison' => ComparisonType::NOT_CONTAINS, 'value' => ['a']],
            ['comparison' => ComparisonType::NOT_CONTAINS, 'value' => ['a']],
            [
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo NOT IN (:foo_1) OR o.foo IS NULL',
            [new Parameter('foo_1', ['a'], Connection::PARAM_STR_ARRAY)],
        ];

        yield [
            ['comparison' => ComparisonType::CONTAINS_EXACTLY, 'value' => ['a', 'b']],
            ['comparison' => ComparisonType::CONTAINS_EXACTLY, 'value' => ['a', 'b']],
            [
                'field_stores_multiple' => true,
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo LIKE :foo_0_0 AND o.foo LIKE :foo_0_1 AND o.foo NOT LIKE :foo_0_exact_excl_0',
            [
                new Parameter('foo_0_0', '%a%'),
                new Parameter('foo_0_1', '%b%'),
                new Parameter('foo_0_exact_excl_0', '%c%'),
            ],
        ];

        yield [
            ['comparison' => ComparisonType::NOT_CONTAINS_ALL, 'value' => ['a', 'b']],
            ['comparison' => ComparisonType::NOT_CONTAINS_ALL, 'value' => ['a', 'b']],
            [
                'field_stores_multiple' => true,
                'value_type_options' => [
                    'multiple' => true,
                    'choices' => ['a', 'b', 'c'],
                ],
            ],
            'SELECT o FROM Object o WHERE o.foo NOT LIKE :foo_0_notall_0 OR o.foo NOT LIKE :foo_0_notall_1 OR o.foo IS NULL',
            [
                new Parameter('foo_0_notall_0', '%a%'),
                new Parameter('foo_0_notall_1', '%b%'),
            ],
        ];
    }

    public function testScalarFieldMultipleSelectionProvidesPartialComparisons(): void
    {
        $form = $this->factory->create(ChoiceFilterType::class, null, [
            'value_type_options' => [
                'multiple' => true,
                'choices' => ['a', 'b'],
            ],
        ]);

        $choices = $form->get('comparison')->getConfig()->getOption('choices');

        $this->assertArrayHasKey('filter.label.contains_any_of', $choices);
        $this->assertArrayHasKey('filter.label.does_not_contain_any_of', $choices);
        $this->assertArrayNotHasKey('filter.label.contains_exactly', $choices);
        $this->assertArrayNotHasKey('filter.label.does_not_contain_all_of', $choices);
        $this->assertSame(ComparisonType::CONTAINS, $choices['filter.label.contains_any_of']);
        $this->assertSame(ComparisonType::NOT_CONTAINS, $choices['filter.label.does_not_contain_any_of']);
    }

    public function testArrayFieldMultipleSelectionProvidesAllComparisons(): void
    {
        $form = $this->factory->create(ChoiceFilterType::class, null, [
            'field_stores_multiple' => true,
            'value_type_options' => [
                'multiple' => true,
                'choices' => ['a', 'b'],
            ],
        ]);

        $choices = $form->get('comparison')->getConfig()->getOption('choices');

        $this->assertArrayHasKey('filter.label.contains_any_of', $choices);
        $this->assertArrayHasKey('filter.label.contains_all', $choices);
        $this->assertArrayHasKey('filter.label.contains_exactly', $choices);
        $this->assertArrayHasKey('filter.label.does_not_contain_all_of', $choices);
        $this->assertArrayHasKey('filter.label.does_not_contain_any_of', $choices);
        $this->assertSame(ComparisonType::CONTAINS, $choices['filter.label.contains_any_of']);
        $this->assertSame(ComparisonType::CONTAINS_ALL, $choices['filter.label.contains_all']);
        $this->assertSame(ComparisonType::CONTAINS_EXACTLY, $choices['filter.label.contains_exactly']);
        $this->assertSame(ComparisonType::NOT_CONTAINS_ALL, $choices['filter.label.does_not_contain_all_of']);
        $this->assertSame(ComparisonType::NOT_CONTAINS, $choices['filter.label.does_not_contain_any_of']);
    }

    public function testArrayFieldSingleSelectionProvidesContainsComparisons(): void
    {
        $form = $this->factory->create(ChoiceFilterType::class, null, [
            'field_stores_multiple' => true,
            'value_type_options' => [
                'multiple' => false,
                'choices' => ['a', 'b'],
            ],
        ]);

        $choices = $form->get('comparison')->getConfig()->getOption('choices');

        $this->assertArrayHasKey('filter.label.contains', $choices);
        $this->assertArrayHasKey('filter.label.not_contains', $choices);
        $this->assertArrayNotHasKey('filter.label.contains_exactly', $choices);
        $this->assertArrayNotHasKey('filter.label.does_not_contain_all_of', $choices);
        $this->assertSame(ComparisonType::CONTAINS, $choices['filter.label.contains']);
        $this->assertSame(ComparisonType::NOT_CONTAINS, $choices['filter.label.not_contains']);
    }

    public function testLegacyEqualityNormalizesToContainsForArrayFields(): void
    {
        $form = $this->factory->create(ChoiceFilterType::class, null, [
            'field_stores_multiple' => true,
            'value_type_options' => [
                'multiple' => true,
                'choices' => ['a', 'b'],
            ],
        ]);

        $form->submit(['comparison' => ComparisonType::EQ, 'value' => ['a']]);

        $data = $form->getData();
        $this->assertSame(ComparisonType::CONTAINS, $data['comparison']);
        $this->assertSame(['a'], $data['value']);
    }
}
