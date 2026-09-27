<script setup lang="ts">
import { computed } from 'vue';

import Banner from '@/components/Banner.vue';
import PluginEntriesTable from '@/components/PluginEntriesTable.vue';
import RecommendedPlugins from '@/components/RecommendedPlugins.vue';
import { TABS_KEYS } from '@/data/tabs';
import { useIntegrationsStore } from '@/stores/integrationsStore';
import type { Form, Integration } from '@/types/models';
import { translate } from '@/utils/translate';

type IntegrationsProps = {
	onBannerButtonClick?: () => void;
};

const props = defineProps<IntegrationsProps>();

const emit = defineEmits({
	goToPlugin: (_id: string) => true,
	disconnectPlugin: (_id: string) => true,
	toggleFormStatus: (_form: Form, _status: boolean) => true,
	viewForm: (_form: Form) => true,
	editForm: (_form: Form) => true,
	addForm: (_id: string) => true
});

const integrationsStore = useIntegrationsStore();

const shouldShowTable = computed(
	() =>
		integrationsStore?.activeIntegrations?.filter((integration) => integration.type === TABS_KEYS.OVERVIEW_TAB_FORMS)
			?.length > 1 || integrationsStore.hasAnyForms(TABS_KEYS.OVERVIEW_TAB_FORMS)
);

const isBannerVisible = computed(() => !integrationsStore.isLoading && !shouldShowTable.value);
const isTableVisible = computed(() => integrationsStore.isLoading || shouldShowTable.value);
</script>

<template>
	<div class="integrations">
		<Banner
			v-if="isBannerVisible"
			:title="translate(`hostinger_reach_forms_banner_title`)"
			:description="translate(`hostinger_reach_forms_banner_description`)"
			:button-text="translate(`hostinger_reach_forms_banner_button_text`)"
			:on-button-click="props.onBannerButtonClick"
			:is-button-loading="integrationsStore.isLoading"
		>
			<template #extra>
				<RecommendedPlugins />
			</template>
		</Banner>

		<PluginEntriesTable
			v-if="isTableVisible"
			:integrations="integrationsStore.fromType(TABS_KEYS.OVERVIEW_TAB_FORMS) as Integration[]"
			:is-loading="integrationsStore.isLoading"
			@go-to-plugin="emit('goToPlugin', $event)"
			@disconnect-plugin="emit('disconnectPlugin', $event)"
			@toggle-form-status="(form, status) => emit('toggleFormStatus', form, status)"
			@view-form="emit('viewForm', $event)"
			@edit-form="emit('editForm', $event)"
			@add-form="emit('addForm', $event)"
		/>
	</div>
</template>

<style scoped lang="scss">
.integrations {
	display: flex;
	flex-direction: column;
	align-self: stretch;
	gap: 20px;
	margin-bottom: 40px;
}
</style>
