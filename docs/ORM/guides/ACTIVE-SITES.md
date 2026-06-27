---
title: "Active Site Config Docs"
audience: [developer, implementer]
source_files: ["docs/engineering/configs/"]
---

# Active Site Config Docs

This folder contains documentation snapshots of active site override configurations. These files are not runtime configuration, but they are intended to mirror the real site override files for sites currently using the org-roster feature (`WicketORM\`).

## Current Site Mappings

- `docs/engineering/configs/CSAE.md`
  - source of truth: `../csae-portal-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`
  - `direct` strategy
- `docs/engineering/configs/CCHL.md`
  - source of truth: `../cchl-website-wordpress/src/web/app/themes/industrial/custom/org-roster.php`
  - `direct` strategy
- `docs/engineering/configs/ESCRS.md`
  - source of truth: `../escrs-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`
  - `membership_cycle` strategy
- `docs/engineering/configs/IAA.md`
  - source of truth: `../iaa-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`
  - `groups` strategy
- `docs/engineering/configs/MSA.md`
  - source of truth: `../msa-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`
  - `cascade` strategy
- `docs/engineering/configs/NJBIA.md`
  - source of truth: `../njbia-website-wordpress/src/wp-content/themes/njbia/theme/inc/org-roster.php`
  - `cascade` strategy
- `docs/engineering/configs/PACE.md`
  - source of truth: `../pace-website-wordpress/src/web/app/themes/wicket-child/custom/org-roster.php`
  - `cascade` strategy

## Important Rule

These files are manually maintained documentation. The library does not load them, validate them, or synchronize them automatically with external site repositories. When a site override changes, update the matching file in `docs/engineering/configs/` to keep the documentation aligned with the real override.
