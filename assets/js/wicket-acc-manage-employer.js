/**
 * Manage Employer block front-end: cards, tabs, pagination, feedback, and the
 * add-employer combobox.
 *
 * Vanilla JS on WordPress core script libraries (wp-api-fetch, wp-url,
 * wp-dom-ready). The server owns every string: labels arrive in the
 * data-wicket-me-config attribute already translated, and remote data paints
 * through textContent only.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
(function () {
	'use strict';

	var FEEDBACK_MS = 6000;
	var DEBOUNCE_MS = 300;
	var MIN_CHARS = 3;

	function boot() {
		document.querySelectorAll('.wicket-acc-manage-employer[data-wicket-me-id]').forEach(initInstance);
	}

	function initInstance(root) {
		var config;

		try {
			config = JSON.parse(root.getAttribute('data-wicket-me-config') || '{}');
		} catch (e) {
			return;
		}

		if (!config.id || !config.labels) {
			return;
		}

		var state = {
			root: root,
			config: config,
			rest: root.getAttribute('data-wicket-me-rest') || '',
			nonce: root.getAttribute('data-wicket-me-nonce') || '',
			settings: root.getAttribute('data-wicket-me-settings') || '',
			token: root.getAttribute('data-wicket-me-token') || '',
			tab: config.mode === 'inactive' ? 'past' : 'current',
			page: 1,
			orgs: {},
			primary: '',
			loaded: false,
			feedbackTimer: 0,
			searchTimer: 0,
			options: [],
			activeIndex: -1
		};

		state.listEl = root.querySelector('.wicket-acc-me__cards');
		state.feedbackEl = root.querySelector('[data-me-feedback]');
		state.tabsEl = root.querySelector('.wicket-acc-me__tabs');
		state.paginationEl = root.querySelector('[data-me-pagination]');
		state.pageStatusEl = root.querySelector('[data-me-page-status]');
		state.prevBtn = root.querySelector('[data-me-page-prev]');
		state.nextBtn = root.querySelector('[data-me-page-next]');
		state.searchInput = document.getElementById(config.id + '-search');
		state.resultsEl = document.getElementById(config.id + '-results');
		state.selectedInput = document.getElementById(config.id + '-selected');
		state.addBtn = document.getElementById(config.id + '-add');
		state.dialog = document.getElementById(config.id + '-dialog');

		if (!state.listEl) {
			return;
		}

		wireFeedback(state);
		wireTabs(state);
		wirePagination(state);

		if (state.searchInput) {
			wireCombobox(state);
			wireAdd(state);
		}

		loadTab(state);
	}

	function wireFeedback(state) {
		if (!state.feedbackEl) {
			return;
		}

		var dismiss = state.feedbackEl.querySelector('.wicket-acc-me__feedback-dismiss');

		if (dismiss) {
			dismiss.addEventListener('click', function () {
				window.clearTimeout(state.feedbackTimer);
				state.feedbackEl.hidden = true;
			});
		}
	}

	function wireTabs(state) {
		if (!state.tabsEl) {
			return;
		}

		state.tabsEl.addEventListener('click', function (evt) {
			var btn = evt.target.closest('[data-me-tab]');

			if (!btn || btn.getAttribute('aria-pressed') === 'true') {
				return;
			}

			state.tab = btn.getAttribute('data-me-tab');
			state.page = 1;

			state.tabsEl.querySelectorAll('[data-me-tab]').forEach(function (b) {
				var active = b === btn;

				b.classList.toggle('is-active', active);
				b.setAttribute('aria-pressed', active ? 'true' : 'false');
			});

			setLoading(state);
			loadTab(state);
		});
	}

	function wirePagination(state) {
		if (!state.paginationEl) {
			return;
		}

		state.prevBtn.addEventListener('click', function () {
			if (state.prevBtn.disabled) {
				return;
			}

			state.page -= 1;
			setLoading(state);
			loadTab(state);
		});

		state.nextBtn.addEventListener('click', function () {
			if (state.nextBtn.disabled) {
				return;
			}

			state.page += 1;
			setLoading(state);
			loadTab(state);
		});
	}

	function setLoading(state) {
		state.listEl.setAttribute('aria-busy', 'true');
		state.listEl.textContent = '';

		var li = document.createElement('li');
		li.className = 'wicket-acc-me__empty';
		li.textContent = '…';
		state.listEl.appendChild(li);
	}

	function loadTab(state) {
		callRest(state, '/state', {
			settings: state.settings,
			settings_token: state.token,
			tab: state.tab,
			page: state.page
		}).then(function (payload) {
			state.loaded = true;
			renderState(state, payload || {});
		}).catch(function (err) {
			// Errors keep the current cards: a 429 or a failed refresh must
			// never wipe the member's list.
			if (!state.loaded) {
				state.listEl.removeAttribute('aria-busy');
				state.listEl.textContent = '';
				state.listEl.appendChild(emptyLi(state, err.message));
				return;
			}

			state.listEl.removeAttribute('aria-busy');
			setFeedback(state, err.message, 'error');
		});
	}

	function renderState(state, payload) {
		state.listEl.removeAttribute('aria-busy');
		state.orgs = payload.orgs && typeof payload.orgs === 'object' ? payload.orgs : {};
		state.primary = payload.primary_uuid || '';

		var connections = Array.isArray(payload.connections) ? payload.connections : [];
		var meta = payload.meta && typeof payload.meta === 'object' ? payload.meta : {};
		var tabs = payload.tabs && typeof payload.tabs === 'object' ? payload.tabs : {};

		updateCounts(state, tabs);
		updatePagination(state, meta);

		state.listEl.textContent = '';

		if (connections.length === 0) {
			state.listEl.appendChild(emptyLi(state, label(state, state.tab === 'past' ? 'empty_past' : 'empty_current')));
			return;
		}

		connections.forEach(function (row) {
			state.listEl.appendChild(renderCard(state, row));
		});
	}

	function emptyLi(state, text) {
		var li = document.createElement('li');
		li.className = 'wicket-acc-me__empty';
		li.textContent = text || '';
		return li;
	}

	function renderCard(state, row) {
		var rel = (row && row.relationships) || {};
		var orgUuid = (rel.to && rel.to.data && rel.to.data.id) ||
			(rel.organization && rel.organization.data && rel.organization.data.id) || '';
		var org = state.orgs[orgUuid] || {};
		var attrs = (row && row.attributes) || {};

		var li = document.createElement('li');
		li.className = 'wicket-acc-me__card' + (state.tab === 'past' ? ' wicket-acc-me__card--past' : '');

		var head = document.createElement('div');
		head.className = 'wicket-acc-me__card-head';

		var name = document.createElement('span');
		name.className = 'wicket-acc-me__card-name';
		name.textContent = org.legal_name || orgUuid || label(state, 'unknown_employer');
		head.appendChild(name);

		if (state.tab === 'current' && orgUuid && orgUuid === state.primary) {
			head.appendChild(badge(label(state, 'primary'), 'wicket-acc-me__badge--primary'));
		}

		if (state.config.display_membership_status && org.membership_status) {
			head.appendChild(badge(org.membership_status, ''));
		}

		li.appendChild(head);

		var metaLine = cardMetaLine(state, org);

		if (metaLine) {
			var meta = document.createElement('p');
			meta.className = 'wicket-acc-me__card-meta';
			meta.textContent = metaLine;
			li.appendChild(meta);
		}

		if (state.config.display_relationship_dates) {
			var dates = relationshipDates(state, attrs);

			if (dates) {
				var datesEl = document.createElement('p');
				datesEl.className = 'wicket-acc-me__card-dates';
				datesEl.textContent = dates;
				li.appendChild(datesEl);
			}
		}

		return li;
	}

	function badge(text, variant) {
		var span = document.createElement('span');
		span.className = 'wicket-acc-me__badge' + (variant ? ' ' + variant : '');
		span.textContent = text;
		return span;
	}

	function cardMetaLine(state, org) {
		var parts = [];

		if (state.config.display_org_type && org.type) {
			parts.push(prettySlug(org.type));
		}

		if (state.config.display_org_address) {
			var address = formatAddress(state, org.addresses);

			if (address) {
				parts.push(address);
			}
		}

		return parts.join(', ');
	}

	function formatAddress(state, addresses) {
		if (!Array.isArray(addresses) || addresses.length === 0) {
			return '';
		}

		var a = addresses[0];

		for (var i = 0; i < addresses.length; i++) {
			if (addresses[i] && addresses[i].primary) {
				a = addresses[i];
				break;
			}
		}

		if (!a) {
			return '';
		}

		var format = state.config.org_address_format;

		if (format === 'city_province_country') {
			return joinNonEmpty([a.city, a.state_name, a.country_name]);
		}

		if (format === 'city_province') {
			return joinNonEmpty([a.city, a.state_name]);
		}

		return (typeof a.formatted_address_label === 'string' && a.formatted_address_label.trim()) ||
			joinNonEmpty([a.address1, a.city, a.state_name, a.country_name]);
	}

	function relationshipDates(state, attrs) {
		var start = attrs.starts_at || attrs.effective_at || attrs.created_at || '';

		if (!start) {
			return '';
		}

		var startDate = shortDate(start);
		var end = attrs.ends_at ? shortDate(attrs.ends_at) : label(state, 'present');

		return startDate + ' – ' + end;
	}

	function shortDate(value) {
		var d = new Date(value);

		if (isNaN(d.getTime())) {
			return value;
		}

		return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short' });
	}

	function prettySlug(slug) {
		return String(slug)
			.replace(/_/g, ' ')
			.replace(/\b\w/g, function (c) {
				return c.toUpperCase();
			});
	}

	function joinNonEmpty(parts) {
		return parts.filter(function (p) {
			return typeof p === 'string' && p.trim() !== '';
		}).join(', ');
	}

	function updateCounts(state, tabs) {
		if (!state.tabsEl) {
			return;
		}

		state.tabsEl.querySelectorAll('[data-me-count]').forEach(function (span) {
			var key = span.getAttribute('data-me-count');
			span.textContent = '(' + (tabs[key] || 0) + ')';
		});
	}

	function updatePagination(state, meta) {
		if (!state.paginationEl) {
			return;
		}

		var totalPages = Number(meta.total_pages || 1);
		var page = Number(meta.current_page || 1);

		if (totalPages <= 1) {
			state.paginationEl.hidden = true;
			return;
		}

		state.paginationEl.hidden = false;
		state.pageStatusEl.textContent = sprintf2(label(state, 'page_of'), String(page), String(totalPages));
		state.prevBtn.disabled = page <= 1;
		state.nextBtn.disabled = page >= totalPages;
	}

	function wireCombobox(state) {
		state.searchInput.addEventListener('input', function () {
			window.clearTimeout(state.searchTimer);
			state.searchTimer = window.setTimeout(function () {
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

	function search(state, term) {
		state.searchInput.setAttribute('aria-expanded', 'true');

		callRest(state, '/search', {
			settings: state.settings,
			settings_token: state.token,
			q: term,
			page: 1
		}).then(function (payload) {
			renderResults(state, payload && Array.isArray(payload.results) ? payload.results : []);
		}).catch(function (err) {
			renderResultsError(state, err.message);
		});
	}

	function renderResults(state, results) {
		clearResults(state);
		state.activeIndex = -1;
		state.options = [];

		if (results.length === 0) {
			var empty = document.createElement('li');
			empty.className = 'wicket-acc-me__option wicket-acc-me__option--empty';
			empty.setAttribute('role', 'option');
			empty.setAttribute('aria-disabled', 'true');
			empty.textContent = label(state, 'no_results');
			state.resultsEl.appendChild(empty);
			return;
		}

		results.forEach(function (row, index) {
			var li = document.createElement('li');
			li.className = 'wicket-acc-me__option';
			li.setAttribute('role', 'option');
			li.setAttribute('id', state.searchInput.id + '-opt-' + index);
			li.setAttribute('aria-selected', 'false');
			li.setAttribute('data-uuid', (row && row.uuid) || '');

			var name = document.createElement('span');
			name.className = 'wicket-acc-me__option-name';
			name.textContent = (row && row.legal_name) || (row && row.uuid) || '';
			li.appendChild(name);

			if (row && row.type && state.config.display_org_type) {
				var type = document.createElement('span');
				type.className = 'wicket-acc-me__option-type';
				type.textContent = prettySlug(row.type);
				li.appendChild(type);
			}

			var location = row ? formatAddress(state, row.addresses) : '';

			if (location) {
				var locationEl = document.createElement('span');
				locationEl.className = 'wicket-acc-me__option-type';
				locationEl.textContent = location;
				li.appendChild(locationEl);
			}

			if (row && row.membership_status && state.config.display_membership_status) {
				var membership = document.createElement('span');
				membership.className = 'wicket-acc-me__badge';
				membership.textContent = row.membership_status;
				li.appendChild(membership);
			}

			if (row && row.connected) {
				var connected = document.createElement('span');
				connected.className = 'wicket-acc-me__badge wicket-acc-me__badge--connected';
				connected.textContent = label(state, 'already');
				li.appendChild(connected);
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
		li.className = 'wicket-acc-me__option wicket-acc-me__option--error';
		li.setAttribute('role', 'option');
		li.setAttribute('aria-disabled', 'true');
		li.textContent = message;
		state.resultsEl.appendChild(li);
	}

	function selectOption(state, li) {
		var uuid = li.getAttribute('data-uuid') || '';
		var nameEl = li.querySelector('.wicket-acc-me__option-name');

		state.selectedInput.value = uuid;
		state.addBtn.disabled = uuid === '';
		state.searchInput.value = nameEl ? nameEl.textContent : '';
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
		state.resultsEl.textContent = '';
		state.options = [];
		state.activeIndex = -1;
		state.searchInput.removeAttribute('aria-activedescendant');
		state.searchInput.setAttribute('aria-expanded', 'false');
	}

	function wireAdd(state) {
		state.addBtn.addEventListener('click', function () {
			var orgUuid = state.selectedInput ? state.selectedInput.value : '';

			if (!orgUuid || state.addBtn.disabled) {
				return;
			}

			state.addBtn.disabled = true;
			state.addBtn.textContent = label(state, 'adding');

			callRest(state, '/relationships', {
				settings: state.settings,
				settings_token: state.token,
				org_uuid: orgUuid
			}, 'POST').then(function (payload) {
				var errors = payload && Array.isArray(payload.errors) ? payload.errors : [];

				if (payload && payload.skipped_existing) {
					setFeedback(state, label(state, 'already'), 'info');
				} else if (errors.length > 0) {
					setFeedback(state, sprintf2(label(state, 'added_with_errors'), errors.join('; ')), 'error');
				} else {
					setFeedback(state, label(state, 'added'), 'success');
				}

				closeDialog(state);
				state.tab = state.tab === 'past' ? 'past' : 'current';
				state.page = 1;
				setLoading(state);
				// MDP read-your-writes can lag behind the mutation; the
				// bounded retry lands with the M5 mutation work.
				loadTab(state);
			}).catch(function (err) {
				setFeedback(state, err.message, 'error');
			}).finally(function () {
				state.addBtn.textContent = label(state, 'add');
				state.selectedInput.value = '';
				state.addBtn.disabled = true;
				clearResults(state);
				state.searchInput.value = '';
			});
		});
	}

	function callRest(state, route, params, method) {
		var url = wp.url.addQueryArgs(state.rest + route, params);
		var options = {
			url: url,
			method: method || 'GET',
			headers: { 'X-WP-Nonce': state.nonce }
		};

		if (options.method === 'POST') {
			delete params.settings;
			delete params.settings_token;
			options.data = params;
			options.credentials = 'same-origin';
		}

		return wp.apiFetch(options).catch(function (err) {
			var message = (err && err.message) ? err.message : label(state, 'request_failed');

			if (err && err.code === 'rest_cookie_invalid_nonce') {
				message = label(state, 'session_expired');
			}

			throw new Error(message);
		});
	}

	function closeDialog(state) {
		if (state.dialog && typeof state.dialog.close === 'function' && state.dialog.open) {
			state.dialog.close();
		}
	}

	function setFeedback(state, message, kind) {
		if (!state.feedbackEl) {
			return;
		}

		var textEl = state.feedbackEl.querySelector('.wicket-acc-me__feedback-text');

		if (textEl) {
			textEl.textContent = message;
		}

		state.feedbackEl.className = 'wicket-acc-me__feedback wicket-acc-me__feedback--' + (kind || 'info');
		state.feedbackEl.hidden = false;
		window.clearTimeout(state.feedbackTimer);
		state.feedbackTimer = window.setTimeout(function () {
			state.feedbackEl.hidden = true;
		}, FEEDBACK_MS);
	}

	function label(state, key) {
		return (state.config.labels && state.config.labels[key]) || '';
	}

	function sprintf2(str, a, b) {
		return String(str)
			.replace('%1$s', a)
			.replace('%2$s', b)
			.replace('%s', a);
	}

	wp.domReady(boot);
})();
