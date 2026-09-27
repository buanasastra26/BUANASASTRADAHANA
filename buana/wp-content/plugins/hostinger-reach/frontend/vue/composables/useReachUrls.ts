import { computed } from 'vue';

import { useGeneralDataStore } from '@/stores/generalDataStore';

export const useReachUrls = () => {
	const generalStore = useGeneralDataStore();

	const reachBaseDomain = computed(() => (generalStore.isStaging ? 'reach.hostinger.dev' : 'reach.hostinger.com'));
	const hpanelBaseDomain = computed(() => (generalStore.isStaging ? 'hpanel.hostinger.dev' : 'hpanel.hostinger.com'));
	const resourceId = computed(() => (generalStore.hasValidResourceId ? generalStore.resourceId : null));

	return {
		reachUpgradeLink: computed(() =>
			resourceId.value
				? `https://${hpanelBaseDomain.value}/reach?resourceId=${resourceId.value}&domain=${generalStore.domain}`
				: `https://${hpanelBaseDomain.value}/reach`
		),
		reachYourPlanLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=settings-your-plan`
				: `https://${reachBaseDomain.value}?routeTo=settings-your-plan`
		),
		reachCampaignsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=campaigns`
				: `https://${reachBaseDomain.value}?routeTo=campaigns`
		),
		reachTemplatesLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=templates`
				: `https://${reachBaseDomain.value}?routeTo=templates`
		),
		reachSettingsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=settings`
				: `https://${reachBaseDomain.value}?routeTo=settings`
		),
		reachDashboardLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}`
				: `https://${reachBaseDomain.value}`
		),
		reachContactsImportLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=contacts-import`
				: `https://${reachBaseDomain.value}?routeTo=contacts-import`
		),
		reachContactsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=contacts`
				: `https://${reachBaseDomain.value}?routeTo=contacts`
		),
		reachSegmentsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=segments`
				: `https://${reachBaseDomain.value}?routeTo=segments`
		),
		reachAutomationsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=automation`
				: `https://${reachBaseDomain.value}?routeTo=automation`
		),
		reachTokensLink: computed(() => `https://${reachBaseDomain.value}?routeTo=integrations-api-tokens`),
		reachFormsLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=forms`
				: `https://${reachBaseDomain.value}?routeTo=forms`
		),
		reachFormsCreateLink: computed(() =>
			resourceId.value
				? `https://${reachBaseDomain.value}?resourceId=${resourceId.value}&domain=${generalStore.domain}&routeTo=forms&action=create-form`
				: `https://${reachBaseDomain.value}?routeTo=forms&action=create-form`
		),
		reachBaseDomain,
		hpanelBaseDomain
	};
};
