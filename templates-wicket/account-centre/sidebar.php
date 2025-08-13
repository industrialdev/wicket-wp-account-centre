<?php
// No direct access
defined('ABSPATH') || exit;

/*
 * ACC navigation/sidebar template.
 *
 * Used on ACC pages and on dashboard.
 *
 * This template can be overridden (at theme level) by copying it to yourtheme/templates-wicket/account-centre/sidebar.php.
 */

global $wp;

$nav_heading = wp_get_nav_menu_name('wicket-acc-nav');
$nav_heading_two = wp_get_nav_menu_name('wicket-acc-nav-secondary');
$show_acc_secondary_nav = apply_filters('wicket/acc/show_secondary_nav', true);

if (WACC()->isWooCommerceActive()) {
    do_action('woocommerce_before_account_navigation');
}

do_action('wicket/acc/sidebar/before_account_navigation');
?>

<?php
if (WACC()->isWooCommerceActive()) {
    do_action('woocommerce_before_account_navigation_ul');
}

do_action('wicket/acc/sidebar/before_account_navigation_ul');
?>

<?php if (has_nav_menu('wicket-acc-nav')) : ?>
    <div class="hidden lg:block myaccount-nav">
        <div class="myaccount-nav__container">
            <?php
            if ($nav_heading) :
                $acc_url = home_url(WACC()->getAccSlug() . '/');
    ?>
                <h2 class="myaccount-nav-heading"><a
                        href="<?php echo esc_url($acc_url); ?>"><?php echo $nav_heading; ?></a>
                </h2>
            <?php endif; ?>
            <?php
    wp_nav_menu([
        'container'      => false,
        'theme_location' => 'wicket-acc-nav',
        'depth'          => 3,
        'menu_id'        => 'wicket-acc-menu',
        'menu_class'     => 'wicket-acc-menu',
        'walker'         => new wicket_acc_menu_walker(),
    ]);
?>
        </div>

        <?php if (has_nav_menu('wicket-acc-nav-secondary') && $show_acc_secondary_nav) : ?>
            <div class="myaccount-nav__container">
                <?php if ($nav_heading_two) :
                    $acc_url = home_url(WACC()->getAccSlug() . '/');
            ?>
                    <h2 class="myaccount-nav-heading"><a
                            href="<?php echo esc_url($acc_url); ?>"><?php echo $nav_heading_two; ?></a>
                    </h2>
                <?php endif; ?>
                <?php
            wp_nav_menu([
                'container'      => false,
                'theme_location' => 'wicket-acc-nav-secondary',
                'depth'          => 3,
                'menu_id'        => 'wicket-acc-menu-two',
                'menu_class'     => 'wicket-acc-menu-two',
                'walker'         => new wicket_acc_menu_walker(),
            ]);
?>
            </div>
        <?php endif; ?>
    </div>

    <div class="lg:w-1/3 lg:hidden myaccount-nav myaccount-nav-mobile">
        <div id="dropdown-my-account-menu" class="dropdown__content dropdown__content--nav"
            aria-labelledby="dropdown-control-my-account-menu" aria-expanded="false" role="region" style="display:none">
            <?php
wp_nav_menu([
    'container'      => false,
    'theme_location' => 'wicket-acc-nav',
    'depth'          => 3,
    'menu_id'        => 'wicket-acc-menu-mobile',
    'menu_class'     => 'wicket-acc-menu-mobile',
    'walker'         => new wicket_acc_menu_mobile_walker(),
]);
?>
        </div>
        <a href="#" id="dropdown-control-my-account-menu" class="dropdown__button dropdown__toggle dropdown__toggle--nav"
            aria-controls="dropdown-my-account-menu"
            aria-expanded="false"><?php echo $nav_heading; ?> <i
                class="fal fa-plus" aria-hidden="true"></i></a>
        <?php if (has_nav_menu('wicket-acc-nav-secondary') && $show_acc_secondary_nav) : ?>
            <div id="dropdown-my-account-menu-two" class="dropdown__content dropdown__content--nav"
                aria-labelledby="dropdown-control-my-account-menu" aria-expanded="false" role="region" style="display:none">
                <?php
    wp_nav_menu([
        'container'      => false,
        'theme_location' => 'wicket-acc-nav-secondary',
        'depth'          => 3,
        'menu_id'        => 'wicket-acc-menu-mobile-two',
        'menu_class'     => 'wicket-acc-menu-mobile-two',
        'walker'         => new wicket_acc_menu_mobile_walker(),
    ]);
            ?>
            </div>
            <a href="#" id="dropdown-control-my-account-menu" class="dropdown__button dropdown__toggle dropdown__toggle--nav"
                aria-controls="dropdown-my-account-menu-two"
                aria-expanded="false"><?php echo $nav_heading_two; ?> <i
                    class="fal fa-plus" aria-hidden="true"></i></a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
if (WACC()->isWooCommerceActive()) {
    do_action('woocommerce_after_account_navigation');
}

do_action('wicket/acc/sidebar/after_account_navigation');
