<script lang="ts" setup>
import { HButton, HCheckbox, HIcon, HText } from '@hostinger/hcomponents';
import { computed, onMounted, reactive } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import { useModal } from '@/composables';
import { useIntegrationsStore } from '@/stores';
import type { ImportSummary, Integration } from '@/types';
import { translate } from '@/utils/translate';

const { closeModal } = useModal();
const integrationsStore = useIntegrationsStore();
const { syncContacts } = integrationsStore;

interface Props {
	title: string;
	subtitle?: string;
	data: { integrations: Integration[]; backButtonRedirectAction: () => void };
}

const props = defineProps<Props>();

const selected = reactive<Record<string, Set<string>>>({});

const init = (): void => {
	props.data.integrations.forEach((integration) => {
		toggleAll(integration, true);
	});
};

const totals = computed((): number => {
	let total = 0;

	props.data.integrations.forEach((integration) => {
		const forms = getForms(integration);
		const selectedFormIds = selected[integration.id] || new Set();

		selectedFormIds.forEach((formId) => {
			if (forms[formId]) {
				total += forms[formId]?.contacts ?? 0;
			}
		});
	});

	return total;
});

const toggleAll = (integration: Integration, checked: boolean): void => {
	if (checked) {
		selected[integration.id] = new Set(Object.keys(getForms(integration)));
	} else {
		selected[integration.id] = new Set();
	}
};

const toggleCheck = (integrationId: string, formId: string, checked: boolean): void => {
	if (checked) {
		selected[integrationId].add(formId);
	} else {
		selected[integrationId].delete(formId);
	}
};

const isChecked = (integrationId: string, formId: string): boolean => selected[integrationId]?.has(formId) ?? false;

const getForms = (integration: Integration): ImportSummary | [] => integration.importStatus?.summary ?? [];

const handleBackClick = (): void => {
	props.data.backButtonRedirectAction();
};

const handleSync = async (): Promise<void> => {
	await syncContacts(selected);
	closeModal();
};

onMounted(() => {
	init();
});
</script>

<template>
	<BaseModal title-alignment="left" :title="title" :subtitle="subtitle">
		<template v-if="data?.backButtonRedirectAction" #back-button>
			<button
				:aria-label="translate('hostinger_reach_back')"
				class="modal__back-button"
				type="button"
				@click="handleBackClick"
			>
				<HIcon name="ic-chevron-left-16" color="neutral--600" />
				{{ translate('hostinger_reach_back') }}
			</button>
		</template>

		<div class="modal">
			<div class="modal__content">
				<div class="modal__integrations">
					<div v-for="integration in data.integrations" :key="integration.id" class="modal__integration">
						<div class="modal__integration-details">
							<img :src="integration.icon" :alt="integration.title" class="modal__integration-image" loading="lazy" />
							<HText class="modal__integration-title" variant="body-2-bold" as="span">
								{{ integration.title }}
							</HText>
						</div>
						<div class="modal__integration-forms">
							<div class="modal__forms-row modal__forms-heading">
								<div class="modal__forms-col">
									<HText variant="body-2-bold" as="span">
										{{ translate('hostinger_reach_forms_title') }}
									</HText>
								</div>
								<div class="modal__forms-col">
									<HText variant="body-2-bold" as="span">
										{{ translate('hostinger_reach_contacts_contacts_to_sync') }}
									</HText>
								</div>
							</div>
							<div v-for="(form, key, index) in getForms(integration)" :key="key" class="modal__forms-row">
								<div class="modal__forms-col">
									<HCheckbox
										:tabindex="index + 1"
										:checked="isChecked(integration.id, key)"
										:label="form.title"
										aria-label="Check form"
										color="primary"
										@change="toggleCheck(integration.id, key, !isChecked(integration.id, key))"
									/>
								</div>
								<div class="modal__forms-col">
									<HText variant="body-2" as="span">{{ form.contacts }}</HText>
								</div>
							</div>
						</div>
					</div>
				</div>
				<HSnackbar icon="ic-user-double-16" variant="info">
					<p v-if="totals > 0">
						<b>{{ totals }} {{ translate('hostinger_reach_contacts_contacts') }}</b>
						{{ translate('hostinger_reach_contacts_info') }}
					</p>
					<p v-else>{{ translate('hostinger_reach_contacts_none_selected') }}</p>
				</HSnackbar>
			</div>
			<div class="modal__footer">
				<HButton
					:is-loading="integrationsStore.isLoading"
					:is-disabled="totals === 0 || integrationsStore.isLoading"
					variant="contain"
					color="primary"
					size="small"
					@click="handleSync"
				>
					{{ translate('hostinger_reach_contacts_sync') }}
				</HButton>
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
:deep(.base-modal__title) {
	margin-top: 24px;
}

:deep(.base-modal__subtitle) {
	color: var(--neutral--300);
}

.modal {
	margin-top: 24px;

	&__back-button {
		width: 32px;
		height: 32px;
		border: none;
		background: transparent;
		cursor: pointer;
		transition: background-color 0.2s ease;

		&:hover {
			background-color: var(--neutral--100);
		}

		&:active {
			background-color: var(--neutral--200);
		}
	}

	&__integration {
		border: 1px solid var(--neutral--200);
		border-radius: var(--h-border-radius-lg);
		padding: 24px;
		margin-bottom: 24px;
	}

	&__integration-details {
		display: flex;
		align-items: center;
		justify-content: flex-start;
		gap: 10px;
		border-bottom: 1px solid var(--neutral--200);
		padding-bottom: 24px;
	}

	&__integration-image {
		width: 28px;
		height: 28px;
	}

	&__integration-forms {
		margin-top: 24px;
	}

	&__forms-row {
		display: flex;
		align-items: center;
		gap: 10px;
		margin-bottom: 12px;
	}

	&__forms-col {
		width: 50%;
	}

	&__content {
		margin-bottom: 24px;
	}

	&__footer {
		display: flex;
		justify-content: flex-end;
		gap: 8px;
	}

	.modal__forms-heading {
		:deep(.h-checkbox-label) {
			font-weight: bold;
		}
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
