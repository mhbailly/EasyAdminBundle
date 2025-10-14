<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Filter\Configurator;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterConfiguratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

/**
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 */
final class ChoiceConfigurator implements FilterConfiguratorInterface
{
    public function supports(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): bool
    {
        return ChoiceFilter::class === $filterDto->getFqcn();
    }

    public function configure(FilterDto $filterDto, ?FieldDto $fieldDto, EntityDto $entityDto, AdminContext $context): void
    {
        $choices = $filterDto->getFormTypeOption('value_type_options.choices');

        if (null === $choices || 0 === \count($choices)) {
            throw new \InvalidArgumentException(sprintf('The choice filter associated to the "%s" property does not define its choices. Define them with the setChoices() method.', $filterDto->getProperty()));
        }

        $filterDto->setFormTypeOptionIfNotSet('field_stores_multiple', $this->fieldStoresMultipleValues($fieldDto, $entityDto, $filterDto->getProperty()));
    }

    private function fieldStoresMultipleValues(?FieldDto $fieldDto, EntityDto $entityDto, string $property): bool
    {
        if (null !== $fieldDto) {
            $formTypeMultiple = $fieldDto->getFormTypeOption('multiple');
            if (null !== $formTypeMultiple) {
                return (bool) $formTypeMultiple;
            }

            $customOption = $fieldDto->getCustomOption(ChoiceField::OPTION_ALLOW_MULTIPLE_CHOICES);
            if (null !== $customOption) {
                return (bool) $customOption;
            }

            $doctrineType = $fieldDto->getDoctrineMetadata()->get('type');
            if (\is_string($doctrineType)) {
                $normalizedType = strtolower($doctrineType);
                if (\in_array($normalizedType, ['json', 'json_array', 'jsonb', 'simple_array', 'array'], true)) {
                    return true;
                }
            }
        }

        if ($entityDto->hasProperty($property)) {
            try {
                $normalizedType = strtolower((string) $entityDto->getPropertyMetadata($property)->get('type'));

                return \in_array($normalizedType, ['json', 'json_array', 'jsonb', 'simple_array', 'array'], true);
            } catch (\Throwable) {
                // ignore invalid metadata or association
            }
        }

        return false;
    }
}
