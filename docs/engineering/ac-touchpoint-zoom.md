---
title: "Ac Touchpoint Zoom"
audience: [developer, agent]
php_class: WicketAcc
source_files: ["src/"]
---

# ACC Touchpoint Zoom Block

## Overview
The `ac-touchpoint-zoom` block displays Zoom Webinar registrations and attendance for the current user, fetched via the Wicket MDP Touchpoint API.

## Features
- Displays upcoming and past Zoom webinars.
- Real-time filtering based on event start date.
- "Load More" functionality via custom AJAX implementation.
- Customizable labels and display limits.

## ACF Configuration

| Field | Type | Description |
|       |      |             |
| `title` | `text` | Main block title. |
| `past_events_title` | `text` | Title for past webinars view. |
| `default_display` | `select` | Default view state (`upcoming`, `past`, `all`). |
| `registered_action` | `checkbox` | Zoom actions to display (e.g., `rsvp_to_event`, `attended_an_event`). |
| `page_results` | `number` | Initial number of webinars to display. |
| `show_view_more_events` | `boolean` | Enables the "Load More" button. |

## Technical Implementation
- **Class**: `WicketAcc\Blocks\TouchpointZoom\init`
- **Data Source**: MDP Touchpoint API (Service ID: `Zoom Webinars (1)`).
- **AJAX**: Uses `admin-ajax.php` with action `wicket_ac_touchpoint_zoom_results`.
- **Frontend**: Custom vanilla JS `loadMoreZoomResults` function for dynamic loading.

## Template Files
- `templates-wicket/blocks/touchpoint-zoom.php`: Main container.
- `templates-wicket/blocks/touchpoint-zoom-card.php`: Webinar card template."
