<script setup lang="ts">
import { HHyperlink } from '@hostinger/hcomponents';

import { useReachUrls } from '@/composables/useReachUrls';
import { translate } from '@/utils/translate';

const { reachContactsLink } = useReachUrls();
</script>

<template>
	<HCard class="reach-contacts-summary" padding="20px 24px" border-radius="20px">
		<div class="reach-contacts-summary__icon">
			<HIcon name="ic-user-double-16" color="primary--500" aria-hidden="true" />
		</div>

		<div class="reach-contacts-summary__description">
			<HText as="span" variant="body-2">
				{{ translate('hostinger_reach_woocommerce_subscribed_contacts_description') }}
			</HText>
			{{ ' ' }}
			<HHyperlink :href="reachContactsLink" target="_blank" size="small" variant="bold" color="primary--400">
				{{ translate('hostinger_reach_woocommerce_view_contacts_link') }}
			</HHyperlink>
		</div>
	</HCard>
</template>

<style scoped lang="scss">
.reach-contacts-summary {
	display: flex;
	align-items: center;
	gap: 20px;
	border: 1px solid var(--neutral--200);

	&__icon {
		flex: 0 0 auto;
		width: 40px;
		height: 40px;
		border-radius: 10px;
		background: var(--primary--50);
		display: flex;
		align-items: center;
		justify-content: center;
	}

	&__description {
		flex: 1 1 auto;
		color: var(--neutral--600);
		line-height: 1.4;
	}
}
</style>
