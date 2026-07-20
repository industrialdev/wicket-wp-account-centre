/**
 * TEMPORARY MIGRATION SCAFFOLDING — legacy MDP field migration for the AC
 * Individual/Org Profile blocks. Safe to delete, file and all, once no block
 * anywhere has a populated legacy `mdp_json_fields`/`mdp_json_sections` value
 * left to migrate. Nothing else in either block depends on this file — see
 * `includes/blocks/ac-individual-profile/init.php` and
 * `includes/blocks/ac-org-profile/init.php`, which already fall back to the
 * legacy fields correctly with zero help from this script.
 *
 * The `mdp_json_fields` / `mdp_json_sections` ACF fields predate the newer
 * open-ended `mdp_json_config` field (see the widget-config refactor plan).
 * They still work — a caller with only the legacy fields populated renders
 * exactly as before — but they're deprecated: `mdp_json_config` supersedes
 * them and, once set, they're ignored entirely (not merged). New editors
 * should use `mdp_json_config` only.
 *
 * This script exists purely to make that migration painless for editors who
 * already have legacy values saved: it injects a `Replace "fields"/"sections"
 * in MDP Widget Config` link under each legacy field. Clicking it JSON-parses
 * the legacy value and writes it under the right key (`fields` or `sections`)
 * into whatever's already in `mdp_json_config`, replacing any existing value
 * under that key wholesale (not merging it), then clears the legacy field.
 * It also hides each legacy field once its value is empty,
 * so old blocks that never used them (or have already been migrated) don't
 * show dead, empty settings to editors.
 *
 * None of this is enforced server-side — a block with both legacy and config
 * values populated will still render via `mdp_json_config` only (see
 * `includes/blocks/ac-individual-profile/init.php` and
 * `includes/blocks/ac-org-profile/init.php`). This script is pure editor-UX
 * sugar on top of that already-existing precedence rule.
 *
 * Note: `mdp_json_sections` on the Org Profile block is currently inert — the
 * org profile widget component has no `sections` arg, so a value migrated
 * into `mdp_json_config`'s `sections` key has no effect on render today. The
 * migrate link is still offered so a previously-saved value isn't stranded:
 * if the org widget ever grows section support, migrated values are already
 * in the right place under `mdp_json_config`.
 */
(function($) {
  if (typeof acf === 'undefined') {
    return;
  }

  // Legacy field name -> wrapper key in the MDP Widget Config JSON object.
  var LEGACY_WRAP_KEYS = {
    mdp_json_fields: 'fields',
    mdp_json_sections: 'sections',
  };

  function findSiblingField(field, name) {
    if (!field || !field.$el) {
      return null;
    }
    var $wrapper = field.$el.closest('.acf-fields');
    if (!$wrapper.length) {
      return null;
    }
    var found = null;
    acf.getFields({ name: name }).forEach(function(f) {
      if ($wrapper.is(f.$el.closest('.acf-fields'))) {
        found = f;
      }
    });
    return found;
  }

  function ensureMigrateLink(legacyField, legacyName, wrapKey) {
    var $input = legacyField.$el.find('.acf-input').first();
    if (!$input.length) {
      return;
    }
    // Always rebuild: a stale link left over from a prior render (detached
    // DOM ACF replaced) would otherwise pass an existence check while having
    // no working click handler on the currently-visible element.
    $input.find('.wicket-acc-mdp-migrate-link').remove();
    var $link = jQuery('<p style="margin-top: 4px;"><a href="#" class="wicket-acc-mdp-migrate-link">Replace "' + wrapKey + '" in MDP Widget Config &rarr;</a></p>');
    $input.append($link);

    $link.find('a').on('click', function(e) {
      e.preventDefault();

      // Re-resolve both fields at click time rather than trusting closures
      // captured when the link was created — ACF can tear down and recreate
      // field instances (e.g. block deselect/reselect) between then and now.
      try {
        var currentLegacyField = acf.getField(legacyField.$el) || legacyField;
        var currentConfigField = findSiblingField(currentLegacyField, 'mdp_json_config');

        if (!currentConfigField) {
          return;
        }

        var legacyVal = currentLegacyField.val();
        if (!legacyVal || !String(legacyVal).trim()) {
          return;
        }

        var parsed;
        try {
          parsed = JSON.parse(legacyVal);
        } catch (err) {
          return;
        }

        var existingConfigVal = currentConfigField.val();
        var configObj = {};
        if (existingConfigVal && String(existingConfigVal).trim()) {
          try {
            configObj = JSON.parse(existingConfigVal);
          } catch (err) {
            configObj = {};
          }
        }

        configObj[wrapKey] = parsed;

        var wrappedStr = JSON.stringify(configObj, null, 2);
        currentConfigField.val(wrappedStr);
        currentConfigField.$el.find('textarea').trigger('change').trigger('input');

        // If WP-core CodeMirror is attached to this field (see
        // wicket-acc-acf-json-editor.js), it shadows the raw textarea with its
        // own rendered view/buffer — writing to the textarea alone does not
        // update what's visually displayed. Push the value into CodeMirror too.
        var cmInstance = currentConfigField.$el.data('wicketAccCmInstance');
        if (cmInstance && cmInstance.codemirror) {
          cmInstance.codemirror.setValue(wrappedStr);
          cmInstance.codemirror.refresh();
        }

        // Purge the legacy field now that its value has been migrated —
        // both the raw value (so nothing stale gets saved) and, if
        // CodeMirror is attached, its shadow view.
        currentLegacyField.val('');
        currentLegacyField.$el.find('textarea').trigger('change').trigger('input');
        var legacyCmInstance = currentLegacyField.$el.data('wicketAccCmInstance');
        if (legacyCmInstance && legacyCmInstance.codemirror) {
          legacyCmInstance.codemirror.setValue('');
          legacyCmInstance.codemirror.refresh();
        }

        hideLegacyFieldIfEmpty(currentLegacyField);
      } catch (outerErr) {
        window.console && console.error('Wicket ACC: unexpected error migrating legacy MDP field value', outerErr);
      }
    });
  }

  function hideLegacyFieldIfEmpty(legacyField) {
    var val = legacyField.val();
    if (!val || !String(val).trim()) {
      legacyField.$el.hide();
    } else {
      legacyField.$el.show();
    }
  }

  function handleLegacyField(legacyField, legacyName) {
    ensureMigrateLink(legacyField, legacyName, LEGACY_WRAP_KEYS[legacyName]);
    // Covers the post-save/reload case: once the legacy value was purged and
    // saved, the field loads empty on the next page load and should stay hidden.
    hideLegacyFieldIfEmpty(legacyField);
  }

  Object.keys(LEGACY_WRAP_KEYS).forEach(function(legacyName) {
    acf.addAction('ready_field/name=' + legacyName, function(legacyField) {
      handleLegacyField(legacyField, legacyName);
    });
    acf.addAction('append_field/name=' + legacyName, function(legacyField) {
      handleLegacyField(legacyField, legacyName);
    });
  });
})(jQuery);
