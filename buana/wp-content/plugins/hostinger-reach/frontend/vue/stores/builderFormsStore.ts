import { defineStore } from 'pinia';
import { ref } from 'vue';

import type { BuilderForm } from '@/data/repositories/reachRepo';
import { reachRepo } from '@/data/repositories/reachRepo';

const REST_BASE = `${hostinger_reach_reach_data.rest_base_url}hostinger-reach/v1`;
const REST_NONCE = hostinger_reach_reach_data.nonce;

export const useBuilderFormsStore = defineStore('builderFormsStore', () => {
	const forms = ref<BuilderForm[]>([]);
	const isLoading = ref(false);
	const isLoaded = ref(false);

	let inFlight: Promise<void> | null = null;

	const previewImageUrl = (uuid: string) =>
		`${REST_BASE}/builder-form-preview/${uuid}?_wpnonce=${encodeURIComponent(REST_NONCE)}`;

	const preloadPreviews = () => {
		if (typeof Image === 'undefined') return;

		forms.value.forEach((form) => {
			const image = new Image();
			image.src = previewImageUrl(form.uuid);
		});
	};

	const fetchForms = async () => {
		isLoading.value = true;

		const [response] = await reachRepo.getBuilderForms();
		forms.value = (response?.resources ?? []).filter((form) => form.status === 'active');

		preloadPreviews();

		isLoaded.value = true;
		isLoading.value = false;
	};

	const loadForms = async (force = false) => {
		if (isLoaded.value && !force) return;
		if (inFlight) return inFlight;

		inFlight = fetchForms().finally(() => {
			inFlight = null;
		});

		return inFlight;
	};

	return {
		forms,
		isLoading,
		isLoaded,
		previewImageUrl,
		loadForms
	};
});
