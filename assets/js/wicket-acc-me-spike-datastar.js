/**
 * M3 spike: minimal glue for the manage-employer Datastar path.
 *
 * Datastar has no success-event primitive for HTML fragment responses: the
 * `datastar-fetch` event carries only started/finished/error, so this closes
 * the add dialog when a fetch started from that button finishes without an
 * error. Flags are tracked per button so an interleaved search request cannot
 * reset the add's failure state. Throwaway spike code (M3 decision gate); the
 * M4 list UI replaces it.
 */
(function () {
	'use strict';

	var failed = new WeakMap();

	document.addEventListener('datastar-fetch', function (evt) {
		var el = evt.detail && evt.detail.el;

		if (!el || !el.classList || !el.classList.contains('me-spike__add')) {
			return;
		}

		var type = evt.detail.type;

		if (type === 'started') {
			failed.set(el, false);
			return;
		}

		if (type === 'error' || type === 'retries-failed') {
			failed.set(el, true);
			return;
		}

		if (type !== 'finished' || failed.get(el)) {
			return;
		}

		document.querySelectorAll('dialog.me-spike-dialog[open]').forEach(function (dialog) {
			dialog.close();
		});
	});
})();
