import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

import { useToast } from '@/composables/useToast';
import { HOSTINGER_REACH_ID } from '@/data/pluginData';
import { formsRepo } from '@/data/repositories/formsRepo';
import { TABS_KEYS } from '@/data/tabs';
import { STORE_PERSISTENT_KEYS } from '@/types/enums';
import type { Form, Integration } from '@/types/models';
import { IMPORT_STATUSES } from '@/types/models';
import { translate } from '@/utils/translate';

export const useIntegrationsStore = defineStore(
	'integrationsStore',
	() => {
		const { showSuccess, showError } = useToast();

		const integrations = ref<Integration[]>([]);
		const isLoading = ref(false);
		const error = ref<string | null>(null);
		const loadingIntegrations = ref<Record<string, boolean>>({});
		const importStatusPollers = ref<Record<string, number>>({});
		const activeIntegrations = computed(() => integrations.value.filter((integration) => integration.isActive));
		const syncableIntegrations = computed(() =>
			activeIntegrations.value.filter((integration) => integration.importEnabled && integration.forms.length > 0)
		);
		const importAvailableIntegrations = computed(() =>
			syncableIntegrations.value.filter((integration) => integration.importStatus.total > 0)
		);

		const availableIntegrations = computed(() => integrations.value.filter(({ id }) => id !== HOSTINGER_REACH_ID));

		const recommendedPlugins = computed<Integration[]>(() =>
			availableIntegrations.value
				.filter(
					(integration) =>
						integration.type === TABS_KEYS.OVERVIEW_TAB_FORMS &&
						integration.isActive === false &&
						integration.isInstallable === true
				)
				.slice()
				.sort((a, b) => {
					const scoreA = a.isPluginActive ? 1 : 0;
					const scoreB = b.isPluginActive ? 1 : 0;

					if (scoreA !== scoreB) return scoreB - scoreA;

					const scoreAContacts = a.importStatus?.total || 0;
					const scoreBContacts = b.importStatus?.total || 0;

					return scoreBContacts - scoreAContacts;
				})
				.slice(0, 3)
		);

		const hasAnyForms = (type: string = 'forms') =>
			integrations.value.some(
				(integration) => integration.type === type && integration.forms && integration.forms.length > 0
			);

		const fromType = (type: string = 'forms'): Integration[] =>
			integrations.value.filter((integration) => integration.type === type);

		const isIntegrationLoading = (integrationId: string) => loadingIntegrations.value[integrationId] || false;

		const mapAndFilterForms = (formsData: Form[], integration: Integration) =>
			formsData
				?.filter((form) => integration.id === form.type)
				.map((form) => ({
					...form,
					integration
				})) || [];

		const loadIntegrations = async () => {
			isLoading.value = true;
			error.value = null;

			const [integrationsData, integrationsError] = await formsRepo.getIntegrations();
			const [formsData] = await formsRepo.getForms();

			if (integrationsError) {
				error.value = integrationsError.message || 'Failed to load integrations';
				isLoading.value = false;

				return;
			}

			if (integrationsData) {
				integrations.value = Object.values(integrationsData).map((integration) => ({
					...integration,
					forms: mapAndFilterForms(formsData || [], integration)
				}));
			}

			isLoading.value = false;
		};

		const isImportProcessFinished = (status?: string) =>
			status === IMPORT_STATUSES.IMPORTED ||
			status === IMPORT_STATUSES.NOT_IMPORTED ||
			status === IMPORT_STATUSES.PARTIALLY_IMPORTED ||
			status === IMPORT_STATUSES.OFF;

		const stopImportStatusPolling = (integrationId: string) => {
			const timerId = importStatusPollers.value[integrationId];
			if (!timerId) {
				return;
			}

			window.clearTimeout(timerId);
			delete importStatusPollers.value[integrationId];
		};

		const refreshImportStatus = async (integrationId: string) => {
			const integration = integrations.value.find((i) => i.id === integrationId);
			if (!integration) {
				stopImportStatusPolling(integrationId);

				return;
			}

			try {
				const [statusData, statusError] = await formsRepo.getImportStatus(integrationId);
				if (statusError) {
					stopImportStatusPolling(integrationId);

					return;
				}

				if (statusData) {
					integration.importStatus = statusData as Integration['importStatus'];
					if (isImportProcessFinished(integration.importStatus?.status)) {
						stopImportStatusPolling(integrationId);
					}
				}
			} catch {
				stopImportStatusPolling(integrationId);
			}
		};

		const startImportStatusPolling = (integrationId: string) => {
			if (importStatusPollers.value[integrationId]) {
				return;
			}

			const tick = async () => {
				await refreshImportStatus(integrationId);

				if (!importStatusPollers.value[integrationId]) {
					return;
				}

				importStatusPollers.value[integrationId] = window.setTimeout(tick, 2000);
			};

			importStatusPollers.value[integrationId] = window.setTimeout(tick, 0);
		};

		const syncContacts = async (importRequest: Record<string, Set<string>>) => {
			isLoading.value = true;

			try {
				await formsRepo.sync(importRequest);
				showSuccess(translate('hostinger_reach_contacts_import_success'));
			} catch (error) {
				showError(error.message || translate('hostinger_reach_contacts_import_error'));
			} finally {
				const integrationIds = Object.keys(importRequest);
				integrationIds.forEach((id) => stopImportStatusPolling(id));
				await loadIntegrations();
				integrationIds.forEach((id) => startImportStatusPolling(id));
				isLoading.value = false;
			}
		};

		const toggleIntegrationStatus = async (integrationId: string, isActive: boolean) => {
			isLoading.value = true;
			loadingIntegrations.value[integrationId] = true;

			const [, error] = await formsRepo.toggleIntegrationStatus(integrationId, isActive);
			isLoading.value = false;

			if (error) {
				loadingIntegrations.value[integrationId] = false;

				return false;
			}

			const integration = integrations.value.find((i) => i.id === integrationId);

			if (integration) {
				integration.isActive = isActive;
			}

			await loadIntegrations();
			loadingIntegrations.value[integrationId] = false;

			showSuccess(
				translate(
					isActive
						? 'hostinger_reach_forms_plugin_connected_success'
						: 'hostinger_reach_forms_plugin_disconnected_success'
				)
			);

			return true;
		};

		return {
			integrations,
			isLoading,
			error,
			loadingIntegrations,
			activeIntegrations,
			syncableIntegrations,
			importAvailableIntegrations,
			availableIntegrations,
			recommendedPlugins,
			hasAnyForms,
			isIntegrationLoading,
			loadIntegrations,
			toggleIntegrationStatus,
			syncContacts,
			fromType
		};
	},
	{
		persist: { key: STORE_PERSISTENT_KEYS.INTEGRATIONS_STORE }
	}
);
