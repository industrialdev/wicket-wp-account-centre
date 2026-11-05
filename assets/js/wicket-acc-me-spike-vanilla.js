/**
 * M3 spike: manage-employer add flow, vanilla JS path.
 *
 * Prototype for the runtime decision gate in
 * atlas://plans/feature-acc-manage-employer-block.md (M3). Throwaway code:
 * the M4 list UI replaces it. It exercises the real REST endpoints with the
 * signed settings the block template embeds.
 */
(function () {
	'use strict';

	var DEBOUNCE_MS = 300;
	var MIN_CHARS = 3;
	var FEEDBACK_MS = 6000;

	function boot() {
		document.querySelectorAll('.wicket-acc-manage-employer[data-wicket-me-settings]').forEach(initInstance);
	}

	function initInstance(root) {
		var sfx = root.getAttribute('data-wicket-me-id');
		if (!sfx) {
			return;
		}

		var state = {
			rest: root.getAttribute('data-wicket-me-rest') || '',
			nonce: root.getAttribute('data-wicket-me-nonce') || '',
			settings: root.getAttribute('data-wicket-me-settings') || '',
			token: root.getAttribute('data-wicket-me-token') || '',
			listEl: document.getElementById(sfx + '-list'),
			feedbackEl: document.getElementById(sfx + '-feedback'),
			searchInput: document.getElementById(sfx + '-search'),
			resultsEl: document.getElementById(sfx + '-results'),
			selectedInput: document.getElementById(sfx + '-selected'),
			addBtn: document.getElementById(sfx + '-add'),
			dialog: document.getElementById(sfx + '-dialog'),
			activeIndex: -1,
			options: [],
			timer: 0,
			connectedUuids: []
		};

		if (!state.listEl || !state.searchInput) {
			return;
		}

		fetchState(state, false);
		wireCombobox(state);
		wireAdd(state);
	}

	function wireCombobox(state) {
		state.searchInput.addEventListener('input', function () {
			window.clearTimeout(state.timer);
			state.timer = window.setTimeout(function () {
				var term = state.searchInput.value.trim();

				if (term.length < MIN_CHARS) {
					clearResults(state);
					return;
				}

				search(state, term);
			}, DEBOUNCE_MS);
		});

		state.searchInput.addEventListener('keydown', function (evt) {
			if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
				evt.preventDefault();
				moveActive(state, evt.key === 'ArrowDown' ? 1 : -1);
				return;
			}

			if (evt.key === 'Enter') {
				// Two-stage escape: first Enter selects from an open list.
				if (state.options.length > 0 && state.activeIndex >= 0) {
					evt.preventDefault();
					selectOption(state, state.options[state.activeIndex]);
				}
				return;
			}

			if (evt.key === 'Escape' && state.resultsEl.childElementCount > 0) {
				// Two-stage escape: first Esc closes the listbox, the second
				// falls through to the native dialog Esc.
				evt.stopPropagation();
				clearResults(state);
			}
		});
	}

	function wireAdd(state) {
		if (!state.addBtn) {
			return;
		}

		state.addBtn.addEventListener('click', function () {
			var orgUuid = state.selectedInput ? state.selectedInput.value : '';

			if (!orgUuid) {
				return;
			}

			state.addBtn.disabled = true;
			state.addBtn.textContent = 'Adding…';

			callRest(state, 'POST', '/relationships', { org_uuid: orgUuid })
				.then(function (payload) {
					var errors = payload && payload.errors ? payload.errors : [];

					if (payload && payload.skipped_existing) {
						showFeedback(state, 'Already connected.', 'info');
					} else if (errors.length > 0) {
						showFeedback(state, 'Added with errors: ' + errors.join('; '), 'error');
					} else {
						showFeedback(state, 'Employer added.', 'success');
					}

					closeDialog(state);
					fetchState(state, true);
				})
				.catch(function (err) {
					showFeedback(state, err.message, 'error');
				})
				.finally(function () {
					state.addBtn.textContent = 'Add employer';
					state.selectedInput.value = '';
					state.addBtn.disabled = true;
					clearResults(state);
					state.searchInput.value = '';
				});
		});
	}

	function callRest(state, method, route, params) {
		var url = state.rest + route + '?settings=' + encodeURIComponent(state.settings) +
			'&settings_token=' + encodeURIComponent(state.token);

		var options = {
			method: method,
			headers: { 'X-WP-Nonce': state.nonce }
		};

		if (method === 'POST') {
			options.headers['Content-Type'] = 'application/json';
			options.body = JSON.stringify(params);
			options.credentials = 'same-origin';
		}

		return fetch(url, options).then(function (response) {
			return response.json().catch(function () {
				return {};
			}).then(function (payload) {
				if (!response.ok) {
					var message = payload && payload.message ? payload.message : 'Request failed (' + response.status + ')';

					if (response.status === 403 && payload && payload.code === 'rest_cookie_invalid_nonce') {
						message = 'Session expired. Reload the page.';
					}

					throw new Error(message);
				}

				return payload;
			});
		});
	}

	function search(state, term) {
		state.searchInput.setAttribute('aria-expanded', 'true');

		var url = state.rest + '/search?q=' + encodeURIComponent(term) +
			'&page=1&settings=' + encodeURIComponent(state.settings) +
			'&settings_token=' + encodeURIComponent(state.token);

		fetch(url, { headers: { 'X-WP-Nonce': state.nonce }, credentials: 'same-origin' })
			.then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) {
						throw new Error(payload && payload.message ? payload.message : 'Search failed');
					}

					return payload;
				});
			})
			.then(function (payload) {
				renderResults(state, payload && payload.results ? payload.results : []);
			})
			.catch(function (err) {
				renderResultsError(state, err.message);
			});
	}

	function renderResults(state, results) {
		clearResults(state);
		state.activeIndex = -1;
		state.options = [];

		if (results.length === 0) {
			var empty = document.createElement('li');
			empty.className = 'me-spike__option me-spike__option--empty';
			empty.setAttribute('role', 'option');
			empty.setAttribute('aria-disabled', 'true');
			empty.textContent = 'No organizations found.';
			state.resultsEl.appendChild(empty);
			return;
		}

		results.forEach(function (row, index) {
			var li = document.createElement('li');
			li.className = 'me-spike__option';
			li.setAttribute('role', 'option');
			li.setAttribute('id', sfxOption(state, index));
			li.setAttribute('aria-selected', 'false');
			li.setAttribute('data-uuid', row.uuid || '');

			var name = document.createElement('span');
			name.textContent = row.legal_name || row.uuid || '';
			li.appendChild(name);

			if (row.connected) {
				var badge = document.createElement('span');
				badge.className = 'me-spike__badge';
				badge.textContent = 'Connected';
				li.appendChild(badge);
			}

			li.addEventListener('click', function () {
				selectOption(state, li);
			});

			state.resultsEl.appendChild(li);
			state.options.push(li);
		});
	}

	function renderResultsError(state, message) {
		clearResults(state);

		var li = document.createElement('li');
		li.className = 'me-spike__option me-spike__option--error';
		li.setAttribute('role', 'option');
		li.setAttribute('aria-disabled', 'true');
		li.textContent = message;
		state.resultsEl.appendChild(li);
	}

	function selectOption(state, li) {
		var uuid = li.getAttribute('data-uuid') || '';
		var name = li.textContent || '';

		state.selectedInput.value = uuid;
		state.addBtn.disabled = uuid === '';
		state.searchInput.value = name.replace(/\s*Connected\s*$/, '');
		clearResults(state);
		state.searchInput.focus();
	}

	function moveActive(state, delta) {
		if (state.options.length === 0) {
			return;
		}

		var next = state.activeIndex + delta;

		if (next < 0) {
			next = state.options.length - 1;
		} else if (next >= state.options.length) {
			next = 0;
		}

		setActive(state, next);
	}

	function setActive(state, index) {
		if (state.activeIndex >= 0 && state.options[state.activeIndex]) {
			state.options[state.activeIndex].classList.remove('is-active');
			state.options[state.activeIndex].setAttribute('aria-selected', 'false');
		}

		state.activeIndex = index;
		var li = state.options[index];

		if (li) {
			li.classList.add('is-active');
			li.setAttribute('aria-selected', 'true');
			li.scrollIntoView({ block: 'nearest' });
			state.searchInput.setAttribute('aria-activedescendant', li.getAttribute('id') || '');
		}
	}

	function clearResults(state) {
		state.resultsEl.innerHTML = '';
		state.options = [];
		state.activeIndex = -1;
		state.searchInput.removeAttribute('aria-activedescendant');
		state.searchInput.setAttribute('aria-expanded', 'false');
	}

	function fetchState(state, announce) {
		var url = state.rest + '/state?page=1&settings=' + encodeURIComponent(state.settings) +
			'&settings_token=' + encodeURIComponent(state.token);

		fetch(url, { headers: { 'X-WP-Nonce': state.nonce }, credentials: 'same-origin' })
			.then(function (response) {
				return response.json().then(function (payload) {
					if (!response.ok) {
						throw new Error(payload && payload.message ? payload.message : 'Could not load employers');
					}

					return payload;
				});
			})
			.then(function (payload) {
				renderState(state, payload || {});

				if (announce) {
					var count = payload && payload.connections ? payload.connections.length : 0;
					showFeedback(state, 'List refreshed (' + count + ').', 'info');
				}
			})
			.catch(function (err) {
				renderStateError(state, err.message);
			});
	}

	function renderState(state, payload) {
		var connections = Array.isArray(payload.connections) ? payload.connections : [];
		var orgs = payload.orgs && typeof payload.orgs === 'object' ? payload.orgs : {};
		var primary = payload.primary_uuid || '';

		state.listEl.innerHTML = '';

		if (connections.length === 0) {
			var empty = document.createElement('li');
			empty.className = 'me-spike__empty';
			empty.textContent = 'No employers found.';
			state.listEl.appendChild(empty);
			return;
		}

		connections.forEach(function (connection) {
			var rel = connection.relationships || {};
			var orgUuid = (rel.to && rel.to.data && rel.to.data.id) ||
				(rel.organization && rel.organization.data && rel.organization.data.id) || '';
			var org = orgs[orgUuid] || {};

			var li = document.createElement('li');
			li.className = 'me-spike__card';

			var name = document.createElement('strong');
			name.textContent = org.legal_name || orgUuid || 'Unknown employer';
			li.appendChild(name);

			if (orgUuid && orgUuid === primary) {
				var badge = document.createElement('span');
				badge.className = 'me-spike__badge';
				badge.textContent = 'Primary';
				li.appendChild(badge);
			}

			state.listEl.appendChild(li);
		});
	}

	function renderStateError(state, message) {
		state.listEl.innerHTML = '';

		var li = document.createElement('li');
		li.className = 'me-spike__empty me-spike__empty--error';
		li.textContent = message;
		state.listEl.appendChild(li);
	}

	function closeDialog(state) {
		if (state.dialog && typeof state.dialog.close === 'function' && state.dialog.open) {
			state.dialog.close();
		}
	}

	function showFeedback(state, message, kind) {
		if (!state.feedbackEl) {
			return;
		}

		state.feedbackEl.textContent = message;
		state.feedbackEl.className = 'me-spike__feedback me-spike__feedback--' + (kind || 'info');
		state.feedbackEl.hidden = false;
		window.clearTimeout(state.feedbackTimer);
		state.feedbackTimer = window.setTimeout(function () {
			state.feedbackEl.hidden = true;
		}, FEEDBACK_MS);
	}

	function sfxOption(state, index) {
		return (state.searchInput.id || 'me-option') + '-opt-' + index;
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
