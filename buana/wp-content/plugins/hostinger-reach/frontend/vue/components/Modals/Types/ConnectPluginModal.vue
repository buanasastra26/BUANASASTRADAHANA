<script lang="ts" setup>
import { HLabel } from '@hostinger/hcomponents';
import { computed } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import PluginIntegrationSkeleton from '@/components/skeletons/PluginIntegrationSkeleton.vue';
import PluginsSectionSkeleton from '@/components/skeletons/PluginsSectionSkeleton.vue';
import { useModal } from '@/composables/useModal';
import { useReachUrls } from '@/composables/useReachUrls';
import { getPluginInfo } from '@/data/pluginData';
import { useIntegrationsStore } from '@/stores/integrationsStore';
import { usePagesStore } from '@/stores/pagesStore';
import type { Integration } from '@/types';
import { ModalName } from '@/types/enums';
import { translate } from '@/utils/translate';

interface Props {
	data?: { backButtonRedirectAction?: () => void };
}

const props = defineProps<Props>();

const { openModal } = useModal();

const { reachContactsImportLink } = useReachUrls();
const integrationsStore = useIntegrationsStore();
const pagesStore = usePagesStore();

const handleBackClick = () => {
	props.data?.backButtonRedirectAction?.();
};

const installedIntegrations = computed(() =>
	integrationsStore.availableIntegrations.filter((integration) => integration.isPluginActive)
);

const availableIntegrations = computed(() =>
	integrationsStore.availableIntegrations.filter((integration) => !integration.isPluginActive)
);

const handleToggle = async (integrationId: string, connect: boolean) => {
	if (connect) {
		await handleConnect(integrationId);
	} else {
		handleDisconnect(integrationId);
	}
};

const handleConnect = async (integrationId: string) => {
	const integration = getIntegration(integrationId);
	if (!integration?.isInstallable) {
		window.open(integration.url, '_blank', 'noopener,noreferrer');

		return;
	}
	await integrationsStore.toggleIntegrationStatus(integrationId, true);
	maybeShowSyncPage(integrationId);
};

const maybeShowSyncPage = (integrationId: string) => {
	const integration = getIntegration(integrationId);
	if (!integration?.importEnabled || integration.forms?.length === 0 || integration.importStatus.total <= 0) {
		return;
	}

	openModal(
		ModalName.SYNC_CONTACTS_MODAL,
		{
			title: translate('hostinger_reach_contacts_modal_title'),
			data: {
				backButtonRedirectAction: () => {
					openModal(ModalName.CONNECT_PLUGIN_MODAL, {}, { hasCloseButton: true, isLG: true });
				},
				integrations: [getIntegration(integrationId)]
			}
		},
		{
			hasCloseButton: true
		}
	);
};

const handleDisconnect = (id: string) => {
	openModal(ModalName.CONFIRM_DISCONNECT_MODAL, {
		data: {
			integration: id,
			backButtonRedirectAction: () => {
				openModal(ModalName.CONNECT_PLUGIN_MODAL, {}, { hasCloseButton: true, isLG: true });
			}
		}
	});
};

const getIntegration = (integrationId: string): Integration | undefined =>
	integrationsStore.availableIntegrations.find((integration) => integration.id === integrationId);

pagesStore.loadData();
</script>

<template>
	<BaseModal title-alignment="left" :title="translate('hostinger_reach_connect_plugin')">
		<template v-if="data?.backButtonRedirectAction" #back-button>
			<button
				:aria-label="translate('hostinger_reach_back')"
				class="add-form-modal__back-button"
				type="button"
				@click="handleBackClick"
			>
				<HIcon name="ic-chevron-left-16" color="neutral--600" />
				{{ translate('hostinger_reach_back') }}
			</button>
		</template>

		<div class="add-form-modal">
			<PluginsSectionSkeleton v-if="integrationsStore.isLoading" />

			<template v-else>
				<div v-if="installedIntegrations.length" class="add-form-modal__installed-section">
					<HText variant="body-3" as="h3" color="neutral--300">
						{{ translate('hostinger_reach_forms_installed_plugins') }}
					</HText>
					<div class="add-form-modal__plugins-list">
						<div v-for="integration in installedIntegrations" :key="integration.id" class="add-form-modal__plugin-card">
							<PluginIntegrationSkeleton v-if="integrationsStore.loadingIntegrations[integration.id]" />

							<div v-else class="add-form-modal__plugin-content">
								<div class="add-form-modal__plugin-info">
									<div class="add-form-modal__plugin-icon">
										<img
											:src="getPluginInfo(integration).icon"
											:alt="integration.title"
											class="add-form-modal__plugin-image"
											loading="lazy"
										/>
									</div>
									<div class="add-form-modal__plugin-details">
										<HText variant="body-2-bold" as="span">
											{{ integration.title }}
										</HText>

										<HLabel v-if="integration.importEnabled" color="grayLight" variant="contain">
											{{ integration.importStatus?.total ?? 0 }}
											{{ translate('hostinger_reach_contacts') }}
										</HLabel>
									</div>
								</div>
								<div class="add-form-modal__plugin-actions">
									<HButton
										variant="outline"
										size="small"
										:color="integration.isActive ? 'danger' : 'neutral'"
										:is-disabled="
											(integration.id === 'elementor' && integration.isActive) ||
											integrationsStore.isIntegrationLoading(integration.id)
										"
										@click="handleToggle(integration.id, !integration.isActive)"
									>
										{{
											integration.isActive
												? translate('hostinger_reach_forms_disconnect')
												: translate('hostinger_reach_forms_connect')
										}}
									</HButton>
								</div>
							</div>
						</div>
					</div>
				</div>

				<div v-if="availableIntegrations.length" class="add-form-modal__plugins-section">
					<HText variant="body-3" as="h3" color="neutral--300">
						{{ translate('hostinger_reach_forms_supported_plugins') }}
					</HText>
					<div class="add-form-modal__plugins-list">
						<div v-for="integration in availableIntegrations" :key="integration.id" class="add-form-modal__plugin-card">
							<PluginIntegrationSkeleton v-if="integrationsStore.isIntegrationLoading(integration.id)" />

							<div v-else class="add-form-modal__plugin-content">
								<div class="add-form-modal__plugin-info">
									<div class="add-form-modal__plugin-icon">
										<img
											:src="getPluginInfo(integration).icon"
											:alt="integration.title"
											class="add-form-modal__plugin-image"
											loading="lazy"
										/>
									</div>
									<div class="add-form-modal__plugin-details">
										<HText variant="body-2-bold" as="span">
											{{ integration.title }}
										</HText>
									</div>
								</div>
								<div class="add-form-modal__plugin-actions">
									<HButton
										variant="outline"
										color="neutral"
										size="small"
										:is-disabled="integrationsStore.isIntegrationLoading(integration.id)"
										@click="handleConnect(integration.id)"
									>
										{{
											integration.isInstallable
												? translate('hostinger_reach_forms_install_and_connect')
												: translate('hostinger_reach_forms_install')
										}}
									</HButton>
								</div>
							</div>
						</div>
					</div>
					<HSnackbar variant="info" hide-icon class="more-plugins-snackbar">
						<div class="more-plugins-snackbar__content">
							<div class="more-plugins-snackbar__text">
								<p>
									<b>{{ translate('hostinger_reach_add_form_snackbar_title') }}</b>
								</p>
								<p>
									{{ translate('hostinger_reach_add_form_snackbar_text') }}
								</p>
							</div>
							<HButton
								class="more-plugins-snackbar__link"
								variant="outline"
								target="_blank"
								color="neutral"
								size="small"
								icon-append="ic-launch-16"
								:to="reachContactsImportLink"
							>
								{{ translate('hostinger_reach_add_form_snackbar_link') }}
							</HButton>
						</div>
					</HSnackbar>
				</div>
			</template>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
.more-plugins-snackbar {
	&__content {
		display: flex;
		align-items: center;
		justify-content: space-between;
	}

	&__text {
		max-width: 60%;

		p {
			font-size: 14px;
		}
	}

	&__link {
		flex-flow: nowrap;
		display: flex;
	}
}

.add-form-modal {
	display: flex;
	flex-direction: column;
	gap: 20px;
	margin-top: 24px;

	&__back-button {
		display: inline-flex;
		align-items: center;
		gap: 4px;
		padding: 4px 8px 4px 4px;
		border: none;
		background: transparent;
		cursor: pointer;
		border-radius: 8px;
		color: var(--neutral--600);
		font-size: 14px;
		transition: background-color 0.2s ease;

		&:hover {
			background-color: var(--neutral--100);
		}

		&:active {
			background-color: var(--neutral--200);
		}
	}

	&__main-card {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 20px;
		background: var(--neutral--0);
		border: 1px solid var(--primary--500);
		border-radius: 12px;
		transition:
			border-color 0.2s ease,
			box-shadow 0.2s ease;

		&:hover {
			border-color: var(--primary--600);
			box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
		}
	}

	&__main-content {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	&__main-icon {
		width: 32px;
		height: 32px;
		flex-shrink: 0;
		background: var(--primary--50);
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	&__plugins-section {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	&__plugins-toggle {
		align-self: center;
	}

	&__plugins-list {
		display: flex;
		flex-direction: column;
		gap: 12px;
	}

	&__plugin-card {
		display: flex;
		flex-direction: column;
		background: var(--neutral--0);
		border: 1px solid var(--neutral--200);
		border-radius: 12px;
		transition: border-color 0.2s ease;
		contain: layout style;

		&:hover {
			border-color: var(--neutral--300);
		}
	}

	&__plugin-content {
		display: flex;
		align-items: center;
		justify-content: space-between;
		padding: 16px;
		width: 100%;
	}

	&__plugin-info {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	&__plugin-icon {
		width: 40px;
		height: 40px;
		border-radius: 8px;
		display: flex;
		align-items: center;
		justify-content: center;
		background: var(--neutral--50);
		flex-shrink: 0;
	}

	&__plugin-image {
		width: 32px;
		height: 32px;
		object-fit: contain;
	}

	&__plugin-details {
		display: flex;
		gap: 8px;
		align-items: center;
	}

	&__plugin-actions {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	&__installed-section {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	@media (max-width: 640px) {
		gap: 16px;

		&__main-card {
			padding: 16px;
			flex-direction: column;
			align-items: stretch;
			gap: 16px;
		}

		&__main-content {
			justify-content: center;
		}

		&__plugin-content {
			padding: 14px;
		}

		&__plugin-icon {
			width: 36px;
			height: 36px;
		}

		&__plugin-image {
			width: 28px;
			height: 28px;
		}
	}

	@media (max-width: 480px) {
		gap: 12px;

		&__main-card {
			padding: 14px;
		}

		&__plugins-list {
			gap: 8px;
		}

		&__plugin-content {
			padding: 12px;
		}
	}
}
</style>
