<script lang="ts" setup>
import { HButton, HHyperlink, HIcon, HText } from '@hostinger/hcomponents';

import { useReachUrls } from '@/composables/useReachUrls';
import { translate } from '@/utils/translate';

const emit = defineEmits<{ refresh: [] }>();

const { reachFormsLink } = useReachUrls();
</script>

<template>
	<div class="select-form-modal__empty">
		<div class="select-form-modal__empty-icon">
			<HIcon name="ic-file-16" color="neutral--800" />
		</div>

		<div class="select-form-modal__empty-body">
			<div class="select-form-modal__empty-text">
				<HText as="h3" variant="heading-3" class="select-form-modal__empty-title">
					{{ translate('hostinger_reach_select_form_modal_empty_title') }}
				</HText>
				<HText as="p" variant="body-2" class="select-form-modal__empty-subtitle">
					{{ translate('hostinger_reach_select_form_modal_empty_subtitle') }}
				</HText>
			</div>

			<div class="select-form-modal__empty-action">
				<HHyperlink
					:href="reachFormsLink"
					target="_blank"
					variant="button-look"
					icon-prepend="ic-plus-16"
					icon-append="ic-arrow-up-right-square-16"
					icon-size="16px"
					:button-look-props="{ variant: 'contain', color: 'neutral', size: 'small' }"
				>
					{{ translate('hostinger_reach_select_form_modal_empty_create') }}
				</HHyperlink>

				<HButton
					class="select-form-modal__empty-refresh"
					variant="outline"
					color="neutral"
					size="small"
					icon-prepend="ic-refresh-16"
					@click="emit('refresh')"
				>
					{{ translate('hostinger_reach_select_form_modal_refresh') }}
				</HButton>
			</div>
		</div>
	</div>
</template>

<style lang="scss" scoped>
.select-form-modal {
	&__empty {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 32px;
		max-width: 372px;
		margin: 0 auto;
		text-align: center;
	}

	&__empty-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 60px;
		height: 60px;
		border: 1px solid rgba(235, 235, 235, 1);
		border-radius: 12px;
		background: rgba(250, 250, 250, 1);
	}

	&__empty-body {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 24px;
	}

	&__empty-text {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 4px;
	}

	&__empty-title {
		font-size: 18px;
		font-weight: 600;
	}

	&__empty-subtitle {
		color: var(--neutral--300);
	}

	&__empty-action {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 8px;
	}

	&__empty-refresh {
		border: 1px solid var(--neutral--300) !important;
		color: var(--neutral--800) !important;
	}
}
</style>
