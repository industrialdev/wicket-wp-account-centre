/**
 * Attaches WP core's bundled CodeMirror (JSON mode, with jsonlint) to the
 * `mdp_json_config` ACF field on the AC Individual/Org Profile blocks, in the
 * block editor. Purely cosmetic: line numbers, syntax coloring, live lint,
 * and code folding on top of the plain `<textarea>` ACF already renders and
 * saves — the textarea stays the source of truth, and this script keeps its
 * value in sync via `.trigger('change')` so ACF's own saving is unaffected.
 * Falls back to the plain textarea if `wp_enqueue_code_editor()` returned
 * `false` (user has "Disable syntax highlighting when editing code" set).
 *
 * Deliberately excludes the legacy `mdp_json_fields`/`mdp_json_sections`
 * fields — see `wicket-acc-acf-field-deprecation.js` for why those stay
 * plain text.
 */
(function($) {
  if (typeof acf === 'undefined' || typeof wp === 'undefined' || !wp.codeEditor || typeof WicketAccJsonEditorSettings === 'undefined') {
    return;
  }

  if (!WicketAccJsonEditorSettings || Object.keys(WicketAccJsonEditorSettings).length === 0) {
    // wp_enqueue_code_editor() returned false (syntax highlighting disabled in the
    // user's profile) — fall back to the plain textarea, no CodeMirror.
    return;
  }

  // Legacy mdp_json_fields/mdp_json_sections intentionally stay plain textareas.
  var JSON_FIELD_NAMES = ['mdp_json_config'];

  function attachEditor(field) {
    if (!field || !field.$el || field.$el.data('wicketAccCmAttached')) {
      return;
    }
    var $textarea = field.$el.find('textarea').first();
    if (!$textarea.length) {
      return;
    }

    var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
    editorSettings.codemirror = _.extend({}, editorSettings.codemirror, WicketAccJsonEditorSettings.codemirror, {
      indentWithTabs: false,
      indentUnit: 2,
      foldGutter: true,
      gutters: (WicketAccJsonEditorSettings.codemirror.gutters || []).concat(['CodeMirror-foldgutter']),
    });

    var instance = wp.codeEditor.initialize($textarea, editorSettings);
    field.$el.data('wicketAccCmAttached', true);
    field.$el.data('wicketAccCmInstance', instance);

    instance.codemirror.on('change', function() {
      instance.codemirror.save();
      $textarea.trigger('change');
    });
  }

  function detachEditor(field) {
    if (!field || !field.$el || !field.$el.data('wicketAccCmAttached')) {
      return;
    }
    var instance = field.$el.data('wicketAccCmInstance');
    if (instance && instance.codemirror) {
      instance.codemirror.toTextArea();
    }
    field.$el.removeData('wicketAccCmAttached');
    field.$el.removeData('wicketAccCmInstance');
  }

  JSON_FIELD_NAMES.forEach(function(name) {
    acf.addAction('ready_field/name=' + name, attachEditor);
    acf.addAction('append_field/name=' + name, attachEditor);
    acf.addAction('remove_field/name=' + name, detachEditor);
  });
})(jQuery);
