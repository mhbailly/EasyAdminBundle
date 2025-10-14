<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Filter;

use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ChoiceFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    /**
     * @param array<mixed> $choices
     */
    public function setChoices(array $choices): self
    {
        $this->dto->setFormTypeOption('value_type_options.choices', $choices);

        return $this;
    }

    /**
     * @param array<string|TranslatableInterface> $choiceGenerator
     */
    public function setTranslatableChoices(array $choiceGenerator): self
    {
        $this->dto->setFormTypeOption('value_type_options.choices', array_keys($choiceGenerator));
        $this->dto->setFormTypeOption('value_type_options.choice_label', fn ($value) => $choiceGenerator[$value]);

        return $this;
    }

    public function renderExpanded(bool $isExpanded = true): self
    {
        $this->dto->setFormTypeOption('value_type_options.expanded', $isExpanded);

        return $this;
    }

    public function canSelectMultiple(bool $selectMultiple = true): self
    {
        $this->dto->setFormTypeOption('value_type_options.multiple', $selectMultiple);

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $comparison = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();
        $isMultiple = (bool) $filterDataDto->getFormTypeOption('value_type_options.multiple');
        $configuredStoresMultiple = $filterDataDto->getFormTypeOption('field_stores_multiple');
        $storesArray = null !== $configuredStoresMultiple
            ? (bool) $configuredStoresMultiple
            : $this->storesArrayValues($fieldDto, $entityDto, $property);
        $wrapWithQuotes = $storesArray && $this->needsQuotedSearchPattern($fieldDto, $entityDto, $property);

        if (null === $value || ($isMultiple && \is_array($value) && 0 === \count($value))) {
            $queryBuilder->andWhere(sprintf('%s.%s %s', $alias, $property, $comparison));

            return;
        }

        if ($storesArray) {
            $this->applyForArrayStorage($queryBuilder, $alias, $property, $comparison, $parameterName, $value, $wrapWithQuotes);

            return;
        }

        $this->applyForScalarStorage($queryBuilder, $alias, $property, $comparison, $parameterName, $value);
    }

    private function applyForScalarStorage(QueryBuilder $queryBuilder, string $alias, string $property, string $comparison, string $parameterName, mixed $value): void
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        if (\is_array($value)) {
            $value = array_values($value);
        }

        if (\in_array($comparison, [ComparisonType::CONTAINS, ComparisonType::CONTAINS_ALL], true)) {
            $comparison = \is_array($value) ? 'IN' : '=';
        } elseif (ComparisonType::NOT_CONTAINS === $comparison) {
            $comparison = \is_array($value) ? 'NOT IN' : '!=';
        }

        $orX = new Orx();
        $orX->add(sprintf('%s.%s %s (:%s)', $alias, $property, $comparison, $parameterName));

        if (\in_array($comparison, [ComparisonType::NEQ, '!=', 'NOT IN'], true)) {
            $orX->add(sprintf('%s.%s IS NULL', $alias, $property));
        }

        $queryBuilder->andWhere($orX)
            ->setParameter($parameterName, $value);
    }

    private function applyForArrayStorage(QueryBuilder $queryBuilder, string $alias, string $property, string $comparison, string $parameterName, mixed $value, bool $wrapWithQuotes): void
    {
        if ($value instanceof \Traversable) {
            $value = iterator_to_array($value, false);
        }

        $values = \is_array($value) ? array_values($value) : [$value];

        if (\in_array($comparison, ['IN', '='], true)) {
            $comparison = ComparisonType::CONTAINS;
        } elseif (\in_array($comparison, ['NOT IN', '!='], true)) {
            $comparison = ComparisonType::NOT_CONTAINS;
        }

        if (ComparisonType::CONTAINS_ALL === $comparison) {
            $this->applyContainsAllComparison($queryBuilder, $alias, $property, $parameterName, $values, $wrapWithQuotes);

            return;
        }

        if (ComparisonType::NOT_CONTAINS === $comparison) {
            $this->applyNotContainsComparison($queryBuilder, $alias, $property, $parameterName, $values, $wrapWithQuotes);

            return;
        }

        $this->applyContainsAnyComparison($queryBuilder, $alias, $property, $parameterName, $values, $wrapWithQuotes);
    }

    /**
     * @param array<mixed> $values
     */
    private function applyContainsAllComparison(QueryBuilder $queryBuilder, string $alias, string $property, string $parameterName, array $values, bool $wrapWithQuotes): void
    {
        $andX = new Andx();

        foreach ($values as $index => $item) {
            $itemParameterName = sprintf('%s_%s', $parameterName, $index);
            $andX->add(sprintf('%s.%s LIKE :%s', $alias, $property, $itemParameterName));
            $queryBuilder->setParameter($itemParameterName, $this->createLikePattern($item, $wrapWithQuotes));
        }

        $queryBuilder->andWhere($andX);
    }

    /**
     * @param array<mixed> $values
     */
    private function applyNotContainsComparison(QueryBuilder $queryBuilder, string $alias, string $property, string $parameterName, array $values, bool $wrapWithQuotes): void
    {
        $andX = new Andx();

        foreach ($values as $index => $item) {
            $itemParameterName = sprintf('%s_%s', $parameterName, $index);
            $andX->add(sprintf('%s.%s NOT LIKE :%s', $alias, $property, $itemParameterName));
            $queryBuilder->setParameter($itemParameterName, $this->createLikePattern($item, $wrapWithQuotes));
        }

        $orX = new Orx();
        $orX->add($andX);
        $orX->add(sprintf('%s.%s IS NULL', $alias, $property));

        $queryBuilder->andWhere($orX);
    }

    /**
     * @param array<mixed> $values
     */
    private function applyContainsAnyComparison(QueryBuilder $queryBuilder, string $alias, string $property, string $parameterName, array $values, bool $wrapWithQuotes): void
    {
        $orX = new Orx();

        foreach ($values as $index => $item) {
            $itemParameterName = sprintf('%s_%s', $parameterName, $index);
            $orX->add(sprintf('%s.%s LIKE :%s', $alias, $property, $itemParameterName));
            $queryBuilder->setParameter($itemParameterName, $this->createLikePattern($item, $wrapWithQuotes));
        }

        $queryBuilder->andWhere($orX);
    }

    private function createLikePattern(mixed $value, bool $wrapWithQuotes): string
    {
        $needle = (string) $value;

        if ($wrapWithQuotes) {
            return '%"'.$needle.'"%';
        }

        return '%'.$needle.'%';
    }

    /**
     * @internal The Doctrine metadata of the FieldDto is not always populated; fall back to entity metadata when possible.
     */
    private function storesArrayValues(?FieldDto $fieldDto, EntityDto $entityDto, string $property): bool
    {
        $type = null;

        if (null !== $fieldDto) {
            $type = $fieldDto->getDoctrineMetadata()->get('type');
        }

        if (null === $type && $entityDto->hasProperty($property)) {
            try {
                $type = $entityDto->getPropertyMetadata($property)->get('type');
            } catch (\Throwable) {
                $type = null;
            }
        }

        if (!\is_string($type)) {
            return false;
        }

        $normalizedType = strtolower($type);

        return \in_array($normalizedType, ['json', 'json_array', 'jsonb', 'simple_array', 'array'], true);
    }

    private function needsQuotedSearchPattern(?FieldDto $fieldDto, EntityDto $entityDto, string $property): bool
    {
        $type = null;

        if (null !== $fieldDto) {
            $type = $fieldDto->getDoctrineMetadata()->get('type');
        }

        if (null === $type && $entityDto->hasProperty($property)) {
            try {
                $type = $entityDto->getPropertyMetadata($property)->get('type');
            } catch (\Throwable) {
                $type = null;
            }
        }

        if (!\is_string($type)) {
            return false;
        }

        return 'simple_array' === strtolower($type);
    }
}
