<?php

/**
 * Business information service.
 */

namespace WicketORM\Services;

use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles retrieval and updates for organization business information.
 */
class BusinessInfoService
{
    /**
     * Section configuration for business information categories.
     *
     * @var array
     */
    private $sections = [];

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->sections = [
            'company_attributes' => [
                'label'      => _x('Company Attributes', 'label', 'wicket-acc'),
                'schema'     => 'urn:uuid:92112b20-bf2f-4939-a6ba-3c111ca96aeb',
                'field_key'  => 'orgcompattributes',
                'value_key'  => 'attributes',
                'other_key'  => 'attributesother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    '2slgbtqia-owned'        => _x('2SLGBTQIA+ Owned', 'label', 'wicket-acc'),
                    'bipoc-owned'            => __('Black, Indigenous, Person of Colour Owned', 'wicket-acc'),
                    'quebec-based-business'  => _x('Quebec Based Business', 'label', 'wicket-acc'),
                    'family-owned'           => _x('Family Owned', 'label', 'wicket-acc'),
                    'made-in-canada'         => _x('Made in Canada', 'label', 'wicket-acc'),
                    'women-owned'            => _x('Women Owned', 'label', 'wicket-acc'),
                    /* translators: Business ownership option. */
                    'other'                  => _x('Other', 'label', 'wicket-acc'),
                ]),
            ],
            'certifications'      => [
                'label'      => _x('Certifications', 'label', 'wicket-acc'),
                'schema'     => 'urn:uuid:aa869490-1415-4dfa-8f25-1a590d841fe4',
                'field_key'  => 'orgcertifications',
                'value_key'  => 'certifications',
                'other_key'  => 'certificationsother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'carbon-neutral'                    => _x('Carbon Neutral', 'label', 'wicket-acc'),
                    'certified-b-corp'                  => _x('Certified B Corp', 'label', 'wicket-acc'),
                    'cruelty-free'                      => _x('Cruelty Free', 'label', 'wicket-acc'),
                    /* translators: Certification brand name; usually left untranslated. */
                    'ecocert'                           => _x('ECOCERT', 'label', 'wicket-acc'),
                    'fair-trade-certified'              => _x('Fair Trade Certified', 'label', 'wicket-acc'),
                    /* translators: GMO is short for genetically modified organism. */
                    'non-gmo-certified'                 => _x('Non-GMO Certified', 'label', 'wicket-acc'),
                    /* translators: NSF is a certification body (NSF International); keep the name. */
                    'nsf-certified'                     => _x('NSF Certified', 'label', 'wicket-acc'),
                    'organic-certified'                 => _x('Organic Certified', 'label', 'wicket-acc'),
                    'regenerative-organic-certified'    => _x('Regenerative Organic Certified', 'label', 'wicket-acc'),
                    'sustainably-sourced'              => _x('Sustainably Sourced', 'label', 'wicket-acc'),
                    /* translators: Certification option. */
                    'other'                            => _x('Other', 'label', 'wicket-acc'),
                ]),
            ],
            'business_services'   => [
                'label'      => _x('Business Services', 'label', 'wicket-acc'),
                'schema'     => 'urn:uuid:63304035-7b3b-473e-8fb4-6b00f97e716d',
                'field_key'  => 'orgbusservice',
                'value_key'  => 'services',
                'other_key'  => 'servicesother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'certification-services'         => _x('Certification Services', 'label', 'wicket-acc'),
                    'consulting-services'            => _x('Consulting Services', 'label', 'wicket-acc'),
                    'contract-manufacturing'         => _x('Contract Manufacturing', 'label', 'wicket-acc'),
                    'display-fixtures'               => _x('Display Fixtures', 'label', 'wicket-acc'),
                    'financial-services-investment-firm' => __('Financial Services / Investment Firm', 'wicket-acc'),
                    'ingredients-raw-materials-supplier' => __('Ingredients & Raw Materials Supplier', 'wicket-acc'),
                    'legal-regulatory-services'      => _x('Legal & Regulatory Services', 'label', 'wicket-acc'),
                    'marketing-advertising'          => _x('Marketing & Advertising', 'label', 'wicket-acc'),
                    'packaging-labeling'             => _x('Packaging & Labelling', 'label', 'wicket-acc'),
                    'quality-assurance-laboratory-testing' => __('Quality Assurance & Laboratory Testing', 'wicket-acc'),
                    'rd-formulation-flavouring'      => __('R&D, Formulation & Flavouring', 'wicket-acc'),
                    'research-data-services'         => _x('Research & Data Services', 'label', 'wicket-acc'),
                    'retail-services'                => _x('Retail Services', 'label', 'wicket-acc'),
                    'shipping-logistics'             => _x('Shipping & Logistics', 'label', 'wicket-acc'),
                    'technology-solutions'           => _x('Technology Solutions', 'label', 'wicket-acc'),
                    /* translators: Business services option. */
                    'other'                          => _x('Other', 'label', 'wicket-acc'),
                ]),
            ],
            'product_segments'    => [
                'label'      => _x('Product Segments', 'label', 'wicket-acc'),
                'schema'     => 'urn:uuid:868b4e5e-7b22-48cc-bb55-5a729cb89111',
                'field_key'  => 'orgprodsegment',
                'value_key'  => 'prodoptions',
                'other_key'  => 'segmentsother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'food-beverage'                               => _x('Food & Beverage', 'label', 'wicket-acc'),
                    'personal-care-beauty'                        => _x('Personal Care & Beauty', 'label', 'wicket-acc'),
                    'healthy-home-lifestyle'                      => _x('Healthy Home & Lifestyle', 'label', 'wicket-acc'),
                    'natural-health-products-vitamin-herbal-supplements' => __('Natural Health Products, Vitamins & Herbal Supplements', 'wicket-acc'),
                    'pet-food-wellness-supplies'                   => __('Pet Food & Wellness Supplies', 'wicket-acc'),
                    /* translators: Business option. */
                    'other'                                       => _x('Other', 'label', 'wicket-acc'),
                ]),
            ],
        ];
    }

    /**
     * Build option definitions.
     *
     * @param array $labels Map of API values to labels.
     * @return array
     */
    private function buildOptions(array $labels)
    {
        $options = [];

        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'slug'  => sanitize_title($value),
                'is_other' => ('other' === $value),
            ];
        }

        return $options;
    }

    /**
     * Fetch organization metadata for rendering the page header.
     *
     * @param string $org_id Organization UUID.
     * @return array
     */
    public function getOrganizationHeader($org_id)
    {
        $default = [
            'name'    => '',
            'address' => '',
            'email'   => '',
            'phone'   => '',
        ];

        if (empty($org_id) || !function_exists('wicket_get_organization')) {
            return $default;
        }

        $org = wicket_get_organization($org_id);

        if (!isset($org['data'])) {
            return $default;
        }

        $attributes = $org['data']['attributes'] ?? [];
        $address = $attributes['formatted_address_label'] ?? '';
        $emails = $attributes['emails'] ?? [];
        $phones = $attributes['phones'] ?? [];

        return [
            'name'    => $attributes['legal_name_en'] ?? '',
            'address' => is_string($address) ? $address : '',
            'email'   => is_array($emails) && !empty($emails) ? ($emails[0]['address'] ?? '') : '',
            'phone'   => is_array($phones) && !empty($phones) ? ($phones[0]['number_international_format'] ?? '') : '',
        ];
    }

    /**
     * Get current selections for each configured section.
     *
     * @param string $org_id Organization UUID.
     * @return array
     */
    public function getSectionsState($org_id)
    {
        $state = [];

        foreach ($this->sections as $key => $section) {
            $state[$key] = [
                'values' => [],
                'other'  => '',
            ];
        }

        if (empty($org_id) || !function_exists('wicket_get_organization')) {
            return $state;
        }

        $org = wicket_get_organization($org_id);
        $data_fields = $org['data']['attributes']['data_fields'] ?? [];

        foreach ($data_fields as $field) {
            $field_key = $field['key'] ?? '';

            foreach ($this->sections as $section_key => $section) {
                if ($section['field_key'] !== $field_key) {
                    continue;
                }

                $values = $field['value'][$section['value_key']] ?? [];
                $values = is_array($values) ? array_map('sanitize_text_field', $values) : [];

                $other = $field['value'][$section['other_key']] ?? '';

                $state[$section_key] = [
                    'values' => $values,
                    'other'  => is_string($other) ? $other : '',
                ];
            }
        }

        return $state;
    }

    /**
     * Persist updated selections to the MDP API.
     *
     * @param string $org_id Organization UUID.
     * @param array  $payload Posted payload.
     *
     * @return array|WP_Error
     */
    public function updateSections($org_id, array $payload)
    {
        if (empty($org_id)) {
            return new WP_Error('missing_org', __('Organization ID is required.', 'wicket-acc'));
        }

        if (!function_exists('WACC')) {
            return new WP_Error('missing_client', __('MDP client is not available.', 'wicket-acc'));
        }

        $client = WACC()->Mdp()->init_client();

        $results = [];

        foreach ($this->sections as $section_key => $section) {
            $sanitized = $this->sanitizeSectionPayload($section_key, $section, $payload);

            $request_payload = [
                'data_fields' => [
                    [
                        '$schema' => $section['schema'],
                        'value'   => $sanitized,
                    ],
                ],
            ];

            $response = $this->patchSection($client, $org_id, $request_payload);

            if (is_wp_error($response)) {
                return $response;
            }

            $results[$section_key] = $response;
        }

        return $results;
    }

    /**
     * Sanitize payload for a single section before sending it to the API.
     *
     * @param string $section_key Section key.
     * @param array  $section_config Section configuration.
     * @param array  $payload Posted data.
     *
     * @return array
     */
    private function sanitizeSectionPayload($section_key, array $section_config, array $payload)
    {
        $values_key = $section_key;
        $other_key = $section_key . '_other';

        $allowed_values = wp_list_pluck($section_config['options'], 'value');
        $raw_values = isset($payload[$values_key]) ? (array) $payload[$values_key] : [];

        $values = [];
        foreach ($raw_values as $value) {
            $value = sanitize_text_field(wp_unslash($value));

            if (in_array($value, $allowed_values, true)) {
                $values[] = $value;
            }
        }

        $other_value = '';
        if (isset($payload[$other_key])) {
            $other_value = sanitize_text_field(wp_unslash($payload[$other_key]));
        }

        $data = [
            $section_config['value_key'] => array_values(array_unique($values)),
        ];

        if (!empty($section_config['other_key'])) {
            $data[$section_config['other_key']] = $other_value;
        }

        return $data;
    }

    /**
     * Issue a PATCH request for a single section.
     *
     * @param mixed  $client Guzzle client from WACC.
     * @param string $org_id Organization UUID.
     * @param array  $section_payload Section payload.
     *
     * @return array|WP_Error
     */
    private function patchSection($client, $org_id, array $section_payload)
    {
        $payload = [
            'data' => [
                'type'       => 'organizations',
                'id'         => (string) $org_id,
                'attributes' => [
                    'data_fields' => $section_payload['data_fields'],
                ],
            ],
        ];

        try {
            $response = $client->patch('organizations/' . $org_id, ['json' => $payload]);

            if (is_array($response) && isset($response['data'])) {
                return $response['data'];
            }

            if (is_object($response) && method_exists($response, 'getStatusCode') && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                return ['status' => 'ok'];
            }

            return new WP_Error('business_info_update_failed', __('Unexpected response from the MDP API.', 'wicket-acc'));
        } catch (\Exception $e) {
            return new WP_Error('business_info_exception', $e->getMessage());
        }
    }

    /**
     * Provide section configuration data.
     *
     * @return array
     */
    public function getSectionsConfig()
    {
        return $this->sections;
    }
}
