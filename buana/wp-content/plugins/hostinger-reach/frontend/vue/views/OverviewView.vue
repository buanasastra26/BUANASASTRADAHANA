<script setup lang="ts">
import { HIcon, HPopover } from '@hostinger/hcomponents';
import { computed, onMounted, watchEffect } from 'vue';

import reachOverviewBannerBackground from '@/assets/images/backgrounds/reach-features-banner.png';
import reachLogo from '@/assets/images/icons/reach-logo.svg';
import ActionButtonsSection from '@/components/ActionButtonsSection.vue';
import Banner from '@/components/Banner.vue';
import FAQ from '@/components/FAQ.vue';
import Integrations from '@/components/Integrations.vue';
import WooCommerce from '@/components/WooCommerce.vue';
import { useModal } from '@/composables';
import { useOverviewData } from '@/composables/useOverviewData';
import { useReachUrls } from '@/composables/useReachUrls';
import { useToast } from '@/composables/useToast';
import { overviewFaqData } from '@/data/faq';
import { ELEMENTOR_ID, HOSTINGER_REACH_ID, WOOCOMMERCE_ID } from '@/data/pluginData';
import { formsRepo } from '@/data/repositories/formsRepo';
import { reachRepo } from '@/data/repositories/reachRepo';
import { TABS_KEYS } from '@/data/tabs';
import { useBuilderFormsStore } from '@/stores/builderFormsStore';
import { useIntegrationsStore } from '@/stores/integrationsStore';
import type { Integration } from '@/types';
import { ModalName } from '@/types';
import type { Form } from '@/types/models';
import { translate } from '@/utils/translate';

const { status, loadOverviewData } = useOverviewData();
const {
	reachDashboardLink,
	reachYourPlanLink,
	reachContactsLink,
	reachSegmentsLink,
	reachAutomationsLink,
	reachFormsLink
} = useReachUrls();
const { showError } = useToast();

const { openModal } = useModal();
const integrationsStore = useIntegrationsStore();
const builderFormsStore = useBuilderFormsStore();

const actionButtons = computed(() => [
	{
		icon: 'ic-user-double-16',
		text: translate('hostinger_reach_overview_contacts_button'),
		url: reachContactsLink.value
	},
	{
		icon: 'ic-user-ai-16',
		text: translate('hostinger_reach_overview_segments_button'),
		url: reachSegmentsLink.value
	},
	{
		icon: 'ic-lightning-16',
		text: translate('hostinger_reach_overview_your_plan_button'),
		url: reachYourPlanLink.value
	},
	{
		icon: 'ic-reach-16',
		text: translate('hostinger_reach_header_go_to_reach_button'),
		url: reachDashboardLink.value
	}
]);

const handlePluginGoTo = (id: string) => {
	const integration = integrationsStore.integrations.find((i) => i.id === id);
	if (!integration?.adminUrl) {
		return;
	}

	window.open(integration.adminUrl, '_blank');
};

const handlePluginDisconnect = (id: string) => {
	openModal(ModalName.CONFIRM_DISCONNECT_MODAL, {
		data: { integration: id }
	});
};

const handleFormToggleStatus = async (form: Form, isActive: boolean) => {
	if (form.isLoading) {
		return;
	}

	const integration = integrationsStore.integrations.find((i) => i.forms?.some((f) => f.formId === form.formId));
	if (!integration || !integration.forms) {
		return;
	}

	const formIndex = integration.forms.findIndex((f) => f.formId === form.formId);
	if (formIndex !== -1) {
		integration.forms[formIndex].isLoading = true;
	}

	const [, error] = await formsRepo.toggleFormStatus(form.formId, isActive, form.type);

	if (formIndex !== -1) {
		integration.forms[formIndex].isLoading = false;
	}

	if (error?.response?.data?.error) {
		showError(error?.response?.data?.error);

		return;
	}

	if (error) {
		showError(translate('hostinger_reach_error_message'));

		return;
	}

	if (formIndex !== -1) {
		integration.forms[formIndex] = {
			...integration.forms[formIndex],
			isActive
		};

		if (isActive && !isAutomation(form) && hasContactsToImport(integration)) {
			openModal(ModalName.CONFIRM_SYNC_MODAL, { integration });
		}
	}
};

const handleViewForm = (form: Form) => {
	if (form.formId === 'ai-theme-footer-form') {
		window.open(hostinger_reach_reach_data.site_url, '_blank');
	}

	if (form.post?.guid) {
		window.open(form.post.guid, '_blank');
	}
};

const FORM_BUILDER_INTEGRATION_IDS = [ELEMENTOR_ID, HOSTINGER_REACH_ID];

const handleAddForm = (id: string) => {
	const integration = integrationsStore.integrations.find((i) => i.id === id);
	if (!integration?.addFormUrl) {
		return;
	}

	if (!FORM_BUILDER_INTEGRATION_IDS.includes(integration.id)) {
		window.open(integration.addFormUrl, '_blank');

		return;
	}

	openModal(
		ModalName.SELECT_FORM_MODAL,
		{
			data: {
				onContinue: (formBuilderId: string) => addFormToIntegrationPage(integration, formBuilderId)
			}
		},
		{ hasCloseButton: true, isXXL: true, noContentPadding: true }
	);
};

const addFormToIntegrationPage = (integration: Integration, formBuilderId: string) => {
	if (!integration.addFormUrl) {
		return;
	}

	const addBlockValue = formBuilderId || '1';
	let url = integration.addFormUrl.replace(
		'hostinger_reach_add_block=1',
		`hostinger_reach_add_block=${encodeURIComponent(addBlockValue)}`
	);

	if (integration.id !== ELEMENTOR_ID) {
		const addBlockNonce = hostinger_reach_reach_data?.add_block_nonce || '';
		url += `&_wpnonce=${encodeURIComponent(addBlockNonce)}`;
	}

	window.open(url, '_blank');
};

const showAddFormModal = () => {
	openModal(ModalName.SELECT_FORM_MODAL, {}, { hasCloseButton: true, isXXL: true, noContentPadding: true });
};

const handleConnectPluginButton = () => {
	openModal(ModalName.CONNECT_PLUGIN_MODAL, {}, { hasCloseButton: true, isLG: true });
};

const hasContactsToImport = (integration: Integration): boolean =>
	integration.importEnabled && integration.importStatus?.total > 0;

const handleSyncContactsButton = () => {
	if (!integrationsStore.importAvailableIntegrations.length) {
		return;
	}

	openModal(
		ModalName.SYNC_CONTACTS_MODAL,
		{
			title: translate('hostinger_reach_contacts_modal_title'),
			subtitle: translate('hostinger_reach_contacts_modal_subtitle'),
			data: { integrations: integrationsStore.importAvailableIntegrations ?? [] }
		},
		{ hasCloseButton: true }
	);
};

const handleEditForm = (form: Form) => {
	const integration = integrationsStore.integrations.find((i) => i.forms?.some((f) => f.formId === form.formId));

	if (!integration?.editUrl) {
		return;
	}

	let editUrl = integration.editUrl;

	const placeholders: Record<string, string> = {
		'{post_id}': form.post?.ID.toString() ?? '',
		'{form_id}': form.formId,
		'{post_name}': form.post?.postName.toString() ?? ''
	};

	for (const [token, value] of Object.entries(placeholders)) {
		if (editUrl.includes(token)) {
			editUrl = editUrl.replaceAll(token, value);
		}
	}

	if (form.formId === 'ai-theme-footer-form') {
		editUrl = 'site-editor.php?p=%2Fwp_template_part%2Fhostinger-ai-theme%2F%2Ffooter&canvas=edit';
	}

	if (!editUrl.startsWith('http') && !editUrl.startsWith('/')) {
		editUrl = `/wp-admin/${editUrl}`;
	}

	window.open(editUrl, '_blank');
};

const hasFormsOrActiveIntegrations = computed(
	() =>
		integrationsStore?.activeIntegrations?.filter((integration) => integration.type === 'forms')?.length > 1 ||
		integrationsStore?.hasAnyForms('forms')
);

const maybeOpenAddFormModal = () => {
	const url = new URL(window.location.href);
	if (url.searchParams.has('addForm')) {
		showAddFormModal();
	}
};

const maybeShowConnectionSuccessModal = async () => {
	const [data] = await reachRepo.getConnectionSuccess();

	if (data?.success) {
		openModal(ModalName.CONNECTION_SUCCESS_MODAL, {}, { hasCloseButton: true, isXL: true, noContentPadding: true });
	}
};

onMounted(() => {
	loadOverviewData();
	integrationsStore.loadIntegrations();
	builderFormsStore.loadForms();
	maybeOpenAddFormModal();
	maybeShowConnectionSuccessModal();
});

watchEffect(() => {
	if (status?.value === 403) {
		window.location.reload();
	}
});

const wooCommerceConnected = computed(
	() => !!integrationsStore?.activeIntegrations.find((i) => i.id === WOOCOMMERCE_ID)
);

const isAutomation = (form: Form): boolean => form?.formId?.includes('.') ?? false;

const shouldShowConnect = computed(
	() =>
		integrationsStore?.activeIntegrations?.filter((integration) => integration.type === TABS_KEYS.OVERVIEW_TAB_FORMS)
			?.length > 1 || integrationsStore.hasAnyForms(TABS_KEYS.OVERVIEW_TAB_FORMS)
);
</script>

<template>
	<div class="overview">
		<header class="overview__header">
			<div class="overview__header-content">
				<div class="overview__header-brand">
					<img :src="reachLogo" :alt="translate('hostinger_reach_header_logo_alt')" class="overview__header-logo" />
				</div>
			</div>
		</header>

		<div class="overview__content">
			<div class="overview__section">
				<div class="overview__section-content">
					<ActionButtonsSection :buttons="actionButtons" />
				</div>
			</div>

			<Banner
				:title="translate('hostinger_reach_overview_banner_title')"
				:description="translate('hostinger_reach_overview_banner_description')"
				:label="translate('hostinger_reach_overview_banner_label')"
				:button-text="translate('hostinger_reach_overview_banner_button_text')"
				:button-to="reachFormsLink"
				button-target="_blank"
				align="left"
				:background-image="reachOverviewBannerBackground as unknown as string"
			/>

			<div class="overview__integrations">
				<div class="overview__integrations-header">
					<HText class="overview__tabs-header" as="h2" variant="heading-2">
						{{ translate('hostinger_reach_forms_title') }}
					</HText>
					<div class="overview__integrations-tabs">
						<div class="overview__integrations-buttons">
							<HButton
								v-if="integrationsStore.importAvailableIntegrations.length > 0"
								variant="text"
								color="primary"
								size="small"
								icon-prepend="ic-arrows-circle-16"
								:is-loading="integrationsStore.isLoading"
								@click="handleSyncContactsButton"
							>
								{{ translate('hostinger_reach_sync_contacts_button_text') }}
							</HButton>
							<HButton
								v-if="shouldShowConnect"
								variant="outline"
								color="primary"
								size="small"
								icon-prepend="ic-link-16"
								:is-loading="integrationsStore.isLoading"
								@click="handleConnectPluginButton"
							>
								{{ translate('hostinger_reach_connect_plugin') }}
							</HButton>
							<HButton
								v-if="hasFormsOrActiveIntegrations"
								variant="outline"
								color="primary"
								size="small"
								icon-prepend="ic-plus-16"
								:is-loading="integrationsStore.isLoading"
								@click="showAddFormModal"
							>
								{{ translate('hostinger_reach_add_form') }}
							</HButton>
						</div>
					</div>
				</div>

				<Integrations
					:type="TABS_KEYS.OVERVIEW_TAB_FORMS"
					:on-banner-button-click="showAddFormModal"
					@go-to-plugin="handlePluginGoTo"
					@disconnect-plugin="handlePluginDisconnect"
					@toggle-form-status="handleFormToggleStatus"
					@view-form="handleViewForm"
					@edit-form="handleEditForm"
					@add-form="handleAddForm"
				/>
				<div class="overview__integrations-header">
					<HText class="overview__tabs-header" as="h2" variant="heading-2">WooCommerce</HText>
					<div class="overview__integrations-tabs">
						<div v-if="wooCommerceConnected" class="overview__integrations-buttons">
							<HButton
								variant="text"
								color="primary"
								size="small"
								icon-append="ic-arrow-up-right-square-16"
								target="_blank"
								:to="reachAutomationsLink"
							>
								{{ translate('hostinger_reach_woocommerce_manage_automations') }}
							</HButton>
							<HPopover
								placement="bottom-end"
								:show-arrow="false"
								background-color="neutral--0"
								border-radius="12px"
								:outside-click-enabled="true"
								:close-other-popovers-on-open="true"
							>
								<template #trigger>
									<HButton variant="outline" color="primary" size="small" icon-prepend="ic-gear-16">
										{{ translate('hostinger_reach_woocommerce_manage_plugin') }}
									</HButton>
								</template>
								<div class="overview__popover-menu">
									<div class="overview__popover-menu-item" @click="handlePluginGoTo(WOOCOMMERCE_ID)">
										<HIcon name="ic-blocks-plus-16" />
										<span>{{ translate('hostinger_reach_plugin_entries_table_go_to_plugin') }}</span>
										<HIcon name="ic-arrow-up-right-square-16" />
									</div>
									<div class="overview__popover-menu-item" @click="handlePluginDisconnect(WOOCOMMERCE_ID)">
										<HIcon name="ic-cross-circle-16" />
										<span>{{ translate('hostinger_reach_plugin_entries_table_disconnect_plugin') }}</span>
									</div>
								</div>
							</HPopover>
						</div>
					</div>
				</div>
				<WooCommerce
					@go-to-plugin="handlePluginGoTo"
					@disconnect-plugin="handlePluginDisconnect"
					@toggle-form-status="handleFormToggleStatus"
				/>

				<FAQ :faq-data="overviewFaqData" />
			</div>
		</div>
	</div>
</template>

<style scoped lang="scss">
.overview {
	min-height: 100vh;
	background-color: var(--neutral--50);

	&__header {
		width: 100%;
		padding: 40px 0 20px 0;
		@media (max-width: 768px) {
			padding: 16px 12px;
		}

		@media (max-width: 480px) {
			padding: 12px 8px;
		}
	}

	&__header-content {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		width: 860px;
		margin: 0 auto;

		@media (max-width: 1023px) {
			width: 100%;
		}
	}

	&__header-brand {
		display: flex;
		align-items: center;
		gap: 12px;

		@media (max-width: 480px) {
			gap: 8px;
		}
	}

	&__header-logo {
		height: 28px;
		width: auto;

		@media (max-width: 768px) {
			height: 24px;
		}

		@media (max-width: 480px) {
			height: 20px;
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		gap: 32px;
		padding: 20px 0;
		width: 860px;
		margin: 0 auto;
	}

	&__integrations {
		width: 100%;
	}

	&__integrations-header {
		width: 100%;
		margin-bottom: 16px;
		display: flex;
		flex-direction: row;
		justify-content: space-between;
		align-items: center;
		gap: 10px;
		flex-wrap: wrap;

		@media (max-width: 992px) {
			justify-content: start;
		}
	}

	&__integrations-tabs {
		display: flex;
		justify-content: flex-end;
		align-items: center;
		width: 100%;

		@media (max-width: 992px) {
			flex-wrap: wrap;
			justify-content: start;
		}
	}

	&__integrations-buttons {
		display: flex;
		gap: 8px;

		@media (max-width: 992px) {
			flex-wrap: wrap;
			width: 100%;

			> div {
				width: 100%;
			}
		}
	}

	&__section {
		display: flex;
		flex-direction: column;
		align-self: stretch;
		gap: 20px;
	}

	&__title {
		display: flex;
		justify-content: space-between;
		align-items: center;
		align-self: stretch;
	}

	&__title-buttons {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__popover-menu {
		padding: 4px;
		min-width: 180px;
	}

	&__popover-menu-item {
		display: flex;
		align-items: center;
		gap: 8px;
		padding: 12px;
		cursor: pointer;
		border-radius: 8px;
		font-weight: 500;
		font-size: 14px;
		color: var(--neutral--600);
		transition: background-color 0.2s ease;

		&:hover {
			background-color: var(--neutral--50);
		}

		span {
			flex: 1;
		}
	}

	&__your-plan-button {
		margin-right: 0;
	}

	&__upgrade-button {
		background: var(--neutral--0);
		border: 1px solid transparent;
		background-image:
			linear-gradient(var(--neutral--0), var(--neutral--0)),
			linear-gradient(135deg, var(--primary--200) 0%, var(--primary--400) 47.45%, var(--primary--600) 100%);
		background-origin: border-box;
		background-clip: padding-box, border-box;
		color: var(--neutral--600);
	}

	&__section-content {
		display: flex;
		flex-direction: column;
		align-self: stretch;
		gap: 16px;
	}

	&__tabs-header {
		width: 100%;
	}
}

@media (max-width: 1023px) {
	.overview {
		&__content {
			width: 100%;
			padding: 24px 16px;
		}

		&__title {
			flex-direction: column;
			align-items: flex-start;
			gap: 12px;
		}

		&__title-buttons {
			align-self: stretch;
			justify-content: flex-end;
		}

		&__integrations_tabs {
			flex-direction: column;
		}
	}
}
</style>
