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
	const CACHE_PREFIX = 'wicket-acc-me:' + CACHE_VERSION;
	const OBSERVER_ROOT_MARGIN = '200px 0px';

	// Bounded re-read after mutations: MDP workers run async, so the change
	// may not read back on the first state fetch. Paint every successful read
	// and stop when the mutation's delta shows up.
	const RETRY_DELAYS_MS = [750, 1500, 3000];

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
			this.tabCounts = { current: 0, past: 0 };
			this.loaded = false;
			this.mutating = false;
			this.pollActive = false;
			this.requestSeq = 0;
			this.searchSeq = 0;
			this.retryTimer = 0;
			this.feedbackTimer = 0;
			this.searchTimer = 0;
			this.options = [];
			this.activeIndex = -1;
			this.observer = null;
			this.pendingEnd = null;

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
			this.confirmDialog = document.getElementById(config.id + '-confirm');
			this.confirmCancelBtn = document.getElementById(config.id + '-confirm-cancel');
			this.confirmEndBtn = document.getElementById(config.id + '-confirm-end');
			this.endDateInput = document.getElementById(config.id + '-end-date');
			this.createNameInput = document.getElementById(config.id + '-create-name');
			this.createTypeSelect = document.getElementById(config.id + '-create-type');
			this.createSubmitBtn = document.getElementById(config.id + '-create-submit');
			this.createErrorEl = root.querySelector('[data-me-create-error]');
			this.addErrorEl = root.querySelector('[data-me-add-error]');
			this.resultsStatusEl = root.querySelector('[data-me-results-status]');

			if (!this.listEl) {
				throw new Error('Manage Employer block: list element missing');
			}

			this.wireFeedback();
			this.wireTabs();
			this.wirePagination();
			this.wireCardActions();

			if (this.searchInput) {
				this.wireCombobox();
				this.wireAdd();
			}

			if (this.createSubmitBtn) {
				this.wireCreateOrg();
			}

			if (this.confirmEndBtn) {
				this.wireEndConfirm();
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
		// blocks sharing an instance id across pages never swap cache. The
		// per-user hash keeps a user switch in the same tab from painting
		// the previous member's cached employers.
		cacheKey() {
			return CACHE_PREFIX + ':' + (this.config.user || '') + ':' + this.config.id + ':' +
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
			// A user action supersedes any in-flight mutation poll chain.
			this.cancelMutationPoll();

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

			// The server clamps the page (a tab may have shrunk under the poll);
			// local arithmetic and the cache key must follow it.
			this.page = Number(meta.current_page || 1);

			this.tabCounts = {
				current: Number(tabs.current || 0),
				past: Number(tabs.past || 0)
			};

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
			const isCurrent = this.tab !== 'past';
			const isPrimary = isCurrent && this.config.enable_primary && !this.config.hide_primary_tag &&
				orgUuid !== '' && orgUuid === this.primary;

			const li = document.createElement('li');
			li.className = 'wicket-acc-me__card' + (isCurrent ? '' : ' wicket-acc-me__card--past');

			if (isPrimary) {
				li.classList.add('wicket-acc-me__card--primary');
			}

			const head = document.createElement('div');
			head.className = 'wicket-acc-me__card-head';

			const name = document.createElement('span');
			name.className = 'wicket-acc-me__card-name';
			name.textContent = org.legal_name || this.label('unknown_employer');
			head.appendChild(name);

			if (isPrimary) {
				head.appendChild(WicketManageEmployer.badge(this.label('primary'), 'wicket-acc-me__badge--primary'));
			}

			if (isCurrent && this.config.display_membership_status && org.membership_status) {
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

			const actions = [];

			if (isCurrent && this.config.enable_primary && !this.config.hide_set_primary_button &&
				orgUuid !== '' && orgUuid !== this.primary) {
				actions.push({ action: 'primary', label: this.label('set_primary') });
			}

			if (isCurrent && !this.config.hide_end_button) {
				actions.push({ action: 'end', label: this.label('end') });
			}

			if (!isCurrent && this.config.mode !== 'inactive') {
				actions.push({ action: 'rejoin', label: this.label('rejoin') });
			}

			if (actions.length > 0 && row && row.id) {
				const bar = document.createElement('div');
				bar.className = 'wicket-acc-me__card-actions';

				actions.forEach((item) => {
					const btn = document.createElement('button');
					btn.type = 'button';
					btn.className = 'component-button button button--secondary wicket-acc-me__card-action wicket-acc-me__card-action--' + item.action;
					btn.textContent = item.label;
					btn.setAttribute('data-me-action', item.action);
					btn.setAttribute('data-org', orgUuid);
					btn.setAttribute('data-connection', row.id || '');
					btn.setAttribute('data-starts', attrs.starts_at || '');
					btn.setAttribute('data-name', org.legal_name || this.label('unknown_employer'));
					bar.appendChild(btn);
				});

				li.appendChild(bar);
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
			// Search has its own sequence: a keystroke must never void an in-flight
			// state read, and a state poll must never void pending results.
			this.cancelMutationPoll();

			const seq = ++this.searchSeq;

			this.searchInput.setAttribute('aria-expanded', 'true');

			this.callRest('/search', {
				settings: this.settings,
				settings_token: this.token,
				q: term,
				page: 1
			}).then((payload) => {
				if (seq !== this.searchSeq) {
					return;
				}

				this.renderResults(payload && Array.isArray(payload.results) ? payload.results : []);
			}).catch((err) => {
				if (seq !== this.searchSeq) {
					return;
				}

				this.renderResultsError(err.message);
			});
		}

		renderResults(results) {
			this.clearResults();
			this.activeIndex = -1;
			this.options = [];
			this.announceResults(WicketManageEmployer.sprintf2(this.label('results_found'), String(results.length)));

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

			// clearResults() reset this at paint time; the listbox is open again
			// the moment its options exist, empty or not.
			this.searchInput.setAttribute('aria-expanded', 'true');
		}

		renderResultsError(message) {
			this.clearResults();
			this.announceResults(message);

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

		// Result counts (and search failures) announce through the static
		// status region; the results list repaints per keystroke, so a live
		// region inside it would never fire.
		announceResults(text) {
			if (this.resultsStatusEl) {
				this.resultsStatusEl.textContent = text;
			}
		}

		wireAdd() {
			this.addBtn.addEventListener('click', () => {
				const orgUuid = this.selectedInput ? this.selectedInput.value : '';

				if (!orgUuid || this.addBtn.disabled || this.mutating || this.pollActive) {
					return;
				}

				const baselineCurrent = this.tabCounts.current;

				this.addBtn.disabled = true;
				this.addBtn.textContent = this.label('adding');
				this.mutating = true;
				this.hideAddError();

				this.callRest('/relationships', {
					settings: this.settings,
					settings_token: this.token,
					org_uuid: orgUuid
				}, 'POST').then((payload) => {
					const errors = WicketManageEmployer.errorList(payload && payload.errors);

					if (payload && payload.skipped_existing) {
						this.setFeedback(this.label('already'), 'info');
					} else if (errors.length > 0) {
						this.setFeedback(WicketManageEmployer.sprintf2(this.label('added_with_errors'), errors.join('; ')), 'warning');
					} else {
						this.setFeedback(this.label('added'), 'success');
					}

					this.closeDialog();
					this.resetAddForm();

					if (payload && payload.skipped_existing) {
						// Nothing was created; one honest read beats a retry chain
						// that can only exhaust into the "still updating" note.
						this.loadTab();
					} else {
						this.refreshAfterMutation({ type: 'add', orgUuid: orgUuid, baselineCurrent: baselineCurrent });
					}
				}).catch((err) => {
					this.mutating = false;
					this.showAddError(err.message);
				}).finally(() => {
					this.mutating = false;
					this.addBtn.textContent = this.label('add');
					this.addBtn.disabled = this.selectedInput ? this.selectedInput.value === '' : true;
				});
			});
		}

		wireCardActions() {
			this.listEl.addEventListener('click', (evt) => {
				const btn = evt.target.closest('[data-me-action]');

				if (!btn || btn.disabled || this.mutating || this.pollActive) {
					return;
				}

				const action = btn.getAttribute('data-me-action');

				if (action === 'end') {
					this.openEndConfirm(btn);
				} else if (action === 'primary') {
					this.setPrimary(btn);
				} else if (action === 'rejoin') {
					this.rejoin(btn);
				}
			});
		}

		openEndConfirm(btn) {
			const target = {
				orgUuid: btn.getAttribute('data-org') || '',
				connectionUuid: btn.getAttribute('data-connection') || '',
				name: btn.getAttribute('data-name') || '',
				startsAt: btn.getAttribute('data-starts') || ''
			};

			if (target.orgUuid === '' || target.connectionUuid === '') {
				return;
			}

			if (this.confirmDialog && typeof this.confirmDialog.showModal === 'function') {
				this.pendingEnd = target;

				if (this.endDateInput) {
					const today = WicketManageEmployer.localToday();

					this.endDateInput.max = today;

					if (target.startsAt.length >= 10) {
						this.endDateInput.min = target.startsAt.slice(0, 10);
					}

					// Default to today; the server computes the exact timestamp
					// and an untouched field sends no date at all, so a browser
					// clock that straddles MDP midnight cannot misdate the end.
					this.endDateInput.value = today;
				}

				this.confirmDialog.showModal();
				return;
			}

			this.endEmployer(target, '');
		}

		wireEndConfirm() {
			this.confirmCancelBtn.addEventListener('click', () => {
				this.closeConfirm();
			});

			this.confirmEndBtn.addEventListener('click', () => {
				const target = this.pendingEnd;
				const picked = this.endDateInput ? this.endDateInput.value : '';

				if (!target) {
					return;
				}

				this.closeConfirm();

				const today = WicketManageEmployer.localToday();
				this.endEmployer(target, picked && picked !== today ? picked : '');
			});
		}

		closeConfirm() {
			if (this.confirmDialog && typeof this.confirmDialog.close === 'function' && this.confirmDialog.open) {
				this.confirmDialog.close();
			}

			this.pendingEnd = null;
		}

		endEmployer(target, endDate) {
			const baselineCurrent = this.tabCounts.current;
			const params = {
				settings: this.settings,
				settings_token: this.token,
				org_uuid: target.orgUuid
			};

			if (endDate !== '') {
				params.end_date = endDate;
			}

			this.mutating = true;

			this.callRest('/relationships/' + encodeURIComponent(target.connectionUuid), params, 'PATCH').then((payload) => {
				// A 207 means some connections of the org failed to end; that is
				// an error to the member, not a success.
				const errors = WicketManageEmployer.errorList(payload && payload.errors);

				if (errors.length > 0) {
					this.setFeedback(WicketManageEmployer.sprintf2(this.label('added_with_errors'), errors.join('; ')), 'error');
				} else {
					this.setFeedback(WicketManageEmployer.sprintf2(this.label('ended'), target.name), 'success');
				}

				if (errors.length > 0) {
					// Partial failures leave MDP in a state the count predicate
					// cannot settle on; one honest read beats a doomed chain.
					this.mutating = false;
					this.loadTab();
				} else {
					this.refreshAfterMutation({ type: 'end', orgUuid: target.orgUuid, baselineCurrent: baselineCurrent });
				}
			}).catch((err) => {
				this.mutating = false;
				this.setFeedback(err.message, 'error');
			}).finally(() => {
				this.mutating = false;
			});
		}

		setPrimary(btn) {
			const orgUuid = btn.getAttribute('data-org') || '';
			const name = btn.getAttribute('data-name') || '';

			if (orgUuid === '') {
				return;
			}

			this.mutating = true;
			btn.disabled = true;
			btn.textContent = this.label('setting_primary');

			this.callRest('/primary', {
				settings: this.settings,
				settings_token: this.token,
				org_uuid: orgUuid
			}, 'PATCH').then(() => {
				this.setFeedback(
					WicketManageEmployer.sprintf2(this.label('primary_set'), name) + ' ' + this.label('primary_set_note'),
					'success'
				);
				this.refreshAfterMutation({ type: 'primary', orgUuid: orgUuid, baselineCurrent: 0 });
			}).catch((err) => {
				this.mutating = false;
				this.setFeedback(err.message, 'error');
			}).finally(() => {
				this.mutating = false;
				btn.disabled = false;
				btn.textContent = this.label('set_primary');
			});
		}

		rejoin(btn) {
			const orgUuid = btn.getAttribute('data-org') || '';
			const name = btn.getAttribute('data-name') || '';

			if (orgUuid === '') {
				return;
			}

			const baselineCurrent = this.tabCounts.current;

			this.mutating = true;
			btn.disabled = true;
			btn.textContent = this.label('rejoining');

			this.callRest('/relationships', {
				settings: this.settings,
				settings_token: this.token,
				org_uuid: orgUuid
			}, 'POST').then((payload) => {
				if (payload && payload.skipped_existing) {
					this.setFeedback(this.label('already'), 'info');
				} else {
					this.setFeedback(WicketManageEmployer.sprintf2(this.label('rejoined'), name), 'success');
				}

				btn.disabled = false;
				btn.textContent = this.label('rejoin');

				if (payload && payload.skipped_existing) {
					this.mutating = false;
					this.loadTab();
				} else {
					this.refreshAfterMutation({ type: 'add', orgUuid: orgUuid, baselineCurrent: baselineCurrent });
				}
			}).catch((err) => {
				this.mutating = false;
				this.setFeedback(err.message, 'error');
			}).finally(() => {
				this.mutating = false;
				btn.disabled = false;
				btn.textContent = this.label('rejoin');
			});
		}

		wireCreateOrg() {
			this.createSubmitBtn.addEventListener('click', () => {
				if (this.mutating || this.pollActive) {
					return;
				}

				const name = this.createNameInput ? this.createNameInput.value.trim() : '';
				const type = this.createTypeSelect ? this.createTypeSelect.value : '';

				if (name === '') {
					this.showCreateError(this.label('name_and_type_required'), this.createNameInput);
					return;
				}

				if (this.createTypeSelect !== null && type === '') {
					this.showCreateError(this.label('name_and_type_required'), this.createTypeSelect);
					return;
				}

				this.hideCreateError();

				const baselineCurrent = this.tabCounts.current;
				const prevLabel = this.createSubmitBtn.textContent;

				this.mutating = true;
				this.createSubmitBtn.disabled = true;
				this.createSubmitBtn.textContent = this.label('creating');

				const params = { name: name };

				if (type !== '') {
					params.org_type = type;
				}

				this.callRest('/organizations', {
					settings: this.settings,
					settings_token: this.token,
					...params
				}, 'POST').then((payload) => {
					const connection = (payload && payload.connection) || {};
					const errors = WicketManageEmployer.errorList(connection.errors);

					if (errors.length > 0) {
						this.setFeedback(WicketManageEmployer.sprintf2(this.label('added_with_errors'), errors.join('; ')), 'warning');
					} else if (payload && payload.duplicate) {
						this.setFeedback(WicketManageEmployer.sprintf2(this.label('duplicate_org'), name), 'info');
					} else {
						this.setFeedback(WicketManageEmployer.sprintf2(this.label('created'), name), 'success');
					}

					this.closeDialog();

					const orgUuid = (payload && payload.org_uuid) || '';

					if (errors.length > 0 && orgUuid === '') {
						// No org and no connection came out of the request, so the
						// list cannot have changed; skip the retry chain.
						this.mutating = false;
						this.loadTab();
					} else {
						this.refreshAfterMutation({
							type: 'add',
							orgUuid: orgUuid,
							baselineCurrent: baselineCurrent
						});
					}
				}).catch((err) => {
					this.mutating = false;
					this.showCreateError(err.message, this.createNameInput);
				}).finally(() => {
					this.mutating = false;
					this.createSubmitBtn.disabled = false;
					this.createSubmitBtn.textContent = prevLabel;

					if (this.createNameInput) {
						this.createNameInput.value = '';
					}

					if (this.createTypeSelect) {
						this.createTypeSelect.value = '';
					}
				});
			});
		}

		showCreateError(message, field) {
			if (!this.createErrorEl) {
				this.setFeedback(message, 'error');
				return;
			}

			this.createErrorEl.textContent = message;
			this.createErrorEl.hidden = false;

			if (field) {
				field.setAttribute('aria-invalid', 'true');
				field.focus();
			}
		}

		hideCreateError() {
			if (this.createErrorEl) {
				this.createErrorEl.textContent = '';
				this.createErrorEl.hidden = true;
			}

			if (this.createNameInput) {
				this.createNameInput.removeAttribute('aria-invalid');
			}

			if (this.createTypeSelect) {
				this.createTypeSelect.removeAttribute('aria-invalid');
			}
		}

		refreshAfterMutation(expectation) {
			// Cached pages predate the mutation; a return visit must not paint it.
			this.clearStateCache();
			window.clearTimeout(this.retryTimer);
			this.mutating = true;
			this.pollActive = true;
			this.setLoading();
			this.pollState(expectation, RETRY_DELAYS_MS.length + 1);
		}

		cancelMutationPoll() {
			window.clearTimeout(this.retryTimer);
			// Only the poll dies here; the in-flight request still clears its
			// own mutating flag, so tab clicks cannot reopen the submit guards
			// mid-request.
			this.pollActive = false;
		}

		pollState(expectation, attemptsLeft) {
			if (attemptsLeft <= 0) {
				// The change landed server-side; keep the honest picture and say so.
				this.mutating = false;
				this.pollActive = false;
				this.setFeedback(this.label('stale_update'), 'info');
				return;
			}

			const seq = ++this.requestSeq;
			const attempt = RETRY_DELAYS_MS.length + 1 - attemptsLeft;

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

				if (this.mutationSettled(payload || {}, expectation)) {
					this.mutating = false;
					this.pollActive = false;
					return;
				}

				const delay = RETRY_DELAYS_MS[Math.min(attempt - 1, RETRY_DELAYS_MS.length - 1)];

				this.retryTimer = window.setTimeout(() => {
					this.pollState(expectation, attemptsLeft - 1);
				}, delay);
			}).catch((err) => {
				if (seq !== this.requestSeq) {
					return;
				}

				if (!this.loaded) {
					this.mutating = false;
					this.pollActive = false;
					this.listEl.removeAttribute('aria-busy');
					this.listEl.textContent = '';
					this.listEl.appendChild(this.emptyLi(err.message));
					return;
				}

				this.mutating = false;
				this.pollActive = false;
				this.setFeedback(err.message, 'error');
			});
		}

		mutationSettled(payload, expectation) {
			const tabs = (payload && payload.tabs) || {};
			const currentCount = Number(tabs.current || 0);

			if (expectation.type === 'primary') {
				return ((payload && payload.primary_uuid) || '') === expectation.orgUuid;
			}

			if (expectation.type === 'end') {
				return currentCount < expectation.baselineCurrent;
			}

			return currentCount > expectation.baselineCurrent;
		}

		clearStateCache() {
			try {
				const prefix = CACHE_PREFIX + ':' + this.config.id + ':' +
					WicketManageEmployer.hashString(this.settings) + ':';
				const doomed = [];

				for (let i = 0; i < window.sessionStorage.length; i++) {
					const key = window.sessionStorage.key(i);

					if (key && key.indexOf(prefix) === 0) {
						doomed.push(key);
					}
				}

				doomed.forEach((key) => window.sessionStorage.removeItem(key));
			} catch (e) {
				// Private mode or quota pressure: the fresh fetch still runs.
			}
		}

		callRest(route, params, method) {
			const url = wp.url.addQueryArgs(this.rest + route, params);
			const options = {
				url: url,
				method: method || 'GET',
				headers: { 'X-WP-Nonce': this.nonce }
			};

			if (options.method === 'POST' || options.method === 'PATCH') {
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

		static localToday() {
			const now = new Date();
			const month = String(now.getMonth() + 1).padStart(2, '0');
			const day = String(now.getDate()).padStart(2, '0');

			return now.getFullYear() + '-' + month + '-' + day;
		}

		showAddError(message) {
			if (!this.addErrorEl) {
				this.setFeedback(message, 'error');
				return;
			}

			this.addErrorEl.textContent = message;
			this.addErrorEl.hidden = false;

			if (this.searchInput) {
				this.searchInput.setAttribute('aria-invalid', 'true');
				this.searchInput.focus();
			}
		}

		hideAddError() {
			if (this.addErrorEl) {
				this.addErrorEl.textContent = '';
				this.addErrorEl.hidden = true;
			}

			if (this.searchInput) {
				this.searchInput.removeAttribute('aria-invalid');
			}
		}

		resetAddForm() {
			this.hideAddError();

			if (this.selectedInput) {
				this.selectedInput.value = '';
			}

			if (this.addBtn) {
				this.addBtn.disabled = true;
			}

			this.clearResults();

			if (this.searchInput) {
				this.searchInput.value = '';
			}
		}

		static errorList(errors) {
			// The end path returns a map of connection_id => message; the add path
			// a list. Normalize so partial failures never read as success.
			if (Array.isArray(errors)) {
				return errors.filter((item) => item !== null && item !== undefined && item !== '');
			}

			if (errors && typeof errors === 'object') {
				return Object.values(errors).filter((item) => item !== null && item !== undefined && item !== '');
			}

			return [];
		}

		static sprintf2(str, a, b) {
			// Function replacer: an org name holding $& or %s must not act as a
			// replacement pattern.
			return String(str)
				.replace('%1$s', () => a)
				.replace('%2$s', () => b)
				.replace('%s', () => a);
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
