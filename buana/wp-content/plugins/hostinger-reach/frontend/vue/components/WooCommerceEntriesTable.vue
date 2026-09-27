<script setup lang="ts">
import { HHyperlink, HLabel } from '@hostinger/hcomponents';
import { computed } from 'vue';

import PluginEntrySkeleton from '@/components/skeletons/PluginEntrySkeleton.vue';
import Toggle from '@/components/Toggle.vue';
import { WOOCOMMERCE_ID } from '@/data/pluginData';
import { useIntegrationsStore } from '@/stores';
import type { Form } from '@/types/models';
import { translate } from '@/utils/translate';

const emit = defineEmits<{
	goToPlugin: [id: string];
	disconnectPlugin: [id: string];
	toggleFormStatus: [form: Form, status: boolean];
}>();

const SKELETON_COUNT = 3;

const integrationsStore = useIntegrationsStore();
const integration = computed(() => integrationsStore?.activeIntegrations.find((i) => i.id === WOOCOMMERCE_ID));
const forms = computed(() => integration?.value?.forms || []);
const canToggle = computed(() => Boolean(integration.value?.canToggleForms));

const formsData = {
	woocommerce: {
		title: translate('hostinger_reach_woocommerce_checkout_title'),
		content: translate('hostinger_reach_woocommerce_checkout_description'),
		learnMore: null
	},
	'order.purchased': {
		title: translate('hostinger_reach_woocommerce_order_purchased_title'),
		content: translate('hostinger_reach_woocommerce_order_purchased_description'),
		learnMore: 'https://www.hostinger.com/support/how-to-set-up-post-purchase-email-automation-in-hostinger-reach/'
	},
	'cart.abandoned': {
		title: translate('hostinger_reach_woocommerce_cart_abandoned_title'),
		content: translate('hostinger_reach_woocommerce_cart_abandoned_description'),
		learnMore:
			'https://www.hostinger.com/support/how-to-set-up-abandoned-cart-email-automation-for-woocommerce-in-hostinger-reach/'
	}
};

const isAutomation = (form: Form): boolean => form?.formId?.includes('.') ?? false;
</script>

<template>
	<div class="woocommerce-entries-table">
		<div class="woocommerce-entries-table__container">
			<template v-if="forms?.length <= 0">
				<PluginEntrySkeleton v-for="i in SKELETON_COUNT" :key="`skeleton-${i}`" />
			</template>

			<template v-else>
				<div class="woocommerce-entries-table__forms-list">
					<div class="woocommerce-entries-table__forms-list__row woocommerce-entries-table__forms-list__header">
						<div
							class="woocommerce-entries-table__cell woocommerce-entries-table__first-cell woocommerce-entries-table__header-cell"
						>
							{{ translate('hostinger_reach_woocommerce_events') }}
						</div>
						<div
							class="woocommerce-entries-table__cell woocommerce-entries-table__second-cell woocommerce-entries-table__header-cell"
						>
							{{ translate('hostinger_reach_woocommerce__status') }}
						</div>
					</div>

					<div
						v-for="form in forms"
						:key="form.formId ?? form.formTitle"
						class="woocommerce-entries-table__forms-list__row"
					>
						<div
							class="woocommerce-entries-table__cell woocommerce-entries-table__first-cell woocommerce-entries-table__forms-list__event"
						>
							<div class="woocommerce-entries-table__forms-list__event-title">
								<strong class="woocommerce-entries-table__event-name">{{ formsData[form.formId]?.title }}</strong>
								<HLabel v-if="isAutomation(form)" color="grayLight" variant="contain">
									{{ translate('hostinger_reach_woocommerce_automation') }}
								</HLabel>
							</div>
							<div
								v-if="form.formId && formsData[form.formId]"
								class="woocommerce-entries-table__forms-list__event-description"
							>
								{{ formsData[form.formId]?.content ?? '' }}
								<HHyperlink
									v-if="formsData[form.formId]?.learnMore"
									:href="formsData[form.formId]?.learnMore"
									variant="regular"
									color="primary--400"
									size="small"
									target="_blank"
								>
									{{ translate('hostinger_reach_learn_more') }}
								</HHyperlink>
							</div>
						</div>

						<div
							class="woocommerce-entries-table__cell woocommerce-entries-table__second-cell woocommerce-entries-table__forms-list__action"
						>
							<div class="woocommerce-entries-table__action-toggle">
								<Toggle
									:value="form.isActive"
									:is-disabled="!canToggle || form.isLoading"
									@toggle="(status) => emit('toggleFormStatus', form, status)"
								/>
							</div>
						</div>
					</div>
				</div>
			</template>
		</div>
	</div>
</template>

<style scoped lang="scss">
.woocommerce-entries-table {
	&__container {
		background: var(--neutral--0);
		border: 1px solid var(--neutral--200);
		border-radius: 20px;
		padding: 24px;
		overflow: hidden;
	}

	&__forms-list {
		display: flex;
		flex-direction: column;
	}

	&__forms-list__row {
		display: flex;
		align-items: center;
		gap: 24px;
		padding: 18px 0;
	}

	&__forms-list__row:not(&__forms-list__header) {
		border-top: 1px solid var(--neutral--200);
	}

	&__forms-list__header {
		padding: 10px 0 18px;
		border-top: none;
	}

	&__cell {
		min-width: 0;
		display: flex;
		align-items: center;
	}

	&__first-cell {
		flex: 1 1 auto;
	}

	&__second-cell {
		flex: 0 0 200px;
		justify-content: flex-start;
	}

	&__header-cell {
		font-weight: 600;
		color: var(--neutral--900);
	}

	&__forms-list__event {
		flex-direction: column;
		align-items: flex-start;
		gap: 6px;
	}

	&__forms-list__event-title {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	&__event-name {
		font-weight: 600;
		color: var(--neutral--900);
	}

	&__forms-list__event-description {
		color: var(--neutral--600);
		line-height: 1.4;
		font-size: 12px;
	}

	&__action-toggle {
		display: flex;
		align-items: center;
		gap: 5px;
	}

	@media (max-width: 920px) {
		&__forms-list__row {
			flex-direction: column;
			align-items: stretch;
			gap: 12px;
		}

		&__second-cell {
			flex: 0 0 auto;
			justify-content: flex-start;
		}
	}

	@media (max-width: 920px) {
		&__forms-list__row {
			flex-direction: column;
			align-items: stretch;
			gap: 12px;
		}

		&__second-cell {
			flex: 0 0 auto;
			justify-content: flex-start;
		}
	}
}
</style>
