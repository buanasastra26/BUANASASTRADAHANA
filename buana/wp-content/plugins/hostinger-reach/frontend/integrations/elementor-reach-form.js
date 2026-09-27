import './elementor-reach-form.scss';

(function () {
	const data = window.hostinger_reach_elementor_data || {};
	const REST_URL = data.restUrl || '/wp-json/';
	const RESOURCE_ID = data.resourceId || '';
	const REACH_DOMAIN = data.reachDomain || 'https://reach.hostinger.com/';
	const DOMAIN = data.domain || '';
	const NONCE = data.nonce || '';
	const WIDGET_NAME = data.widgetName || 'hostinger-reach';
	const EMBED_SCRIPT = data.embedScript;
	const IS_CONNECTED = data.isConnected !== 'no';
	const CONNECT_URL = data.connectUrl || '/wp-admin/admin.php?page=hostinger-reach';
	const i18n = data.i18n || {};

	const t = (key, fallback) => i18n[key] || fallback;

	const previewImageUrl = (uuid) =>
		`${REST_URL}hostinger-reach/v1/builder-form-preview/${uuid}?_wpnonce=${encodeURIComponent(NONCE)}`;
	const formEditUrl = (uuid) =>
		`${REACH_DOMAIN}?resourceId=${encodeURIComponent(RESOURCE_ID)}` +
		`&routeTo=form-publish` +
		`&routeId=${encodeURIComponent(uuid)}`;
	const reachCreateFormUrl = () =>
		RESOURCE_ID
			? `${REACH_DOMAIN}?resourceId=${encodeURIComponent(RESOURCE_ID)}&domain=${encodeURIComponent(DOMAIN)}&routeTo=forms&action=create-form`
			: `${REACH_DOMAIN}?routeTo=forms&action=create-form`;
	const isValidFormId = (id) => /^[a-zA-Z0-9-]+$/.test(id || '');
	const previewDoc = (uuid) =>
		isValidFormId(uuid)
			? `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">` +
				`<div data-reach-form="${uuid}"></div>` +
				`<script src="${EMBED_SCRIPT}" defer></scr` +
				`ipt></body></html>`
			: `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;"></body></html>`;

	const REACH_SVG =
		'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M13.9245 7.35306L18.0629 3.21429L20.7851 5.9365L18.5031 8.21843H15.8003L15.7818 8.20957L10.0742 5.67124V1.5H13.9245V7.35306Z" fill="#1D1E20"/>' +
		'<path d="M5.49634 15.7812L8.21686 15.7893L13.9257 18.3284V22.4997H10.0754L10.0741 16.6488L5.93704 20.7854L3.21484 18.0632L5.49634 15.7812Z" fill="#1D1E20"/>' +
		'<path d="M22.5008 10.0762H18.3287L15.7832 15.8002V18.5055L18.0643 20.7866L20.7865 18.064L16.6477 13.9256L22.5008 13.9248V10.0762Z" fill="#1D1E20"/>' +
		'<path d="M5.9365 3.21484L8.21632 5.49466V8.20463L5.67208 13.9249H1.5V10.0763H7.35306L3.21429 5.93789L5.9365 3.21484Z" fill="#1D1E20"/>' +
		'</svg>';

	const PENCIL_SVG =
		'<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
		'<path fill-rule="evenodd" clip-rule="evenodd" d="M6.85667 14.1806C6.85667 13.7664 7.14459 13.4306 7.49976 13.4306H13.6067C13.9619 13.4306 14.2498 13.7664 14.2498 14.1806C14.2498 14.5949 13.9619 14.9306 13.6067 14.9306H7.49976C7.14459 14.9306 6.85667 14.5949 6.85667 14.1806Z" fill="#673DE6"/>' +
		'<path fill-rule="evenodd" clip-rule="evenodd" d="M4.29059 12.6648L13.0584 3.80217C13.0832 3.77712 13.1066 3.75351 13.1286 3.73115C13.1063 3.70902 13.0827 3.68567 13.0577 3.66088L12.2096 2.82162C12.1845 2.79681 12.1609 2.77343 12.1385 2.75132C12.1163 2.77365 12.0929 2.79725 12.0681 2.8223L6.68571 8.25206L3.29933 11.6871C3.29304 11.6935 3.28714 11.6995 3.28159 11.7051C3.27938 11.7127 3.27703 11.7208 3.27453 11.7294L2.87773 13.0942L4.24774 12.6904C4.25651 12.6878 4.26473 12.6854 4.27247 12.6831C4.27814 12.6774 4.28416 12.6713 4.29059 12.6648ZM5.6195 7.19698L2.23021 10.635C2.12442 10.7423 2.07152 10.796 2.0277 10.8561C1.9888 10.9095 1.95529 10.9666 1.92766 11.0266C1.89654 11.0941 1.87551 11.1665 1.83345 11.3111L1.24282 13.3426C1.05669 13.9828 0.963621 14.3029 1.04553 14.5207C1.11691 14.7106 1.2672 14.8601 1.45749 14.9306C1.67583 15.0116 1.99583 14.9173 2.63581 14.7287L4.67228 14.1285C4.81967 14.0851 4.89337 14.0634 4.96204 14.0312C5.02302 14.0027 5.08096 13.9681 5.13498 13.9279C5.19581 13.8826 5.24982 13.828 5.35786 13.7188L14.1257 4.85619C14.5192 4.4584 14.716 4.25951 14.7889 4.03103C14.853 3.83004 14.8519 3.61394 14.7856 3.41365C14.7103 3.18595 14.5114 2.98916 14.1137 2.59558L13.2656 1.75632C12.8675 1.36239 12.6684 1.16542 12.4396 1.09237C12.2384 1.0281 12.022 1.02915 11.8213 1.09534C11.5933 1.1706 11.3961 1.36947 11.0019 1.76722L5.6195 7.19698Z" fill="#673DE6"/>' +
		'</svg>';

	const ARROW_SVG =
		'<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">' +
		'<path d="M2.5 5.24805C2.5 3.72926 3.73122 2.49805 5.25 2.49805H6C6.41421 2.49805 6.75 2.83383 6.75 3.24805C6.75 3.66226 6.41421 3.99805 6 3.99805H5.25C4.55964 3.99805 4 4.55769 4 5.24805V10.6861C4 11.3765 4.55964 11.9361 5.25 11.9361H10.7508C11.4411 11.9361 12.0008 11.3765 12.0008 10.6861V10.0002C12.0008 9.58603 12.3366 9.25024 12.7508 9.25024C13.165 9.25024 13.5008 9.58603 13.5008 10.0002V10.6861C13.5008 12.2049 12.2696 13.4361 10.7508 13.4361H5.25C3.73122 13.4361 2.5 12.2049 2.5 10.6861V5.24805Z" fill="#673DE6"/>' +
		'<path d="M12 5.06101L8.03033 9.03068C7.73744 9.32357 7.26256 9.32357 6.96967 9.03068C6.67678 8.73779 6.67678 8.26291 6.96967 7.97002L10.9393 4.00035H9C8.58579 4.00035 8.25 3.66456 8.25 3.25035C8.25 2.83614 8.58579 2.50035 9 2.50035L12.25 2.50035C12.9404 2.50035 13.5 3.05999 13.5 3.75035V7.00035C13.5 7.41456 13.1642 7.75035 12.75 7.75035C12.3358 7.75035 12 7.41456 12 7.00035V5.06101Z" fill="#673DE6"/>' +
		'</svg>';

	const REFRESH_SVG =
		'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
		'<path d="M4 12a8 8 0 0 1 13.657-5.657L20 8m0 0V3m0 5h-5M20 12a8 8 0 0 1-13.657 5.657L4 16m0 0v5m0-5h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
		'</svg>';

	const FILE_SVG =
		'<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
		'<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" stroke="#18181A" stroke-width="1.5" stroke-linejoin="round"/>' +
		'<path d="M14 3v5h5" stroke="#18181A" stroke-width="1.5" stroke-linejoin="round"/>' +
		'</svg>';

	const PLUS_SVG =
		'<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">' +
		'<path d="M8 3.25v9.5M3.25 8h9.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>' +
		'</svg>';

	function createConnectBanner() {
		const banner = document.createElement('div');
		banner.className = 'hostinger-reach-elementor-connect';

		const title = document.createElement('div');
		title.className = 'hostinger-reach-elementor-connect__title';
		title.textContent = t('connectTitle', 'You are not connected to Hostinger Reach');
		banner.appendChild(title);

		const subtitle = document.createElement('div');
		subtitle.className = 'hostinger-reach-elementor-connect__subtitle';
		subtitle.textContent = t(
			'connectSubtitle',
			'You are not connected to Hostinger Reach. To gain full access to this block, you need to connect your Hostinger Reach account.'
		);
		banner.appendChild(subtitle);

		return banner;
	}

	function createEmptyState(onRefresh) {
		const emptyState = document.createElement('div');
		emptyState.className = 'hostinger-reach-elementor-modal__empty-state';

		const emptyIcon = document.createElement('div');
		emptyIcon.className = 'hostinger-reach-elementor-modal__empty-icon';
		emptyIcon.innerHTML = FILE_SVG;
		emptyState.appendChild(emptyIcon);

		const emptyBody = document.createElement('div');
		emptyBody.className = 'hostinger-reach-elementor-modal__empty-body';

		const emptyText = document.createElement('div');
		emptyText.className = 'hostinger-reach-elementor-modal__empty-text';

		const emptyTitle = document.createElement('h3');
		emptyTitle.className = 'hostinger-reach-elementor-modal__empty-title';
		emptyTitle.textContent = t('emptyTitle', 'No forms yet');
		emptyText.appendChild(emptyTitle);

		const emptySubtitle = document.createElement('p');
		emptySubtitle.className = 'hostinger-reach-elementor-modal__empty-subtitle';
		emptySubtitle.textContent = t('emptySubtitle', 'Build your first form in Hostinger Reach and add it to any page on your site.');
		emptyText.appendChild(emptySubtitle);

		emptyBody.appendChild(emptyText);

		const emptyAction = document.createElement('div');
		emptyAction.className = 'hostinger-reach-elementor-modal__empty-action';
		const emptyCreate = document.createElement('a');
		emptyCreate.className = 'hostinger-reach-elementor-modal__empty-create';
		emptyCreate.href = reachCreateFormUrl();
		emptyCreate.target = '_blank';
		emptyCreate.rel = 'noopener noreferrer';
		emptyCreate.insertAdjacentHTML('beforeend', PLUS_SVG);
		const emptyCreateLabel = document.createElement('span');
		emptyCreateLabel.textContent = t('emptyCreate', 'Create a form in Reach');
		emptyCreate.appendChild(emptyCreateLabel);
		emptyAction.appendChild(emptyCreate);

		if (typeof onRefresh === 'function') {
			const emptyRefresh = document.createElement('button');
			emptyRefresh.type = 'button';
			emptyRefresh.className = 'hostinger-reach-elementor-modal__empty-refresh';
			emptyRefresh.insertAdjacentHTML('beforeend', REFRESH_SVG);
			const emptyRefreshLabel = document.createElement('span');
			emptyRefreshLabel.textContent = t('refresh', 'Refresh forms');
			emptyRefresh.appendChild(emptyRefreshLabel);
			emptyRefresh.addEventListener('click', onRefresh);
			emptyAction.appendChild(emptyRefresh);
		}

		emptyBody.appendChild(emptyAction);

		emptyState.appendChild(emptyBody);

		return emptyState;
	}

	let formsCache = null;
	let formsFetching = false;
	let pendingCallbacks = [];

	function fetchForms(callback) {
		if (formsCache) {
			callback(formsCache);
			return;
		}

		if (typeof callback === 'function') {
			pendingCallbacks.push(callback);
		}

		if (formsFetching) {
			return;
		}

		formsFetching = true;

		fetch(`${REST_URL}hostinger-reach/v1/builder-forms?_=${Date.now()}`, {
			headers: { 'X-WP-Nonce': NONCE },
		})
			.then((response) => (response.ok ? response.json() : null))
			.then((json) => {
				const resources = (json && json.data && json.data.resources) || [];
				formsCache = resources
					.filter((resource) => resource.status === 'active')
					.map((resource) => ({ uuid: resource.uuid, name: resource.name }));
			})
			.catch(() => {
				formsCache = [];
			})
			.finally(() => {
				formsFetching = false;
				const callbacks = pendingCallbacks;
				pendingCallbacks = [];
				callbacks.forEach((cb) => cb(formsCache));
			});
	}

	function getFormName(uuid) {
		if (!formsCache) {
			return t('loading', 'Loading…');
		}

		const form = formsCache.find((item) => item.uuid === uuid);
		return form ? form.name : uuid;
	}

	function resolveContainer(ctx) {
		ctx = ctx || {};

		const attempts = [
			() => ctx.view && typeof ctx.view.getContainer === 'function' && ctx.view.getContainer(),
			() => ctx.view && ctx.view.container,
			() => ctx.model && typeof ctx.model.getContainer === 'function' && ctx.model.getContainer(),
			() => {
				const element = window.elementor && elementor.getCurrentElement && elementor.getCurrentElement();
				if (!element) {
					return null;
				}
				if (typeof element.getContainer === 'function') {
					return element.getContainer();
				}
				return element.container || null;
			},
		];

		for (let i = 0; i < attempts.length; i++) {
			try {
				const container = attempts[i]();
				if (container) {
					return container;
				}
			} catch (e) {}
		}

		return null;
	}

	function getSetting(ctx, key) {
		ctx = ctx || {};

		try {
			const container = resolveContainer(ctx);
			if (container && container.settings && container.settings.get) {
				return container.settings.get(key);
			}
		} catch (e) {}

		try {
			if (ctx.model && typeof ctx.model.getSetting === 'function') {
				return ctx.model.getSetting(key);
			}
		} catch (e) {}

		return '';
	}

	function forceRender(ctx, container) {
		try {
			if (container && typeof container.render === 'function') {
				container.render();
			}
		} catch (e) {}

		try {
			if (ctx && ctx.view && typeof ctx.view.renderOnChange === 'function') {
				ctx.view.renderOnChange();
			} else if (ctx && ctx.view && typeof ctx.view.render === 'function') {
				ctx.view.render();
			}
		} catch (e) {}
	}

	function applySettings(ctx, settings) {
		const container = resolveContainer(ctx);

		if (container && window.$e && window.$e.run) {
			try {
				window.$e.run('document/elements/settings', { container, settings });
				forceRender(ctx, container);
				return;
			} catch (e) {}
		}

		if (ctx.model && typeof ctx.model.setSetting === 'function') {
			Object.keys(settings).forEach((key) => ctx.model.setSetting(key, settings[key]));
		}

		forceRender(ctx, container);
	}

	function getCurrentTemplateId(ctx) {
		if ((getSetting(ctx, 'formBuilderManual') || '') === 'yes') {
			return (getSetting(ctx, 'formBuilderIdManual') || '').trim();
		}

		return (getSetting(ctx, 'formBuilderId') || '').trim();
	}

	function renderUseFormBuilderButton(mount, render) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'hostinger-reach-elementor-selector__use';
		button.textContent = t('useTemplate', 'Use Form builder template');
		button.addEventListener('click', () => openModal(mount.__reachCtx, render));
		mount.appendChild(button);
	}

	function renderConnectButton(mount) {
		const button = document.createElement('a');
		button.className = 'hostinger-reach-elementor-selector__use';
		button.href = CONNECT_URL;
		button.target = '_blank';
		button.rel = 'noopener noreferrer';
		button.textContent = t('connectToReach', 'Connect to Reach');
		mount.appendChild(button);
	}

	function renderCard(mount, uuid, render) {
		const card = document.createElement('div');
		card.className = 'hostinger-reach-elementor-card';

		const title = document.createElement('div');
		title.className = 'hostinger-reach-elementor-card__title';
		title.innerHTML = REACH_SVG;
		const name = document.createElement('span');
		name.className = 'hostinger-reach-elementor-card__name';
		name.textContent = IS_CONNECTED
			? getFormName(uuid)
			: t('cardNotConnected', 'Reach is not Connected');
		title.appendChild(name);
		card.appendChild(title);

		if (!IS_CONNECTED) {
			const connect = document.createElement('a');
			connect.className = 'hostinger-reach-elementor-card__change';
			connect.href = CONNECT_URL;
			connect.target = '_blank';
			connect.rel = 'noopener noreferrer';
			connect.textContent = t('connectToReach', 'Connect to Reach');
			card.appendChild(connect);
			mount.appendChild(card);
			return;
		}

		const image = document.createElement('img');
		image.className = 'hostinger-reach-elementor-card__image';
		image.src = previewImageUrl(uuid);
		image.alt = getFormName(uuid);
		image.loading = 'lazy';
		card.appendChild(image);

		const change = document.createElement('button');
		change.type = 'button';
		change.className = 'hostinger-reach-elementor-card__change';
		change.textContent = t('changeSelection', 'Change the selection');
		change.addEventListener('click', () => openModal(mount.__reachCtx, render));
		card.appendChild(change);

		if (RESOURCE_ID) {
			const edit = document.createElement('a');
			edit.className = 'hostinger-reach-elementor-card__edit';
			edit.href = formEditUrl(uuid);
			edit.target = '_blank';
			edit.rel = 'noopener noreferrer';
			edit.innerHTML =
				PENCIL_SVG +
				`<span class="hostinger-reach-elementor-card__edit-text">${t('editInReach', 'Edit in Reach')}</span>` +
				ARROW_SVG;
			card.appendChild(edit);
		}

		mount.appendChild(card);
	}

	function renderClassicButton(mount, render) {
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'hostinger-reach-elementor-selector__classic';
		button.textContent = t('useClassic', 'Use classic Reach block');
		button.addEventListener('click', () => {
			applySettings(mount.__reachCtx, {
				formBuilderManual: '',
				formBuilderIdManual: '',
				formBuilderId: '',
			});
			render();
		});
		mount.appendChild(button);
	}

	function buildSelector(mount, ctx) {
		mount.__reachCtx = ctx;

		const render = () => {
			const current = getCurrentTemplateId(mount.__reachCtx);
			mount.innerHTML = '';

			if (!IS_CONNECTED && !current) {
				mount.appendChild(createConnectBanner());
			}

			if (!current) {
				if (IS_CONNECTED) {
					renderUseFormBuilderButton(mount, render);
				} else {
					renderConnectButton(mount);
				}
				return;
			}

			if (IS_CONNECTED && !formsCache) {
				fetchForms(() => render());
			}

			renderCard(mount, current, render);
			renderClassicButton(mount, render);
		};

		mount.__reachRender = render;
		render();
	}

	function openModal(ctx, onDone) {
		const overlay = document.createElement('div');
		overlay.className = 'hostinger-reach-elementor-modal-overlay';

		const modal = document.createElement('div');
		modal.className = 'hostinger-reach-elementor-modal';
		overlay.appendChild(modal);

		const header = document.createElement('div');
		header.className = 'hostinger-reach-elementor-modal__header';
		const heading = document.createElement('h2');
		heading.className = 'hostinger-reach-elementor-modal__title';
		heading.textContent = t('selectForm', 'Select a form');
		const close = document.createElement('button');
		close.type = 'button';
		close.className = 'hostinger-reach-elementor-modal__close';
		close.setAttribute('aria-label', t('cancel', 'Cancel'));
		close.innerHTML = '&times;';
		header.appendChild(heading);
		header.appendChild(close);
		modal.appendChild(header);

		const searchWrap = document.createElement('div');
		searchWrap.className = 'hostinger-reach-elementor-modal__search';
		const searchInput = document.createElement('input');
		searchInput.type = 'search';
		searchInput.className = 'hostinger-reach-elementor-modal__search-input';
		searchInput.placeholder = t('searchPlaceholder', 'Search forms');
		searchInput.setAttribute('aria-label', t('searchPlaceholder', 'Search forms'));
		searchWrap.appendChild(searchInput);

		const body = document.createElement('div');
		body.className = 'hostinger-reach-elementor-modal__body';
		modal.appendChild(body);

		const listCol = document.createElement('div');
		listCol.className = 'hostinger-reach-elementor-modal__list-col';
		listCol.appendChild(searchWrap);

		const list = document.createElement('div');
		list.className = 'hostinger-reach-elementor-modal__list';
		listCol.appendChild(list);

		const preview = document.createElement('div');
		preview.className = 'hostinger-reach-elementor-modal__preview';
		const frame = document.createElement('iframe');
		frame.className = 'hostinger-reach-elementor-modal__preview-frame';
		frame.title = t('selectForm', 'Select a form');
		preview.appendChild(frame);

		const footer = document.createElement('div');
		footer.className = 'hostinger-reach-elementor-modal__footer';
		const footerStart = document.createElement('div');
		footerStart.className = 'hostinger-reach-elementor-modal__footer-start';
		const createLink = document.createElement('a');
		createLink.className = 'hostinger-reach-elementor-modal__create';
		createLink.href = reachCreateFormUrl();
		createLink.target = '_blank';
		createLink.rel = 'noopener noreferrer';
		createLink.textContent = t('createForm', 'Create a new form');
		const refreshBtn = document.createElement('button');
		refreshBtn.type = 'button';
		refreshBtn.className = 'hostinger-reach-elementor-modal__refresh';
		refreshBtn.setAttribute('aria-label', t('refresh', 'Refresh forms'));
		refreshBtn.title = t('refresh', 'Refresh forms');
		refreshBtn.innerHTML = REFRESH_SVG;
		footerStart.appendChild(createLink);
		footerStart.appendChild(refreshBtn);
		const footerActions = document.createElement('div');
		footerActions.className = 'hostinger-reach-elementor-modal__footer-actions';
		const cancelBtn = document.createElement('button');
		cancelBtn.type = 'button';
		cancelBtn.className = 'hostinger-reach-elementor-modal__cancel';
		cancelBtn.textContent = t('cancel', 'Cancel');
		const continueBtn = document.createElement('button');
		continueBtn.type = 'button';
		continueBtn.className = 'hostinger-reach-elementor-modal__continue';
		continueBtn.textContent = t('continue', 'Continue');
		continueBtn.disabled = true;
		footerActions.appendChild(cancelBtn);
		footerActions.appendChild(continueBtn);
		footer.appendChild(footerStart);
		footer.appendChild(footerActions);
		modal.appendChild(footer);

		document.body.appendChild(overlay);

		const destroy = () => overlay.remove();
		close.addEventListener('click', destroy);
		cancelBtn.addEventListener('click', destroy);
		overlay.addEventListener('click', (event) => {
			if (event.target === overlay) {
				destroy();
			}
		});

		let selectedId = getCurrentTemplateId(ctx);
		let allForms = [];
		let searchQuery = '';
		let renderedPreviewId = null;

		continueBtn.addEventListener('click', () => {
			if (!selectedId) {
				return;
			}
			applySettings(ctx, {
				formBuilderManual: 'yes',
				formBuilderIdManual: selectedId,
			});
			destroy();
			if (typeof onDone === 'function') {
				onDone();
			}
		});

		function refreshForms() {
			formsCache = null;
			continueBtn.disabled = true;
			renderBody();
		}

		refreshBtn.addEventListener('click', refreshForms);

		searchInput.addEventListener('input', () => {
			searchQuery = searchInput.value;
			renderResults();
		});

		const getFilteredForms = () => {
			const query = searchQuery.trim().toLowerCase();

			if (!query) {
				return allForms;
			}

			return allForms.filter((form) => form.name.toLowerCase().includes(query));
		};

		const ensureResultsLayout = () => {
			if (body.firstChild === listCol) {
				return;
			}
			body.textContent = '';
			body.appendChild(listCol);
			body.appendChild(preview);
		};

		const renderResults = () => {
			const forms = getFilteredForms();

			list.textContent = '';

			if (!forms.length) {
				const noResults = document.createElement('div');
				noResults.className = 'hostinger-reach-elementor-modal__no-results';
				noResults.textContent = t('noResults', 'No forms match your search.');
				list.appendChild(noResults);
				continueBtn.disabled = !selectedId;
				frame.style.display = 'none';
				return;
			}

			if (!selectedId || !forms.some((form) => form.uuid === selectedId)) {
				selectedId = forms[0].uuid;
			}
			continueBtn.disabled = false;
			frame.style.display = '';

			forms.forEach((form) => {
				const item = document.createElement('button');
				item.type = 'button';
				item.className = 'hostinger-reach-elementor-modal__item';
				if (form.uuid === selectedId) {
					item.classList.add('is-selected');
				}

				const itemName = document.createElement('span');
				itemName.className = 'hostinger-reach-elementor-modal__item-name';
				itemName.textContent = form.name;
				item.appendChild(itemName);

				const thumb = document.createElement('img');
				thumb.className = 'hostinger-reach-elementor-modal__item-thumb';
				thumb.src = previewImageUrl(form.uuid);
				thumb.alt = form.name;
				thumb.loading = 'lazy';
				item.appendChild(thumb);

				item.addEventListener('click', () => {
					selectedId = form.uuid;
					list.querySelectorAll('.hostinger-reach-elementor-modal__item').forEach((el) =>
						el.classList.remove('is-selected')
					);
					item.classList.add('is-selected');
					if (renderedPreviewId !== selectedId) {
						renderedPreviewId = selectedId;
						frame.srcdoc = previewDoc(selectedId);
					}
				});

				list.appendChild(item);
			});

			if (renderedPreviewId !== selectedId) {
				renderedPreviewId = selectedId;
				frame.srcdoc = previewDoc(selectedId);
			}
		};

		const renderBody = () => {
			body.textContent = '';

			const loading = document.createElement('div');
			loading.className = 'hostinger-reach-elementor-modal__loading';
			loading.innerHTML =
				'<span class="hostinger-reach-elementor-spinner" role="status" aria-label="' + t('loading', 'Loading…') + '"></span>';
			body.appendChild(loading);

			fetchForms((forms) => {
				allForms = forms || [];

				if (!allForms.length) {
					body.textContent = '';
					const empty = document.createElement('div');
					empty.className = 'hostinger-reach-elementor-modal__empty';
					empty.appendChild(createEmptyState(refreshForms));
					body.appendChild(empty);
					footer.style.display = 'none';
					continueBtn.disabled = true;
					return;
				}

				footer.style.display = '';
				ensureResultsLayout();
				renderResults();
			});
		};

		renderBody();
	}

	function initMount(mount, ctx) {
		if (mount.__reachInit) {
			mount.__reachCtx = ctx;
			if (mount.__reachRender) {
				mount.__reachRender();
			}
			return;
		}

		mount.__reachInit = true;
		buildSelector(mount, ctx);
	}

	function watchPanel(panelEl, ctx) {
		const observer = new MutationObserver(() => {
			const mount = panelEl.querySelector('.hostinger-reach-elementor-selector');
			if (mount && !mount.__reachInit) {
				initMount(mount, ctx);
			}
		});

		observer.observe(panelEl, { childList: true, subtree: true });
	}

	function onPanelOpen(panel, model, view) {
		const ctx = { model, view };
		const panelEl = panel && panel.$el ? panel.$el[0] : document;
		let attempts = 0;

		const interval = setInterval(() => {
			const mount = panelEl.querySelector('.hostinger-reach-elementor-selector');
			if (mount) {
				clearInterval(interval);
				initMount(mount, ctx);
				watchPanel(panelEl, ctx);
			} else if (++attempts > 40) {
				clearInterval(interval);
			}
		}, 100);
	}

	function registerHook() {
		if (!window.elementor || !window.elementor.hooks) {
			return false;
		}

		window.elementor.hooks.addAction(`panel/open_editor/widget/${WIDGET_NAME}`, onPanelOpen);
		return true;
	}

	if (!registerHook()) {
		window.addEventListener('elementor/init', registerHook);
	}
})();
