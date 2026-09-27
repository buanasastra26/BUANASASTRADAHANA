<script setup lang="ts">
import { onMounted, ref } from 'vue';

import FAQ from '@/components/FAQ.vue';
import Hero from '@/components/Hero.vue';
import { useModal } from '@/composables/useModal';
import { useToast } from '@/composables/useToast';
import { connectFaqData } from '@/data/faq';
import { reachRepo } from '@/data/repositories/reachRepo';
import { ModalName } from '@/types/enums/modalEnum';
import { translate } from '@/utils/translate';

const TRUSTED_AUTH_DOMAINS = /^https:\/\/auth\.hostinger\.(dev|com)/;

const { showError } = useToast();

const isConnectedToAnotherSite = ref(false);
const isButtonLoading = ref(false);
const { openModal } = useModal();
const domain = window.location.hostname;

const handleGetStarted = async () => {
	isButtonLoading.value = true;

	const [data, error] = await reachRepo.generateAuthUrl();

	isButtonLoading.value = false;

	if (error || !data) {
		showError(error?.message || translate('hostinger_reach_error_message'));

		return;
	}

	if (data.authUrl && TRUSTED_AUTH_DOMAINS.test(data.authUrl)) {
		window.location.href = data.authUrl;
	} else {
		showError(translate('hostinger_reach_error_message'));
	}
};

const openApiKeyModal = (apiKey: string = '') => {
	openModal(ModalName.REACH_API_KEY_MODAL, { apiKey }, { hasCloseButton: true, isXL: true });
};

onMounted(() => {
	const params = new URLSearchParams(window.location.search);
	const key = params.get('api_key');

	if (key !== null) {
		openApiKeyModal();
	}
});
</script>

<template>
	<div class="welcome-view">
		<Hero
			:is-connected-to-another-site="isConnectedToAnotherSite"
			:is-button-loading="isButtonLoading"
			:domain="domain"
			:on-get-started="handleGetStarted"
			:on-manual-api-key-click="() => openApiKeyModal()"
		/>
		<div class="faq-wrap">
			<FAQ :faq-data="connectFaqData" />
		</div>
	</div>
</template>

<style scoped lang="scss">
.welcome-view {
	min-height: 100vh;
	padding: 0 16px;

	@media (max-width: 480px) {
		padding: 0 12px;
	}
}

@media (max-width: 320px) {
	.welcome-view {
		padding: 0 8px;
	}
}

.faq-wrap {
	max-width: 780px;
	margin: 24px auto;
}
</style>
