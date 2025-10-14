<?php

namespace EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type;

use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ChoiceFilterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $multiple = (bool) $builder->get('value')->getOption('multiple');
        $fieldStoresMultiple = (bool) $options['field_stores_multiple'];

        // if the filter shows the values as checkboxes or radio buttons, remove the
        // attribute that turns the <select> into an autocomplete widget
        if (true === ($options['value_type_options']['expanded'] ?? false)) {
            unset($options['value_type_options']['attr']['data-ea-widget']);
        }

        $builder->addModelTransformer(new CallbackTransformer(
            static fn ($data) => $data,
            static function ($data) use ($multiple, $fieldStoresMultiple) {
                // symfony form will cut off invalid values, so make sure no warnings will be thrown out:
                $data['value'] ??= null;
                $data['comparison'] = self::normalizeComparison($data['comparison'], $multiple, $fieldStoresMultiple);

                switch ($data['comparison']) {
                    case ComparisonType::EQ:
                        if (null === $data['value'] || ($multiple && 0 === \count($data['value']))) {
                            $data['comparison'] = 'IS NULL';
                        } else {
                            $data['comparison'] = $multiple ? 'IN' : '=';
                        }
                        break;
                    case ComparisonType::NEQ:
                        if (null === $data['value'] || ($multiple && 0 === \count($data['value']))) {
                            $data['comparison'] = 'IS NOT NULL';
                        } else {
                            $data['comparison'] = $multiple ? 'NOT IN' : '!=';
                        }
                        break;
                    case ComparisonType::CONTAINS_ALL:
                        if (null === $data['value'] || ($multiple && 0 === \count($data['value']))) {
                            $data['comparison'] = 'IS NULL';
                            $data['value'] = $multiple ? [] : null;
                        } else {
                            if ($multiple) {
                                $data['value'] = array_values((array) $data['value']);
                            }

                            $data['comparison'] = ComparisonType::CONTAINS_ALL;
                        }
                        break;
                    case ComparisonType::CONTAINS:
                        if (null === $data['value'] || ($multiple && 0 === \count($data['value']))) {
                            $data['comparison'] = 'IS NULL';
                            $data['value'] = $multiple ? [] : null;
                        } else {
                            if ($multiple) {
                                $data['value'] = array_values((array) $data['value']);
                            }
                            $data['comparison'] = ComparisonType::CONTAINS;
                        }
                        break;
                    case ComparisonType::NOT_CONTAINS:
                        if (null === $data['value'] || ($multiple && 0 === \count($data['value']))) {
                            $data['comparison'] = 'IS NOT NULL';
                            $data['value'] = $multiple ? [] : null;
                        } else {
                            if ($multiple) {
                                $data['value'] = array_values((array) $data['value']);
                            }
                            $data['comparison'] = ComparisonType::NOT_CONTAINS;
                        }
                        break;
                }

                return $data;
            }
        ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'comparison_type_options' => ['type' => 'choice'],
            'value_type' => ChoiceType::class,
            'value_type_options' => [
                'multiple' => false,
                'attr' => [
                    'data-ea-widget' => 'ea-autocomplete',
                ],
            ],
            'field_stores_multiple' => false,
        ]);
        $resolver->setNormalizer('value_type_options', static function (Options $options, $value) {
            if (!isset($value['attr'])) {
                $value['attr']['data-ea-widget'] = 'ea-autocomplete';
            }

            return $value;
        });
        $resolver->setNormalizer('comparison_type_options', static function (Options $options, $value) {
            $value['type'] ??= 'choice';

            if (isset($value['choices'])) {
                return $value;
            }

            $filterIsMultiple = (bool) ($options['value_type_options']['multiple'] ?? false);
            $fieldStoresMultiple = (bool) $options['field_stores_multiple'];

            $value['choices'] = self::resolveAvailableComparisons($filterIsMultiple, $fieldStoresMultiple);

            return $value;
        });
        $resolver->setAllowedTypes('field_stores_multiple', 'bool');
    }

    public function getParent(): string
    {
        return ComparisonFilterType::class;
    }

    /**
     * @return array<string, string>
     */
    private static function resolveAvailableComparisons(bool $filterIsMultiple, bool $fieldStoresMultiple): array
    {
        if ($filterIsMultiple && $fieldStoresMultiple) {
            return [
                'filter.label.contains_one_of' => ComparisonType::CONTAINS,
                'filter.label.contains_all' => ComparisonType::CONTAINS_ALL,
                'filter.label.does_not_contain_any_of' => ComparisonType::NOT_CONTAINS,
            ];
        }

        if ($filterIsMultiple) {
            return [
                'filter.label.contains_one_of' => ComparisonType::CONTAINS,
                'filter.label.does_not_contain_any_of' => ComparisonType::NOT_CONTAINS,
            ];
        }

        if ($fieldStoresMultiple) {
            return [
                'filter.label.contains' => ComparisonType::CONTAINS,
                'filter.label.not_contains' => ComparisonType::NOT_CONTAINS,
            ];
        }

        return [
            'filter.label.is_same' => ComparisonType::EQ,
            'filter.label.is_not_same' => ComparisonType::NEQ,
        ];
    }

    private static function normalizeComparison(string $comparison, bool $filterIsMultiple, bool $fieldStoresMultiple): string
    {
        if (\in_array($comparison, ['IN', '='], true)) {
            $comparison = ComparisonType::EQ;
        } elseif (\in_array($comparison, ['NOT IN', '!='], true)) {
            $comparison = ComparisonType::NEQ;
        }

        if ($filterIsMultiple && $fieldStoresMultiple) {
            if (ComparisonType::EQ === $comparison) {
                return ComparisonType::CONTAINS;
            }

            if (ComparisonType::NEQ === $comparison) {
                return ComparisonType::NOT_CONTAINS;
            }

            return $comparison;
        }

        if ($filterIsMultiple && !$fieldStoresMultiple) {
            if (ComparisonType::EQ === $comparison) {
                return ComparisonType::CONTAINS;
            }

            if (ComparisonType::NEQ === $comparison) {
                return ComparisonType::NOT_CONTAINS;
            }

            return $comparison;
        }

        if (!$filterIsMultiple && $fieldStoresMultiple) {
            if (ComparisonType::EQ === $comparison) {
                return ComparisonType::CONTAINS;
            }

            if (ComparisonType::NEQ === $comparison) {
                return ComparisonType::NOT_CONTAINS;
            }

            return $comparison;
        }

        return $comparison;
    }
}
