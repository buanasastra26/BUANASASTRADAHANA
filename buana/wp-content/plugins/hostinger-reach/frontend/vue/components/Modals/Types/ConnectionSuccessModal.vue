<script lang="ts" setup>
import { computed, onUnmounted } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import RecommendedPlugins from '@/components/RecommendedPlugins.vue';
import { useModal } from '@/composables';
import { reachRepo } from '@/data/repositories/reachRepo';
import { ModalName } from '@/types/enums';
import { translate } from '@/utils/translate';

const { openModal } = useModal();

const title = computed(
	() => `${hostinger_reach_reach_data.domain} ${translate('hostinger_reach_connection_success_modal_title')}`
);

// Clear the server-side transient however the modal is closed (X, overlay, ESC or navigating to the form modal).
onUnmounted(() => {
	reachRepo.deleteConnectionSuccess();
});

const handleAddForm = () => {
	openModal(ModalName.SELECT_FORM_MODAL, {}, { hasCloseButton: true, isXXL: true, noContentPadding: true });
};

const openSelf = () => {
	openModal(ModalName.CONNECTION_SUCCESS_MODAL, {}, { hasCloseButton: true, isXL: true, noContentPadding: true });
};
</script>

<template>
	<BaseModal>
		<div class="connection-success-modal">
			<div class="connection-success-modal__inner">
				<div class="connection-success-modal__heading">
					<div class="connection-success-modal__title-row">
						<HText as="h2" variant="heading-2" class="connection-success-modal__title">
							{{ title }}
						</HText>
						<HIcon name="ic-confetti-24" />
					</div>
					<HText as="p" variant="body-2" class="connection-success-modal__subtitle">
						{{ translate('hostinger_reach_connection_success_modal_subtitle') }}
					</HText>
				</div>

				<HButton
					color="primary"
					size="medium"
					icon-prepend="ic-plus-16"
					class="connection-success-modal__add-form"
					@click="handleAddForm"
				>
					{{ translate('hostinger_reach_connection_success_modal_add_form') }}
				</HButton>

				<RecommendedPlugins class="connection-success-modal__plugins" :more-back-action="openSelf" />
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
.connection-success-modal {
	padding: 56px;

	&__inner {
		display: flex;
		flex-direction: column;
		align-items: center;
		max-width: 500px;
		margin: 0 auto;
	}

	&__heading {
		display: flex;
		flex-direction: column;
		align-items: center;
		gap: 8px;
	}

	&__title-row {
		display: flex;
		align-items: center;
		justify-content: center;
		gap: 8px;
	}

	&__title {
		margin: 0;
	}

	&__subtitle {
		color: var(--neutral--500);
		text-align: center;
	}

	&__add-form {
		margin-top: 24px;
		align-self: center;
	}

	&__plugins {
		margin-top: 24px;
		width: 100%;
	}
}
</style>
