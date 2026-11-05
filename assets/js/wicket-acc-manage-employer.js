/**
 * Manage Employer block front-end: cards, tabs, pagination, feedback, and the
 * add-employer combobox.
 *
 * One ES6 class per block instance on WordPress core script libraries
 * (wp-api-fetch, wp-url, wp-dom-ready). The server owns every string: labels
 * arrive in the data-wicket-me-config attribute already translated, and
 * remote data paints through textContent only.
 *
 * Time to first content: the last /state response is cached per tab and page
 * in sessionStorage and painted instantly on return visits, while a fresh
 * fetch runs once the block nears the viewport.
 *
 * @see atlas://plans/feature-acc-manage-employer-block.md
 */
(function () {
	'use strict';

	const FEEDBACK_MS = 6000;
	const DEBOUNCE_MS = 300;
	const MIN_CHARS = 3;
	const CACHE_TTL_MS = 60000;
	const CACHE_VERSION = 'v1';
	const OBSERVER_ROOT_MARGIN = '200px 0px';

	class WicketManageEmployer {
		constructor(root) {
			let config;

			try {
				config = JSON.parse(root.getAttribute('data-wicket-me-config') || '{}');
			} catch (e) {
				config = null;
			}

			if (!config || !config.id || !config.labels) {
				throw new Error('Manage Employer block: invalid instance config');
			}

			this.root = root;
			this.config = config;
			this.rest = root.getAttribute('data-wicket-me-rest') || '';
			this.nonce = root.getAttribute('data-wicket-me-nonce') || '';
			this.settings = root.getAttribute('data-wicket-me-settings') || '';
			this.token = root.getAttribute('data-wicket-me-token') || '';
			this.tab = config.mode === 'inactive' ? 'past' : 'current';
			this.page = 1;
			this.orgs = {};
			this.primary = '';
			this.loaded = false;
			this.requestSeq = 0;
			this.feedbackTimer = 0;
			this.searchTimer = 0;
			this.options = [];
			this.activeIndex = -1;
			this.observer = null;

			this.listEl = root.querySelector('.wicket-acc-me__cards');
			this.feedbackEl = root.querySelector('[data-me-feedback]');
			this.tabsEl = root.querySelector('.wicket-acc-me__tabs');
			this.paginationEl = root.querySelector('[data-me-pagination]');
			this.pageStatusEl = root.querySelector('[data-me-page-status]');
			this.prevBtn = root.querySelector('[data-me-page-prev]');
			this.nextBtn = root.querySelector('[data-me-page-next]');
			this.searchInput = document.getElementById(config.id + '-search');
			this.resultsEl = document.getElementById(config.id + '-results');
			this.selectedInput = document.getElementById(config.id + '-selected');
			this.addBtn = document.getElementById(config.id + '-add');
			this.dialog = document.getElementById(config.id + '-dialog');

			if (!this.listEl) {
				throw new Error('Manage Employer block: list element missing');
			}

			this.wireFeedback();
			this.wireTabs();
			this.wirePagination();

			if (this.searchInput) {
				this.wireCombobox();
				this.wireAdd();
			}

			this.load();
		}

		load() {
			const cached = this.readCache();

			if (cached) {
				this.loaded = true;
				this.renderState(cached);
			}

			this.fetchWhenVisible();
		}

		fetchWhenVisible() {
			if (!('IntersectionObserver' in window)) {
				this.loadTab();
				return;
			}

			this.observer = new IntersectionObserver((entries) => {
				if (!entries.some((entry) => entry.isIntersecting)) {
					return;
				}

				this.observer.disconnect();
				this.observer = null;
				this.loadTab();
			}, { rootMargin: OBSERVER_ROOT_MARGIN });

			this.observer.observe(this.root);
		}

		// The settings payload identifies the block configuration, so two
		// blocks sharing an instance id across pages never swap cache.
		cacheKey() {
			return 'wicket-acc-me:' + CACHE_VERSION + ':' + this.config.id + ':' +
				WicketManageEmployer.hashString(this.settings) + ':' + this.tab + ':' + this.page;
		}

		readCache() {
			try {
				const raw = window.sessionStorage.getItem(this.cacheKey());

				if (!raw) {
					return null;
				}

				const entry = JSON.parse(raw);

				if (!entry || typeof entry.at !== 'number' || !entry.payload) {
					return null;
				}

				if (Date.now() - entry.at > CACHE_TTL_MS) {
					window.sessionStorage.removeItem(this.cacheKey());
					return null;
				}

				return entry.payload;
			} catch (e) {
				return null;
			}
		}

		writeCache(payload) {
			try {
				window.sessionStorage.setItem(
					this.cacheKey(),
					JSON.stringify({ at: Date.now(), payload: payload })
				);
			} catch (e) {
				// Private mode or quota pressure: caching is best effort.
			}
		}

		wireFeedback() {
			if (!this.feedbackEl) {
				return;
			}

			const dismiss = this.feedbackEl.querySelector('.wicket-acc-me__feedback-dismiss');

			if (dismiss) {
				dismiss.addEventListener('click', () => {
					window.clearTimeout(this.feedbackTimer);
					this.feedbackEl.hidden = true;
				});
			}
		}

		wireTabs() {
			if (!this.tabsEl) {
				return;
			}

			this.tabsEl.addEventListener('click', (evt) => {
				const btn = evt.target.closest('[data-me-tab]');

				if (!btn || btn.getAttribute('aria-pressed') === 'true') {
					return;
				}

				const prev = {
					tab: this.tab,
					page: this.page,
					btn: this.tabsEl.querySelector('[data-me-tab][aria-pressed="true"]')
				};

				this.tab = btn.getAttribute('data-me-tab');
				this.page = 1;
				this.setTabActive(btn);

				this.setLoading();
				this.loadTab(prev);
			});
		}

		setTabActive(activeBtn) {
			this.tabsEl.querySelectorAll('[data-me-tab]').forEach((btn) => {
				const active = btn === activeBtn;

				btn.classList.toggle('is-active', active);
				btn.setAttribute('aria-pressed', active ? 'true' : 'false');
			});
		}

		wirePagination() {
			if (!this.paginationEl) {
				return;
			}

			this.prevBtn.addEventListener('click', () => {
				if (this.prevBtn.disabled) {
					return;
				}

				const prevPage = this.page;
				this.page -= 1;
				this.setLoading();
				this.loadTab({ page: prevPage });
			});

			this.nextBtn.addEventListener('click', () => {
				if (this.nextBtn.disabled) {
					return;
				}

				const prevPage = this.page;
				this.page += 1;
				this.setLoading();
				this.loadTab({ page: prevPage });
			});
		}

		setLoading() {
			this.listEl.setAttribute('aria-busy', 'true');

			if (this.loaded) {
				return;
			}

			this.renderSkeletons();
		}

		renderSkeletons() {
			this.listEl.textContent = '';

			for (let i = 0; i < 3; i++) {
				const li = document.createElement('li');
				li.className = 'wicket-acc-me__sk';
				li.setAttribute('aria-hidden', 'true');

				const line = document.createElement('span');
				line.className = 'wicket-acc-me__sk-line';
				const lineShort = document.createElement('span');
				lineShort.className = 'wicket-acc-me__sk-line wicket-acc-me__sk-line--short';

				li.appendChild(line);
				li.appendChild(lineShort);
				this.listEl.appendChild(li);
			}
		}

		loadTab(prev) {
			const seq = ++this.requestSeq;

			this.callRest('/state', {
				settings: this.settings,
				settings_token: this.token,
				tab: this.tab,
				page: this.page
			}).then((payload) => {
				if (seq !== this.requestSeq) {
					return;
				}

				this.loaded = true;
				this.renderState(payload || {});
				this.writeCache(payload || {});
			}).catch((err) => {
				if (seq !== this.requestSeq) {
					return;
				}

				this.listEl.removeAttribute('aria-busy');

				if (!this.loaded) {
					this.listEl.textContent = '';
					this.listEl.appendChild(this.emptyLi(err.message));
					return;
				}

				// A failed refresh keeps the painted cards and rewinds the tab or
				// page the click already moved, so the chrome stays truthful.
				if (prev) {
					if (typeof prev.tab === 'string') {
						this.tab = prev.tab;
					}

					if (typeof prev.page === 'number') {
						this.page = prev.page;
					}

					if (prev.btn) {
						this.setTabActive(prev.btn);
					}
				}

				this.setFeedback(err.message, 'error');
			});
		}

		renderState(payload) {
			this.listEl.removeAttribute('aria-busy');
			this.orgs = payload.orgs && typeof payload.orgs === 'object' ? payload.orgs : {};
			this.primary = payload.primary_uuid || '';

			const connections = Array.isArray(payload.connections) ? payload.connections : [];
			const meta = payload.meta && typeof payload.meta === 'object' ? payload.meta : {};
			const tabs = payload.tabs && typeof payload.tabs === 'object' ? payload.tabs : {};

			this.updateCounts(tabs);
			this.updatePagination(meta);

			this.listEl.textContent = '';

			if (connections.length === 0) {
				this.listEl.appendChild(this.emptyLi(this.label(this.tab === 'past' ? 'empty_past' : 'empty_current')));
				return;
			}

			connections.forEach((row) => {
				this.listEl.appendChild(this.renderCard(row));
			});
		}

		emptyLi(text) {
			const li = document.createElement('li');
			li.className = 'wicket-acc-me__empty';
			li.textContent = text || '';
			return li;
		}

		renderCard(row) {
			const rel = (row && row.relationships) || {};
			const orgUuid = (rel.to && rel.to.data && rel.to.data.id) ||
				(rel.organization && rel.organization.data && rel.organization.data.id) || '';
			const org = this.orgs[orgUuid] || {};
			const attrs = (row && row.attributes) || {};

			const li = document.createElement('li');
			li.className = 'wicket-acc-me__card' + (this.tab === 'past' ? ' wicket-acc-me__card--past' : '');

			const head = document.createElement('div');
			head.className = 'wicket-acc-me__card-head';

			const name = document.createElement('span');
			name.className = 'wicket-acc-me__card-name';
			name.textContent = org.legal_name || this.label('unknown_employer');
			head.appendChild(name);

			if (this.tab === 'current' && orgUuid && orgUuid === this.primary) {
				head.appendChild(WicketManageEmployer.badge(this.label('primary'), 'wicket-acc-me__badge--primary'));
			}

			if (this.config.display_membership_status && org.membership_status) {
				head.appendChild(WicketManageEmployer.badge(org.membership_status, ''));
			}

			li.appendChild(head);

			const metaLine = this.cardMetaLine(org);

			if (metaLine) {
				const meta = document.createElement('p');
				meta.className = 'wicket-acc-me__card-meta';
				meta.textContent = metaLine;
				li.appendChild(meta);
			}

			if (this.config.display_relationship_dates) {
				const dates = this.relationshipDates(attrs);

				if (dates) {
					const datesEl = document.createElement('p');
					datesEl.className = 'wicket-acc-me__card-dates';
					datesEl.textContent = dates;
					li.appendChild(datesEl);
				}
			}

			return li;
		}

		static badge(text, variant) {
			const span = document.createElement('span');
			span.className = 'wicket-acc-me__badge' + (variant ? ' ' + variant : '');
			span.textContent = text;
			return span;
		}

		cardMetaLine(org) {
			const parts = [];

			if (this.config.display_org_type && org.type) {
				parts.push(WicketManageEmployer.prettySlug(org.type));
			}

			if (this.config.display_org_address) {
				const address = this.formatAddress(org.addresses);

				if (address) {
					parts.push(address);
				}
			}

			return parts.join(', ');
		}

		formatAddress(addresses) {
			if (!Array.isArray(addresses) || addresses.length === 0) {
				return '';
			}

			let a = addresses[0];

			for (let i = 0; i < addresses.length; i++) {
				if (addresses[i] && addresses[i].primary) {
					a = addresses[i];
					break;
				}
			}

			if (!a) {
				return '';
			}

			const format = this.config.org_address_format;

			if (format === 'city_province_country') {
				return WicketManageEmployer.joinNonEmpty([a.city, a.state_name, a.country_name]);
			}

			if (format === 'city_province') {
				return WicketManageEmployer.joinNonEmpty([a.city, a.state_name]);
			}

			return (typeof a.formatted_address_label === 'string' && a.formatted_address_label.trim()) ||
				WicketManageEmployer.joinNonEmpty([a.address1, a.city, a.state_name, a.country_name]);
		}

		relationshipDates(attrs) {
			const start = attrs.starts_at || attrs.effective_at || attrs.created_at || '';

			if (!start) {
				return '';
			}

			const startDate = WicketManageEmployer.shortDate(start);
			const end = attrs.ends_at ? WicketManageEmployer.shortDate(attrs.ends_at) : this.label('present');

			return startDate + ' – ' + end;
		}

		static shortDate(value) {
			const d = new Date(value);

			if (isNaN(d.getTime())) {
				return value;
			}

			return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short' });
		}

		static prettySlug(slug) {
			return String(slug)
				.replace(/_/g, ' ')
				.replace(/\b\w/g, (c) => c.toUpperCase());
		}

		static joinNonEmpty(parts) {
			return parts.filter((p) => typeof p === 'string' && p.trim() !== '').join(', ');
		}

		static hashString(value) {
			let hash = 5381;

			for (let i = 0; i < value.length; i++) {
				hash = ((hash << 5) + hash + value.charCodeAt(i)) | 0;
			}

			return String(hash);
		}

		updateCounts(tabs) {
			if (!this.tabsEl) {
				return;
			}

			this.tabsEl.querySelectorAll('[data-me-count]').forEach((span) => {
				const key = span.getAttribute('data-me-count');
				span.textContent = '(' + (tabs[key] || 0) + ')';
			});
		}

		updatePagination(meta) {
			if (!this.paginationEl) {
				return;
			}

			const totalPages = Number(meta.total_pages || 1);
			const page = Number(meta.current_page || 1);

			if (totalPages <= 1) {
				this.paginationEl.hidden = true;
				return;
			}

			this.paginationEl.hidden = false;
			this.pageStatusEl.textContent = WicketManageEmployer.sprintf2(this.label('page_of'), String(page), String(totalPages));
			this.prevBtn.disabled = page <= 1;
			this.nextBtn.disabled = page >= totalPages;
		}

		wireCombobox() {
			this.searchInput.addEventListener('input', () => {
				// Any edit voids the previous selection; Add must not submit a stale org.
				this.selectedInput.value = '';
				this.addBtn.disabled = true;
				window.clearTimeout(this.searchTimer);
				this.searchTimer = window.setTimeout(() => {
					const term = this.searchInput.value.trim();

					if (term.length < MIN_CHARS) {
						this.clearResults();
						return;
					}

					this.search(term);
				}, DEBOUNCE_MS);
			});

			this.searchInput.addEventListener('keydown', (evt) => {
				if (evt.key === 'ArrowDown' || evt.key === 'ArrowUp') {
					evt.preventDefault();
					this.moveActive(evt.key === 'ArrowDown' ? 1 : -1);
					return;
				}

				if (evt.key === 'Enter') {
					if (this.options.length > 0 && this.activeIndex >= 0) {
						evt.preventDefault();
						this.selectOption(this.options[this.activeIndex]);
					}
					return;
				}

				if (evt.key === 'Escape' && this.resultsEl.childElementCount > 0) {
					// Two-stage escape: first Esc closes the listbox, the second
					// falls through to the native dialog Esc.
					evt.preventDefault();
					evt.stopPropagation();
					this.clearResults();
				}
			});
		}

		search(term) {
			const seq = ++this.requestSeq;

			this.searchInput.setAttribute('aria-expanded', 'true');

			this.callRest('/search', {
				settings: this.settings,
				settings_token: this.token,
				q: term,
				page: 1
			}).then((payload) => {
				if (seq !== this.requestSeq) {
					return;
				}

				this.renderResults(payload && Array.isArray(payload.results) ? payload.results : []);
			}).catch((err) => {
				if (seq !== this.requestSeq) {
					return;
				}

				this.renderResultsError(err.message);
			});
		}

		renderResults(results) {
			this.clearResults();
			this.activeIndex = -1;
			this.options = [];

			if (results.length === 0) {
				const empty = document.createElement('li');
				empty.className = 'wicket-acc-me__option wicket-acc-me__option--empty';
				empty.setAttribute('role', 'option');
				empty.setAttribute('aria-disabled', 'true');
				empty.textContent = this.label('no_results');
				this.resultsEl.appendChild(empty);
				return;
			}

			results.forEach((row, index) => {
				const li = document.createElement('li');
				li.className = 'wicket-acc-me__option';
				li.setAttribute('role', 'option');
				li.setAttribute('id', this.searchInput.id + '-opt-' + index);
				li.setAttribute('aria-selected', 'false');
				li.setAttribute('data-uuid', (row && row.uuid) || '');

				const name = document.createElement('span');
				name.className = 'wicket-acc-me__option-name';
				name.textContent = (row && row.legal_name) || (row && row.uuid) || '';
				li.appendChild(name);

				if (row && row.type && this.config.display_org_type) {
					const type = document.createElement('span');
					type.className = 'wicket-acc-me__option-type';
					type.textContent = WicketManageEmployer.prettySlug(row.type);
					li.appendChild(type);
				}

				const location = row ? this.formatAddress(row.addresses) : '';

				if (location) {
					const locationEl = document.createElement('span');
					locationEl.className = 'wicket-acc-me__option-type';
					locationEl.textContent = location;
					li.appendChild(locationEl);
				}

				if (row && row.membership_status && this.config.display_membership_status) {
					const membership = document.createElement('span');
					membership.className = 'wicket-acc-me__badge';
					membership.textContent = row.membership_status;
					li.appendChild(membership);
				}

				if (row && row.connected) {
					const connected = document.createElement('span');
					connected.className = 'wicket-acc-me__badge wicket-acc-me__badge--connected';
					connected.textContent = this.label('already');
					li.appendChild(connected);
				}

				li.addEventListener('click', () => {
					this.selectOption(li);
				});

				this.resultsEl.appendChild(li);
				this.options.push(li);
			});
		}

		renderResultsError(message) {
			this.clearResults();

			const li = document.createElement('li');
			li.className = 'wicket-acc-me__option wicket-acc-me__option--error';
			li.setAttribute('role', 'option');
			li.setAttribute('aria-disabled', 'true');
			li.textContent = message;
			this.resultsEl.appendChild(li);
		}

		selectOption(li) {
			const uuid = li.getAttribute('data-uuid') || '';
			const nameEl = li.querySelector('.wicket-acc-me__option-name');

			this.selectedInput.value = uuid;
			this.addBtn.disabled = uuid === '';
			this.searchInput.value = nameEl ? nameEl.textContent : '';
			this.clearResults();
			this.searchInput.focus();
		}

		moveActive(delta) {
			if (this.options.length === 0) {
				return;
			}

			let next = this.activeIndex + delta;

			if (next < 0) {
				next = this.options.length - 1;
			} else if (next >= this.options.length) {
				next = 0;
			}

			this.setActive(next);
		}

		setActive(index) {
			if (this.activeIndex >= 0 && this.options[this.activeIndex]) {
				this.options[this.activeIndex].classList.remove('is-active');
				this.options[this.activeIndex].setAttribute('aria-selected', 'false');
			}

			this.activeIndex = index;
			const li = this.options[index];

			if (li) {
				li.classList.add('is-active');
				li.setAttribute('aria-selected', 'true');
				li.scrollIntoView({ block: 'nearest' });
				this.searchInput.setAttribute('aria-activedescendant', li.getAttribute('id') || '');
			}
		}

		clearResults() {
			this.resultsEl.textContent = '';
			this.options = [];
			this.activeIndex = -1;
			this.searchInput.removeAttribute('aria-activedescendant');
			this.searchInput.setAttribute('aria-expanded', 'false');
		}

		wireAdd() {
			this.addBtn.addEventListener('click', () => {
				const orgUuid = this.selectedInput ? this.selectedInput.value : '';

				if (!orgUuid || this.addBtn.disabled) {
					return;
				}

				this.addBtn.disabled = true;
				this.addBtn.textContent = this.label('adding');

				this.callRest('/relationships', {
					settings: this.settings,
					settings_token: this.token,
					org_uuid: orgUuid
				}, 'POST').then((payload) => {
					const errors = payload && Array.isArray(payload.errors) ? payload.errors : [];

					if (payload && payload.skipped_existing) {
						this.setFeedback(this.label('already'), 'info');
					} else if (errors.length > 0) {
						this.setFeedback(WicketManageEmployer.sprintf2(this.label('added_with_errors'), errors.join('; ')), 'error');
					} else {
						this.setFeedback(this.label('added'), 'success');
					}

					this.closeDialog();
					const prevPage = this.page;
					this.page = 1;
					this.setLoading();
					// MDP read-your-writes can lag behind the mutation; the
					// bounded retry lands with the M5 mutation work.
					this.loadTab({ page: prevPage });
				}).catch((err) => {
					this.setFeedback(err.message, 'error');
				}).finally(() => {
					this.addBtn.textContent = this.label('add');
					this.selectedInput.value = '';
					this.addBtn.disabled = true;
					this.clearResults();
					this.searchInput.value = '';
				});
			});
		}

		callRest(route, params, method) {
			const url = wp.url.addQueryArgs(this.rest + route, params);
			const options = {
				url: url,
				method: method || 'GET',
				headers: { 'X-WP-Nonce': this.nonce }
			};

			if (options.method === 'POST') {
				delete params.settings;
				delete params.settings_token;
				options.data = params;
				options.credentials = 'same-origin';
			}

			return wp.apiFetch(options).catch((err) => {
				let message = (err && err.message) ? err.message : this.label('request_failed');

				if (err && err.code === 'rest_cookie_invalid_nonce') {
					message = this.label('session_expired');
				}

				throw new Error(message);
			});
		}

		closeDialog() {
			if (this.dialog && typeof this.dialog.close === 'function' && this.dialog.open) {
				this.dialog.close();
			}
		}

		setFeedback(message, kind) {
			if (!this.feedbackEl) {
				return;
			}

			const textEl = this.feedbackEl.querySelector('.wicket-acc-me__feedback-text');

			if (textEl) {
				textEl.textContent = message;
			}

			this.feedbackEl.className = 'wicket-acc-me__feedback wicket-acc-me__feedback--' + (kind || 'info');
			this.feedbackEl.hidden = false;
			window.clearTimeout(this.feedbackTimer);
			this.feedbackTimer = window.setTimeout(() => {
				this.feedbackEl.hidden = true;
			}, FEEDBACK_MS);
		}

		label(key) {
			return (this.config.labels && this.config.labels[key]) || '';
		}

		static sprintf2(str, a, b) {
			return String(str)
				.replace('%1$s', a)
				.replace('%2$s', b)
				.replace('%s', a);
		}
	}

	wp.domReady(() => {
		document.querySelectorAll('.wicket-acc-manage-employer[data-wicket-me-id]').forEach((root) => {
			try {
				new WicketManageEmployer(root);
			} catch (e) {
				// Invalid instance config: leave the server-rendered chrome untouched.
			}
		});
	});
})();
