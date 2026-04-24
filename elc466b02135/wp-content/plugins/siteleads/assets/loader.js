(async function (settings = {}) {
	const url = new URL(window.location.href);
	const urlParams = new URLSearchParams(url.search);

	const MOBILE_MAX_WIDTH = 600;

	function isPhone() {
		// 1. URL Override for testing
		if (urlParams.get('siteleads-preview')) {
			return urlParams.get('preview-device') === 'mobile';
		}

		const shortSide = Math.min(window.innerWidth, window.innerHeight);

		// 2. Check for Touch Primary Input
		const isTouchPrimary = window.matchMedia('(pointer: coarse)').matches;

		return (
			shortSide <= MOBILE_MAX_WIDTH && // Lowered to 480 to exclude most tablets
			isTouchPrimary
		);
	}

	const getTimestamp = () => {
		const date = new Date();
		const ts = Math.floor(date.getTime() / 1000);
		return ts;
	};

	const generateUniqueId = () => {
		if (window.crypto && window.crypto.randomUUID) {
			return crypto.randomUUID();
		}

		const uuid = new Array(36);
		for (let i = 0; i < 36; i++) {
			uuid[i] = Math.floor(Math.random() * 16);
		}
		uuid[14] = 4;
		uuid[19] = uuid[19] &= ~(1 << 2);
		uuid[19] = uuid[19] |= 1 << 3;
		uuid[8] = uuid[13] = uuid[18] = uuid[23] = '-';
		return uuid.map((x) => x.toString(16)).join('');
	};

	settings = {
		...settings,
		getVisitorId: () => {
			let id = window.localStorage.getItem('siteleads_visitor_id');
			if (!id) {
				id = generateUniqueId();
				window.localStorage.setItem('siteleads_visitor_id', id);
			}
			return id;
		},
	};

	settings.getVisitorId();

	const browserTime = getTimestamp();

	const { server_time: serverTime = 0, referrer = '' } = settings;

	const getReferrer = () => {
		if (
			serverTime &&
			browserTime &&
			parseInt(serverTime) + 2 > browserTime &&
			referrer
		) {
			return {
				source: 'server',
				referrer,
			};
		}

		return {
			source: 'browser',
			referrer: document.referrer || '',
		};
	};

	const widgetIds = [
		...document.querySelectorAll('[data-siteleads-widget-placeholders]'),
	]
		.map((el) => el.getAttribute('data-siteleads-widget-placeholders'))
		.filter(Boolean);

	const formData = new FormData();
	formData.append('siteleads_load_widgets', '1');

	const payload = {
		widgets: widgetIds,
		referrer: getReferrer(),
		timestamp: new Date().toISOString(),
		device: isPhone() ? 'mobile' : 'desktop',
	};

	formData.append('payload', JSON.stringify(payload));

	try {
		const response = await fetch(url, {
			method: 'POST',
			body: formData,
		});

		const data = await response.json();

		if (data.success) {
			const responseData = data.data || {};
			// @ts-ignore
			settings = {
				...settings,
				...responseData,
			};

			if (Array.isArray(responseData.styles)) {
				responseData.styles.forEach((style) => {
					if (style.type === 'url' && style.value) {
						const link = document.createElement('link');
						link.setAttribute(
							'siteleads-dynamic-link-element',
							'1'
						);
						link.rel = 'stylesheet';
						link.href = style.value;
						document.head.appendChild(link);
					}

					if (style.type === 'inline' && style.value) {
						const styleEl = document.createElement('style');
						styleEl.setAttribute(
							'siteleads-dynamic-style-element',
							'1'
						);
						styleEl.innerHTML = style.value;
						document.head.appendChild(styleEl);
					}
				});
			}
		}
	} catch (error) {
		console.error('Error fetching popups data:', error);
	}

	// @ts-ignore
	window.siteLeadsData = settings;

	document.body.setAttribute('data-siteleads-widgets-remote-loaded', '1');
	const event = new CustomEvent('siteleads-widgets-remote-loaded');
	document.dispatchEvent(event);

	// @ts-ignore
})(window.siteLeadsData || {});
