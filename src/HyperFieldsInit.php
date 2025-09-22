<?php

namespace WicketAcc;

use HyperPress\Fields\HyperFields;
use HyperPress\Fields\Field;

// No direct access
defined("ABSPATH") || exit();

/**
 * HyperPress HyperFields Init Class.
 */
class HyperFieldsInit extends WicketAcc
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        // Debug: Check if we reach here
        error_log("HyperFieldsInit constructor called");

        // Register HyperFields options page
        add_action("init", [$this, "mainACCHyperFieldsOptionsPage"]);
    }

    /**
     * Create the plugin options page with HyperFields.
     */
    public function mainACCHyperFieldsOptionsPage()
    {
        error_log("HyperFieldsInit mainACCHyperFieldsOptionsPage called");

        if (!class_exists(HyperFields::class)) {
            error_log("HyperFields class does not exist");
            return;
        }

        error_log("HyperFields class exists, creating options page");

        // Create a simple options page for testing
        $options = HyperFields::makeOptionPage(
            "ACC HyperFields Options",
            "wicket-acc-options",
        )
            ->setMenuTitle("ACC HyperFields Options")
            ->setParentSlug("edit.php?post_type=my-account");

        // Add section and field separately
        $section = $options->addSection("main_options", "Main Options");
        $section->addField(
            HyperFields::makeField("text", "test_field", "Test Field"),
        );

        error_log("About to register options page");
        $options->register();
        error_log("Options page registered successfully");
    }

    /**
     * Get the API connection status HTML.
     *
     * @return string
     */
    public function get_api_status_html()
    {
        ob_start();

        // Use the plugin's internal method to check the connection.
        // initClient() returns false on failure, or the client object on success.
        $client = WACC()->Mdp()->initClient();
        $can_connect = $client !== false;
        ?>
        <div style="display: flex; align-items: center; padding: 10px 0;">
            <label style="margin-right: 10px; font-weight: bold;"><?php echo __(
                "Status",
                "wicket",
            ); ?></label>
            <?php if ($can_connect): ?>
                <span style="background-color: #7ad03a; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo __(
                    "CONNECTED",
                    "wicket",
                ); ?></span>
            <?php else: ?>
                <span style="background-color: #dc3232; color: white; padding: 5px 10px; border-radius: 3px;"><?php echo __(
                    "NOT CONNECTED",
                    "wicket",
                ); ?></span>
            <?php endif; ?>
        </div>
        <?php return ob_get_clean();
    }
}
