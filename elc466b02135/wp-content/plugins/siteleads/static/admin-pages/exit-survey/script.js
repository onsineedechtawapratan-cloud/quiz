(function () {
	const MODAL_ID = 'siteleads-exit-survey-modal';
	const DEACTIVATE_SELECTOR = 'a[data-siteleads-exit-survey="true"]';
	const SUBMIT_ACK_TIMEOUT_MS = 1000;
	const SUBMIT_COMPLETE_TIMEOUT_MS = 2000;

	let deactivateUrl = '';
	let iframeFailed = false;
	let formHasSelection = false;
	/** @type {ReturnType<typeof setTimeout>|null} */
	let submitFallbackTimer = null;

	/** @return {HTMLDialogElement|null} The exit survey dialog element. */
	function getModal() {
		return /** @type {HTMLDialogElement|null} */ (
			document.getElementById(MODAL_ID)
		);
	}

	/** @type {(modal: HTMLDialogElement, show: boolean) => void} */
	const toggleValidationWarning = function (modal, show) {
		const warning = /** @type {HTMLElement|null} */ (
			modal.querySelector('.siteleads-exit-survey-warning')
		);

		if (!warning) {
			return;
		}

		if (show) {
			warning.removeAttribute('hidden');
		} else {
			warning.setAttribute('hidden', 'hidden');
		}
	};

	function deactivatePlugin() {
		if (submitFallbackTimer) {
			clearTimeout(submitFallbackTimer);
			submitFallbackTimer = null;
		}

		if (deactivateUrl) {
			window.location.href = deactivateUrl;
		}
	}

	/** @param {number} delayMs */
	function scheduleSubmitFallback(delayMs) {
		if (submitFallbackTimer) {
			clearTimeout(submitFallbackTimer);
		}

		submitFallbackTimer = setTimeout(function () {
			submitFallbackTimer = null;
			deactivatePlugin();
		}, delayMs);
	}

	/**
	 * @param {HTMLDialogElement} modal
	 */
	function iframeLoadFailed(modal) {
		iframeFailed = true;
		formHasSelection = false;

		const iframe = /** @type {HTMLIFrameElement|null} */ (
			modal.querySelector('#siteleads-exit-survey-iframe')
		);
		if (iframe) {
			iframe.style.display = 'none';
		}
	}

	/** @param {HTMLDialogElement} modal */
	function bindEvents(modal) {
		if (!modal) {
			return;
		}

		modal.addEventListener('click', function (event) {
			const target = /** @type {{closest?: Function}|null} */ (
				event.target
			);
			if (!target || typeof target.closest !== 'function') {
				return;
			}

			if (target.closest('.siteleads-exit-survey-retry')) {
				if (typeof modal.close === 'function') {
					modal.close();
				} else {
					modal.removeAttribute('open');
				}
				return;
			}

			if (target.closest('.siteleads-exit-survey-skip')) {
				deactivatePlugin();
				return;
			}

			if (target.closest('.siteleads-exit-survey-submit')) {
				if (!formHasSelection) {
					toggleValidationWarning(modal, true);
					return;
				}

				toggleValidationWarning(modal, false);

				if (iframeFailed) {
					deactivatePlugin();
					return;
				}

				const iframe = /** @type {HTMLIFrameElement|null} */ (
					modal.querySelector('#siteleads-exit-survey-iframe')
				);
				if (iframe && iframe.contentWindow) {
					iframe.contentWindow.postMessage(
						{ action: 'siteleads_submit' },
						'*'
					);
					// Wait for submit-start ack from iframe before using longer fallback.
					scheduleSubmitFallback(SUBMIT_ACK_TIMEOUT_MS);
				} else {
					deactivatePlugin();
				}
			}
		});

		window.addEventListener('message', function (event) {
			if (event.data && event.data.action === 'siteleads_form_state') {
				formHasSelection =
					Boolean(event.data.hasSelection) && !iframeFailed;
				if (formHasSelection) {
					toggleValidationWarning(modal, false);
				}
				return;
			}

			if (
				event.data &&
				event.data.action === 'siteleads_submit_started'
			) {
				scheduleSubmitFallback(SUBMIT_COMPLETE_TIMEOUT_MS);
				const submitBtn = /** @type {HTMLButtonElement|null} */ (
					modal.querySelector('.siteleads-exit-survey-submit')
				);
				if (submitBtn) {
					submitBtn.style.display = 'none';
				}
				const skipBtn = /** @type {HTMLButtonElement|null} */ (
					modal.querySelector('.siteleads-exit-survey-skip')
				);
				if (skipBtn) {
					skipBtn.style.display = 'none';
				}
				return;
			}

			if (event.data && event.data.action === 'siteleads_form_submit') {
				deactivatePlugin();
				return;
			}

			if (event.data && event.data.action === 'siteleads_iframe_height') {
				const iframe = /** @type {HTMLIFrameElement|null} */ (
					modal.querySelector('#siteleads-exit-survey-iframe')
				);
				const height = Number(event.data.height);
				if (!iframe || !Number.isFinite(height)) {
					return;
				}

				iframe.style.height =
					Math.max(125, Math.ceil(height) + 20) + 'px';
			}
		});

		const iframe = /** @type {HTMLIFrameElement|null} */ (
			modal.querySelector('#siteleads-exit-survey-iframe')
		);
		if (iframe) {
			const iframeLoadTimer = setTimeout(function () {
				iframeLoadFailed(modal);
			}, 2000);

			iframe.addEventListener('load', function () {
				clearTimeout(iframeLoadTimer);
			});

			iframe.addEventListener('error', function () {
				clearTimeout(iframeLoadTimer);
				iframeLoadFailed(modal);
			});
		}
	}

	function init() {
		const modal = getModal();
		if (!modal) {
			return;
		}

		bindEvents(modal);

		document.addEventListener('click', function (event) {
			const target = /** @type {{closest?: Function}|null} */ (
				event.target
			);
			if (!target) {
				return;
			}

			if (typeof target.closest !== 'function') {
				return;
			}

			const deactivateLink = target.closest(DEACTIVATE_SELECTOR);
			if (!deactivateLink) {
				return;
			}

			if (iframeFailed) {
				return;
			}

			event.preventDefault();
			deactivateUrl = deactivateLink.getAttribute('href') || '';
			formHasSelection = false;
			toggleValidationWarning(modal, false);
			if (typeof modal.showModal === 'function') {
				modal.showModal();
			} else {
				modal.setAttribute('open', 'open');
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
