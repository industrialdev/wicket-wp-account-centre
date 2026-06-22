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
                'label'      => __('Company Attributes', 'wicket-acc'),
                'schema'     => 'urn:uuid:92112b20-bf2f-4939-a6ba-3c111ca96aeb',
                'field_key'  => 'orgcompattributes',
                'value_key'  => 'attributes',
                'other_key'  => 'attributesother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    '2slgbtqia-owned'        => __('2SLGBTQIA+ Owned', 'wicket-acc'),
                    'bipoc-owned'            => __('Black, Indigenous, Person of Colour Owned', 'wicket-acc'),
                    'quebec-based-business'  => __('Quebec Based Business', 'wicket-acc'),
                    'family-owned'           => __('Family Owned', 'wicket-acc'),
                    'made-in-canada'         => __('Made in Canada', 'wicket-acc'),
                    'women-owned'            => __('Women Owned', 'wicket-acc'),
                    'other'                  => __('Other', 'wicket-acc'),
                ]),
            ],
            'certifications'      => [
                'label'      => __('Certifications', 'wicket-acc'),
                'schema'     => 'urn:uuid:aa869490-1415-4dfa-8f25-1a590d841fe4',
                'field_key'  => 'orgcertifications',
                'value_key'  => 'certifications',
                'other_key'  => 'certificationsother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'carbon-neutral'                    => __('Carbon Neutral', 'wicket-acc'),
                    'certified-b-corp'                  => __('Certified B Corp', 'wicket-acc'),
                    'cruelty-free'                      => __('Cruelty Free', 'wicket-acc'),
                    'ecocert'                           => __('ECOCERT', 'wicket-acc'),
                    'fair-trade-certified'              => __('Fair Trade Certified', 'wicket-acc'),
                    'non-gmo-certified'                 => __('Non-GMO Certified', 'wicket-acc'),
                    'nsf-certified'                     => __('NSF Certified', 'wicket-acc'),
                    'organic-certified'                 => __('Organic Certified', 'wicket-acc'),
                    'regenerative-organic-certified'    => __('Regenerative Organic Certified', 'wicket-acc'),
                    'sustainably-sourced'              => __('Sustainably Sourced', 'wicket-acc'),
                    'other'                            => __('Other', 'wicket-acc'),
                ]),
            ],
            'business_services'   => [
                'label'      => __('Business Services', 'wicket-acc'),
                'schema'     => 'urn:uuid:63304035-7b3b-473e-8fb4-6b00f97e716d',
                'field_key'  => 'orgbusservice',
                'value_key'  => 'services',
                'other_key'  => 'servicesother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'certification-services'         => __('Certification Services', 'wicket-acc'),
                    'consulting-services'            => __('Consulting Services', 'wicket-acc'),
                    'contract-manufacturing'         => __('Contract Manufacturing', 'wicket-acc'),
                    'display-fixtures'               => __('Display Fixtures', 'wicket-acc'),
                    'financial-services-investment-firm' => __('Financial Services / Investment Firm', 'wicket-acc'),
                    'ingredients-raw-materials-supplier' => __('Ingredients & Raw Materials Supplier', 'wicket-acc'),
                    'legal-regulatory-services'      => __('Legal & Regulatory Services', 'wicket-acc'),
                    'marketing-advertising'          => __('Marketing & Advertising', 'wicket-acc'),
                    'packaging-labeling'             => __('Packaging & Labeling', 'wicket-acc'),
                    'quality-assurance-laboratory-testing' => __('Quality Assurance & Laboratory Testing', 'wicket-acc'),
                    'rd-formulation-flavouring'      => __('R&D, Formulation & Flavouring', 'wicket-acc'),
                    'research-data-services'         => __('Research & Data Services', 'wicket-acc'),
                    'retail-services'                => __('Retail Services', 'wicket-acc'),
                    'shipping-logistics'             => __('Shipping & Logistics', 'wicket-acc'),
                    'technology-solutions'           => __('Technology Solutions', 'wicket-acc'),
                    'other'                          => __('Other', 'wicket-acc'),
                ]),
            ],
            'product_segments'    => [
                'label'      => __('Product Segments', 'wicket-acc'),
                'schema'     => 'urn:uuid:868b4e5e-7b22-48cc-bb55-5a729cb89111',
                'field_key'  => 'orgprodsegment',
                'value_key'  => 'prodoptions',
                'other_key'  => 'segmentsother',
                'input_type' => 'checkbox',
                'options'    => $this->buildOptions([
                    'food-beverage'                               => __('Food & Beverage', 'wicket-acc'),
                    'personal-care-beauty'                        => __('Personal Care & Beauty', 'wicket-acc'),
                    'healthy-home-lifestyle'                      => __('Healthy Home & Lifestyle', 'wicket-acc'),
                    'natural-health-products-vitamin-herbal-supplements' => __('Natural Health Products, Vitamins & Herbal Supplements', 'wicket-acc'),
                    'pet-food-wellness-supplies'                   => __('Pet Food & Wellness Supplies', 'wicket-acc'),
                    'other'                                       => __('Other', 'wicket-acc'),
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

        $client = WACC()->MdpApi->init_client();

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
