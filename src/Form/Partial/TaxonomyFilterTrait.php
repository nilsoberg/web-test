<?php

namespace App\Form\Partial;

use App\Form\Type\TaxonomyFilterType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

trait TaxonomyFilterTrait
{
    public function addTaxonomyFilterUi(FormBuilderInterface $builder, array $options, UrlGeneratorInterface $urlGenerator): void
    {
        $builder->add('taxSearch', TaxonomyFilterType::class, [
            'autocomplete_url' => $urlGenerator->generate('api_taxonomy_autocomplete'),
            'predefined_data' => [
                'bacteria_archaea_fungi' => [
                    'logic' => 'OR',
                    'rules' => [
                        ['field' => 'domain', 'operator' => '=', 'value' => 'Bacteria'],
                        ['field' => 'domain', 'operator' => '=', 'value' => 'Archaea'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Ascomycota'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Basidiomycota'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Fungi incertae sedis'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'unclassified Fungi'],
                    ],
                ],
                'fungi' => [
                    'logic' => 'OR',
                    'rules' => [
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Ascomycota'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Basidiomycota'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'Fungi incertae sedis'],
                        ['field' => 'phylum', 'operator' => '=', 'value' => 'unclassified Fungi'],
                    ],
                ],
                'eukaryota_no_fungi' => [
                    'logic' => 'AND',
                    'rules' => [
                        ['field' => 'domain',  'operator' => '=',        'value' => 'Eukaryota'],
                        ['field' => 'phylum',  'operator' => '!=',       'value' => 'Ascomycota'],
                        ['field' => 'phylum',  'operator' => '!=',       'value' => 'Basidiomycota'],
                        ['field' => 'phylum',  'operator' => '!=',       'value' => 'Fungi incertae sedis'],
                        ['field' => 'phylum',  'operator' => '!=',       'value' => 'unclassified Fungi'],
                        ['field' => 'species', 'operator' => 'NOT LIKE', 'value' => 'metagenomes'],
                    ],
                ],
                'viruses' => [
                    'logic' => 'AND',
                    'rules' => [['field' => 'domain', 'operator' => '=', 'value' => 'Viruses']]
                ],
                'bacteria' => [
                    'logic' => 'AND',
                    'rules' => [['field' => 'domain', 'operator' => '=', 'value' => 'Bacteria']]
                ],
                'archaea' => [
                    'logic' => 'AND',
                    'rules' => [['field' => 'domain', 'operator' => '=', 'value' => 'Archaea']]
                ],
                'eukaryota' => [
                    'logic' => 'AND',
                    'rules' => [['field' => 'domain', 'operator' => '=', 'value' => 'Eukaryota']]
                ],
            ],
		]);
    }
}
