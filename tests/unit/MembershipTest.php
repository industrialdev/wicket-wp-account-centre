<?php

declare(strict_types=1);

namespace WicketAcc\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use WicketAcc\Mdp\Membership;

#[CoversClass(Membership::class)]
class MembershipTest extends AbstractTestCase
{
    public function test_get_current_person_renewal_end_timestamp_uses_mdp_only(): void
    {
        $membership = new class extends Membership {
            public function __construct() {}

            protected function getMdpMembershipRenewalEndTimestamp(?string $person_uuid): ?int
            {
                return strtotime('2026-06-12T00:00:00-04:00');
            }
        };

        $result = $membership->getCurrentPersonRenewalEndTimestamp([
            'user_id' => 42,
            'person_uuid' => 'person-uuid',
        ]);

        $this->assertSame(strtotime('2026-06-12T00:00:00-04:00'), $result);
    }

    public function test_get_current_person_renewal_end_timestamp_returns_null_when_mdp_has_no_date(): void
    {
        $membership = new class extends Membership {
            public function __construct() {}

            protected function getMdpMembershipRenewalEndTimestamp(?string $person_uuid): ?int
            {
                return null;
            }
        };

        $result = $membership->getCurrentPersonRenewalEndTimestamp([
            'user_id' => 42,
            'person_uuid' => 'person-uuid',
        ]);

        $this->assertNull($result);
    }

    public function test_get_person_max_end_date_from_entries_uses_delayed_mdp_membership(): void
    {
        $membership = new class extends Membership {
            public function __construct() {}

            public function getCurrentPersonMemberships(array $args = []): array|false
            {
                return [
                    'data' => [
                        [
                            'attributes' => [
                                'status' => 'Active',
                                'ends_at' => '2026-03-20T00:00:00-04:00',
                            ],
                        ],
                        [
                            'attributes' => [
                                'status' => 'Delayed',
                                'ends_at' => '2027-03-22T00:00:00-04:00',
                            ],
                        ],
                        [
                            'attributes' => [
                                'status' => 'Inactive',
                                'ends_at' => '2028-03-22T00:00:00-04:00',
                            ],
                        ],
                    ],
                ];
            }
        };

        $result = $membership->getPersonMaxEndDateFromEntries('person-uuid');

        $this->assertSame('2027-03-22T00:00:00-04:00', $result);
    }

    public function test_get_current_person_renewal_end_timestamp_by_type_uses_mdp_only(): void
    {
        $membership = new class extends Membership {
            public function __construct() {}

            protected function getMdpMembershipRenewalEndTimestampByType(string $membership_type, ?string $person_uuid): ?int
            {
                return match ($membership_type) {
                    'individual' => strtotime('2027-03-10T00:00:00-04:00'),
                    'organization' => strtotime('2027-02-03T00:00:00-04:00'),
                    default => null,
                };
            }
        };

        $individual_result = $membership->getCurrentPersonRenewalEndTimestampByType('individual', [
            'user_id' => 42,
            'person_uuid' => 'person-uuid',
        ]);

        $organization_result = $membership->getCurrentPersonRenewalEndTimestampByType('organization', [
            'user_id' => 42,
            'person_uuid' => 'person-uuid',
        ]);

        $this->assertSame(strtotime('2027-03-10T00:00:00-04:00'), $individual_result);
        $this->assertSame(strtotime('2027-02-03T00:00:00-04:00'), $organization_result);
    }
}
