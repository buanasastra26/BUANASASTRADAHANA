wp.domReady(() => {
	const BLOCK_LIST_POLL_INTERVAL = 100;
	const BLOCK_LIST_MAX_ATTEMPTS = 100;

	whenEditorIsReady()
		.then(waitForBlockList)
		.then(insertSubscriptionBlock);

	function insertSubscriptionBlock() {
		const autoloaderData = window.hostinger_reach_autoloader_data || {};
		const attributes = autoloaderData.formBuilderId
			? { formBuilderId: autoloaderData.formBuilderId }
			: {};
		const block = wp.blocks.createBlock('hostinger-reach/subscription', attributes);
		wp.data.dispatch('core/block-editor').insertBlocks(block);
	}

	function whenEditorIsReady() {
		return new Promise((resolve) => {
			const unsubscribe = wp.data.subscribe(() => {
				const editor = wp.data.select('core/editor');
				const isReady = typeof editor.__unstableIsEditorReady === 'function'
					? editor.__unstableIsEditorReady()
					: editor.isCleanNewPost() || wp.data.select('core/block-editor').getBlockCount() > 0;

				if (isReady) {
					unsubscribe();
					resolve();
				}
			});
		});
	}
	
	function waitForBlockList() {
		return new Promise((resolve) => {
			let attempts = 0;

			const check = () => {
				const canvasDocument = getEditorCanvasDocument();
				const isBlockListRendered = !!canvasDocument.querySelector('.block-editor-block-list__layout');

				if (isBlockListRendered || attempts >= BLOCK_LIST_MAX_ATTEMPTS) {
					resolve();
					return;
				}

				attempts += 1;
				setTimeout(check, BLOCK_LIST_POLL_INTERVAL);
			};

			check();
		});
	}

	function getEditorCanvasDocument() {
		const canvasIframe = document.querySelector('iframe[name="editor-canvas"]');

		if (canvasIframe && canvasIframe.contentDocument) {
			return canvasIframe.contentDocument;
		}

		return document;
	}
});
