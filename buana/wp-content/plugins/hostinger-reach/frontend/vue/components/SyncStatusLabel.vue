<script setup lang="ts">
import { HIcon } from '@hostinger/hcomponents';
import { computed } from 'vue';

import type { ImportStatusType, StatusIcon } from '@/types';
import { IMPORT_STATUSES } from '@/types';
import { translate } from '@/utils/translate';

interface Props {
	enabled: boolean;
	status: ImportStatusType;
}

const props = defineProps<Props>();
const getStatusIcon = computed((): StatusIcon => {
	switch (props.status) {
		case IMPORT_STATUSES.IMPORTED:
			return { icon: 'ic-checkmark-circle-filled-16', color: 'success--500' };
		case IMPORT_STATUSES.IMPORTING:
			return { icon: 'ic-arrows-circle-16', color: 'neutral--300' };
		case IMPORT_STATUSES.NOT_IMPORTED:
			return { icon: 'ic-warning-circle-filled-16', color: 'neutral--300' };
		default:
			return { icon: 'ic-half-filled-circle-16', color: 'neutral--300' };
	}
});
</script>

<template>
	<span class="status-content" :data-status="props.status" :data-availabe="props.enabled">
		<HIcon
			v-if="props.enabled && props.status !== IMPORT_STATUSES.OFF"
			:name="getStatusIcon.icon"
			:color="getStatusIcon.color"
		/>
		{{
			props.enabled
				? translate(`hostinger_reach_contacts_${props.status}`)
				: translate('hostinger_reach_contacts_not_available')
		}}
	</span>
</template>

<style scoped lang="scss">
.status-content {
	display: flex;
	align-items: center;
	gap: 5px;

	&[data-availabe='false'] {
		color: var(--neutral--300);
		opacity: 0.7;
	}

	&[data-status='importing'] {
		svg {
			animation: spin 2.5s linear infinite;
		}
	}
}
</style>
