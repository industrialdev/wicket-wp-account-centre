---
title: "Ac Touchpoint Moodle"
audience: [developer, agent]
php_class: Wicket_ACC_Main
source_files: ["src/"]
---

# ACC Touchpoint Moodle Block

## Overview
The `ac-touchpoint-moodle` block displays Moodle course enrollments and completions for the current user, fetched via the Wicket MDP Touchpoint API.

## Features
- Displays upcoming, past, or all Moodle-related touchpoints.
- Configurable "Show More" functionality via AJAX.
- Filtering by specific Moodle actions (e.g., enrolled, completed).
- Support for grid/column layouts.

## ACF Configuration

| Field | Type | Description |
|       |      |             |
| `title` | `text` | Main block title. |
| `past_events_title` | `text` | Title shown when viewing past events. |
| `default_display` | `select` | Default view: `upcoming`, `past`, or `all`. |
| `registered_action` | `checkbox` | List of Moodle action codes to include (e.g., `enrolled_in_a_course`). |
| `page_results` | `number` | Number of items to show per "page" before "Show More". |
| `show_switch_view_link` | `boolean` | Toggle link to switch between Upcoming/Past views. |

## Technical Implementation
- **Class**: `WicketAcc\Blocks\TouchpointMoodle\init`
- **Data Source**: MDP Touchpoint API (Service ID: `Moodle`).
- **AJAX**: Uses `admin-ajax.php` with action `wicket_ac_touchpoint_moodle_results`.
- **Frontend**: Uses Alpine.js for the "Show More" interaction.

## Template Files
- `templates-wicket/blocks/touchpoint-moodle.php`: Main wrapper.
- `templates-wicket/blocks/touchpoint-moodle-card.php`: Individual item display."
