<script lang="ts" setup>
import { reactive } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import { useModal } from '@/composables';
import { useIntegrationsStore } from '@/stores';
import type { ImportSummary, Integration } from '@/types';
import { translate } from '@/utils/translate';

interface Props {
	integration: Integration;
}

const { closeModal } = useModal();
const integrationsStore = useIntegrationsStore();
const props = defineProps<Props>();

const dismiss = () => {
	const backButtonAction = props.data?.backButtonRedirectAction;
	if (backButtonAction) {
		backButtonAction();
	} else {
		closeModal();
	}
};

const getFormsSummary = (): ImportSummary | [] => props?.integration?.importStatus?.summary ?? [];

const handleSync = async (): Promise<void> => {
	if (!props?.integration?.id) {
		return;
	}

	const selected = reactive<Record<string, Set<string>>>({});
	selected[props.integration.id] = new Set(Object.keys(getFormsSummary()));
	await integrationsStore.syncContacts(selected);
	closeModal();
};
</script>

<template>
	<BaseModal title-alignment="left" :title="translate('hostinger_reach_confirm_sync_modal_title')">
		<div class="confirm-disconnect-modal">
			<div class="confirm-disconnect-modal__content">
				<HText variant="body-2" as="p" class="confirm-disconnect-modal__text">
					{{ translate('hostinger_reach_confirm_sync_modal_text') }}
				</HText>
			</div>

			<div class="confirm-disconnect-modal__footer">
				<HButton
					variant="text"
					color="neutral"
					size="small"
					:is-disabled="integrationsStore.isLoading"
					@click="dismiss"
				>
					{{ translate('hostinger_reach_confirm_sync_modal_cancel') }}
				</HButton>
				<HButton
					color="primary"
					size="small"
					:is-disabled="integrationsStore.isLoading"
					:is-loading="integrationsStore.isLoading"
					@click="handleSync"
				>
					{{ translate('hostinger_reach_confirm_sync_modal_confirm') }}
				</HButton>
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
.confirm-disconnect-modal {
	margin-top: 24px;

	&__icon {
		margin-right: 12px;
	}

	&__content {
		display: flex;
		flex-direction: column;
		gap: 20px;
		align-items: center;
		margin-bottom: 24px;
	}

	&__footer {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
	}

	@media (max-width: 640px) {
		&__footer {
			flex-direction: column-reverse;
			gap: 12px;

			:deep(.h-button) {
				width: 100%;
			}
		}
	}
}
</style>
