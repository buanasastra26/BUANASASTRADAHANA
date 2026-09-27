<script setup lang="ts">
import { HNotificationRow } from '@hostinger/hcomponents';
import { computed } from 'vue';

import Banner from '@/components/Banner.vue';
import ReachContactsSummary from '@/components/ReachContactsSummary.vue';
import WooCommerceEntriesTable from '@/components/WooCommerceEntriesTable.vue';
import { useModal } from '@/composables';
import { WOOCOMMERCE_ID } from '@/data/pluginData';
import { TABS_KEYS } from '@/data/tabs';
import { useIntegrationsStore } from '@/stores/integrationsStore';
import { ModalName } from '@/types';
import type { Form } from '@/types/models';
import { translate } from '@/utils/translate';

const emit = defineEmits({
	goToPlugin: (_id: string) => true,
	disconnectPlugin: (_id: string) => true,
	toggleFormStatus: (_form: Form, _status: boolean) => true,
	viewForm: (_form: Form) => true,
	editForm: (_form: Form) => true,
	addForm: (_id: string) => true
});

const { openModal } = useModal();
const integrationsStore = useIntegrationsStore();

const shouldShowTable = computed(
	() =>
		!!integrationsStore?.activeIntegrations.find((i) => i.id === WOOCOMMERCE_ID) ||
		integrationsStore.hasAnyForms(TABS_KEYS.OVERVIEW_TAB_ECOMMERCE)
);

const isBannerVisible = computed(() => !integrationsStore.isLoading && !shouldShowTable.value);
const isTableVisible = computed(() => integrationsStore.isLoading || shouldShowTable.value);

const connectAndInstallWooCommerce = async () => {
	const success = await integrationsStore.toggleIntegrationStatus(WOOCOMMERCE_ID, true);
	if (success) {
		openModal(
			ModalName.SYNC_CONTACTS_MODAL,
			{
				title: translate('hostinger_reach_contacts_modal_title'),
				subtitle: translate('hostinger_reach_contacts_modal_subtitle'),
				data: { integrations: [integrationsStore?.activeIntegrations.find((i) => i.id === WOOCOMMERCE_ID)] }
			},
			{ hasCloseButton: true }
		);
	}

	return;
};
</script>

<template>
	<div class="woocommerce">
		<Banner
			v-if="isBannerVisible"
			:title="translate(`hostinger_reach_ecommerce_banner_title`)"
			:description="translate(`hostinger_reach_ecommerce_banner_description`)"
			:button-text="translate(`hostinger_reach_ecommerce_banner_button_text`)"
			:on-button-click="connectAndInstallWooCommerce"
			:is-button-loading="integrationsStore.isLoading"
		/>

		<ReachContactsSummary v-if="isTableVisible" />

		<WooCommerceEntriesTable
			v-if="isTableVisible"
			@go-to-plugin="emit('goToPlugin', $event)"
			@disconnect-plugin="emit('disconnectPlugin', $event)"
			@toggle-form-status="(form, status) => emit('toggleFormStatus', form, status)"
		/>

		<HNotificationRow
			v-if="shouldShowTable"
			:show-close-icon="false"
			:label="translate('hostinger_reach_woocommerce_connected_title')"
			:description="translate('hostinger_reach_woocommerce_connected_text')"
			:primary-action-text="translate('hostinger_reach_woocommerce_connected_button')"
			variant="info"
			:primary-action-props="{ iconAppend: 'ic-arrow-up-right-square-16', variant: 'outlineStrong' }"
			@primary-action-click="emit('goToPlugin', WOOCOMMERCE_ID)"
		/>
	</div>
</template>

<style scoped lang="scss">
.woocommerce {
	display: flex;
	flex-direction: column;
	align-self: stretch;
	gap: 20px;
	margin-bottom: 40px;
}
</style>
