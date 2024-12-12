<?php

namespace WicketAcc;

// No direct access
defined('ABSPATH') || exit;

/*
 * Available $args[] variables:
 *
 * organization_id - Organization UUID
 * organization_info - Organization info
 * organization_memberships - Organization memberships
 */
?>
<section id="content" class="wicket-acc-organization-management section page-default">

    <div class="wicket-welcome-block bg-light-010 rounded-100 p-4 mb-4">
        <h2 class='font-bold text-lg black_header'>
            <?php echo $args['organization_info']['org_name']; ?>
        </h2>

        <?php if (!empty($args['organization_info']['org_address'])) : ?>
            <p class='formatted_address_label mb-4'>
                <?php echo $args['organization_info']['org_address']['formatted_address_label']; ?>
            </p>

            <p class="email_address mb-4">
            <h5 class="font-bold">
                <?php _e('Email Address', 'wicket') ?>
            </h5>
            <?php echo $args['organization_info']['org_email']['address']; ?>
            </p>

            <p class="phone_number mb-4">
            <h5 class="font-bold">
                <?php _e('Phone Number', 'wicket') ?>
            </h5>
            <?php echo $args['organization_info']['org_phone']['number_international_format']; ?>
            </p>
        <?php endif; ?>
    </div>

    <hr aria-hidden="true">

    <?php
    if (empty($args['organization_memberships'])) {
        ?>
        <p><?php _e('No memberships found', 'wicket'); ?>
        </p>
    <?php
    }
?>
</section>
