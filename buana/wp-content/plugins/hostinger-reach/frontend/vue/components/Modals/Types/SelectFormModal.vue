<script lang="ts" setup>
import { HButton, HHyperlink, HIcon, HIconButton, HInput, HSkeletonLoader, HText } from '@hostinger/hcomponents';
import { storeToRefs } from 'pinia';
import { computed, onMounted, ref, watch } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import SelectFormEmptyState from '@/components/Modals/Types/SelectFormEmptyState.vue';
import { useModal } from '@/composables';
import { useReachUrls } from '@/composables/useReachUrls';
import { useBuilderFormsStore } from '@/stores';
import { ModalName } from '@/types/enums';
import { translate } from '@/utils/translate';

interface Props {
	data?: Record<string, unknown>;
}

const props = defineProps<Props>();

const { closeModal, openModal } = useModal();
const { reachFormsCreateLink } = useReachUrls();

const EMBED_SCRIPT_URL = hostinger_reach_reach_data.embed_script_url;

const builderFormsStore = useBuilderFormsStore();
const { forms, isLoading, isLoaded } = storeToRefs(builderFormsStore);
const { previewImageUrl } = builderFormsStore;

const selectedFormId = ref('');
const searchQuery = ref('');
const failedPreviews = ref<Set<string>>(new Set());

const markPreviewFailed = (uuid: string) => {
	failedPreviews.value = new Set(failedPreviews.value).add(uuid);
};

const filteredForms = computed(() => {
	const query = searchQuery.value.trim().toLowerCase();

	if (!query) return forms.value;

	return forms.value.filter((form) => form.name.toLowerCase().includes(query));
});

const isLoadingForms = computed(() => isLoading.value || !isLoaded.value);
const isEmpty = computed(() => isLoaded.value && forms.value.length === 0);
const showSearch = computed(() => !isLoadingForms.value && forms.value.length > 0);
const showFooter = computed(() => !isEmpty.value);

const selectedForm = computed(() => forms.value.find((form) => form.uuid === selectedFormId.value));

watch(filteredForms, (list) => {
	if (!list.some((form) => form.uuid === selectedFormId.value)) {
		selectedFormId.value = list[0]?.uuid ?? '';
	}
});

const previewDoc = (uuid: string) =>
	`<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">` +
	`<div data-reach-form="${uuid}"></div>` +
	`<script src="${EMBED_SCRIPT_URL}" defer></scr` +
	`ipt></body></html>`;

const initSelection = () => {
	const preselectedId = props.data?.selectedFormId as string | undefined;
	selectedFormId.value =
		preselectedId && forms.value.some((form) => form.uuid === preselectedId)
			? preselectedId
			: (forms.value[0]?.uuid ?? '');
};

const loadForms = async () => {
	await builderFormsStore.loadForms();
	initSelection();
};

const handleRefresh = async () => {
	await builderFormsStore.loadForms(true);
	initSelection();
};

const openFormModal = () => {
	openModal(
		ModalName.SELECT_FORM_MODAL,
		{ data: { selectedFormId: selectedFormId.value } },
		{ hasCloseButton: true, isXXL: true, noContentPadding: true }
	);
};

const handleContinue = () => {
	if (!selectedForm.value) return;

	const onContinue = props.data?.onContinue as ((formBuilderId: string) => void) | undefined;
	if (onContinue) {
		onContinue(selectedForm.value.uuid);
		closeModal();

		return;
	}

	openModal(
		ModalName.SELECT_PAGE_MODAL,
		{
			data: {
				selectedForm: selectedForm.value,
				formPreviewUrl: previewImageUrl(selectedForm.value.uuid),
				backButtonRedirectAction: openFormModal
			}
		},
		{ hasCloseButton: true, noContentPadding: true, isMD: true }
	);
};

onMounted(loadForms);
</script>

<template>
	<BaseModal
		title-alignment="left"
		:title="translate('hostinger_reach_select_form_modal_title')"
		:subtitle="translate('hostinger_reach_select_form_modal_subtitle')"
	>
		<div class="select-form-modal">
			<div
				class="select-form-modal__content"
				:class="{
					'select-form-modal__content--empty': isEmpty,
					'select-form-modal__content--forms': !isEmpty
				}"
			>
				<div v-if="isLoadingForms" class="select-form-modal__list">
					<div class="select-form-modal__list-forms">
						<div class="select-form-modal__forms-grid">
							<div v-for="n in 4" :key="`skeleton-${n}`" class="select-form-modal__form-item">
								<HSkeletonLoader width="60%" height="20px" border-radius="sm" />
								<div class="select-form-modal__form-thumb">
									<HSkeletonLoader width="100%" height="100%" border-radius="lg" />
								</div>
							</div>
						</div>
					</div>

					<div class="select-form-modal__list-preview">
						<HSkeletonLoader width="100%" height="100%" border-radius="md" />
					</div>
				</div>

				<div v-else-if="!isEmpty" class="select-form-modal__list">
					<div class="select-form-modal__list-forms">
						<div v-if="showSearch" class="select-form-modal__search">
							<HInput
								v-model="searchQuery"
								size="small"
								icon-prepend="ic-search-16"
								:show-clear-icon="true"
								:remove-bottom-padding="true"
								:placeholder="translate('hostinger_reach_select_form_modal_search_placeholder')"
							/>
						</div>

						<div v-if="filteredForms.length > 0" class="select-form-modal__forms-grid">
							<button
								v-for="form in filteredForms"
								:key="form.uuid"
								type="button"
								class="select-form-modal__form-item"
								:class="{ 'select-form-modal__form-item--selected': form.uuid === selectedFormId }"
								@click="selectedFormId = form.uuid"
							>
								<HText variant="body-1-bold" as="span" class="select-form-modal__form-name">
									{{ form.name }}
								</HText>
								<div class="select-form-modal__form-thumb">
									<img
										v-if="!failedPreviews.has(form.uuid)"
										class="select-form-modal__form-image"
										:src="previewImageUrl(form.uuid)"
										:alt="form.name"
										loading="lazy"
										@error="markPreviewFailed(form.uuid)"
									/>
									<HIcon v-else name="ic-image-24" color="neutral--400" />
								</div>
							</button>
						</div>

						<HText v-else as="p" variant="body-2" class="select-form-modal__no-results">
							{{ translate('hostinger_reach_select_form_modal_no_results') }}
						</HText>
					</div>

					<div class="select-form-modal__list-preview">
						<iframe
							v-if="selectedFormId"
							:key="selectedFormId"
							class="select-form-modal__preview-frame"
							:srcdoc="previewDoc(selectedFormId)"
							:title="translate('hostinger_reach_select_form_modal_title')"
						></iframe>
					</div>
				</div>

				<SelectFormEmptyState v-else @refresh="handleRefresh" />
			</div>

			<div v-if="showFooter" class="select-form-modal__footer">
				<div class="select-form-modal__footer-start">
					<HHyperlink
						class="select-form-modal__footer-create"
						:href="reachFormsCreateLink"
						target="_blank"
						variant="button-look"
						icon-prepend="ic-plus-16"
						icon-append="ic-arrow-up-right-square-16"
						icon-size="16px"
						:button-look-props="{ variant: 'outline', color: 'neutral', size: 'small' }"
					>
						{{ translate('hostinger_reach_select_form_modal_create_new') }}
					</HHyperlink>

					<HIconButton
						class="select-form-modal__footer-refresh"
						icon="ic-refresh-16"
						:icon-description="translate('hostinger_reach_select_form_modal_refresh')"
						variant="outline"
						color="neutral"
						size="small"
						:is-loading="isLoadingForms"
						@click="handleRefresh"
					/>
				</div>

				<div class="select-form-modal__footer-actions">
					<HButton variant="text" color="neutral" size="small" @click="closeModal">
						{{ translate('hostinger_reach_select_form_modal_cancel') }}
					</HButton>
					<HButton color="primary" size="small" :is-disabled="!selectedFormId" @click="handleContinue">
						{{ translate('hostinger_reach_select_form_modal_continue') }}
					</HButton>
				</div>
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
:deep(.base-modal__header) {
	padding: 24px 24px 0;
}

:deep(.base-modal__subtitle) {
	padding: 0 24px;
}

.select-form-modal {
	border-top: 1px solid var(--neutral--200);

	&__content {
		padding: 24px;

		&--empty {
			padding-top: 56px;
			padding-bottom: 56px;
		}

		&--forms {
			padding: 0;
		}
	}

	&__search {
		position: sticky;
		top: 0;
		z-index: 1;
		padding: 16px 24px 8px;
		background: var(--neutral--0, #fff);

		:deep(.h-form-field) {
			margin-bottom: 0;
		}

		:deep(input:focus) {
			box-shadow: none;
			outline: 0;
		}
	}

	&__list {
		display: grid;
		grid-template-columns: 1fr 1fr;
		height: 480px;
	}

	&__list-forms {
		display: flex;
		flex-direction: column;
		min-height: 0;
		overflow-y: auto;
	}

	&__forms-grid {
		display: grid;
		grid-template-columns: 1fr 1fr;
		gap: 16px;
		align-content: start;
		padding: 24px;
	}

	&__no-results {
		padding: 24px 16px;
		color: var(--neutral--300);
		text-align: center;
		padding: 16px;
	}

	&__no-results {
		padding: 24px 16px;
		color: var(--neutral--300);
		text-align: center;
	}

	&__list-preview {
		display: flex;
		flex-direction: column;
		overflow-y: auto;
		padding: 24px;
		background: #f5f5f6;
	}

	&__form-item {
		display: flex;
		flex-direction: column;
		gap: 8px;
		padding: 0;
		border: none;
		background: transparent;
		cursor: pointer;
		text-align: left;

		&:focus-visible {
			outline: none;
		}
	}

	&__form-name {
		color: var(--neutral--800);
	}

	&__form-thumb {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 100%;
		height: 140px;
		overflow: hidden;
		background: #f5f5f6;
		border-radius: 16px;
		border: 3px solid transparent;
		transition: border-color 0.2s ease;
	}

	&__form-image {
		width: 100%;
		height: 100%;
		object-fit: cover;
	}

	&__form-item--selected &__form-thumb {
		border-color: var(--h-bg-brand-default);
	}

	&__preview-frame {
		width: 100%;
		height: 100%;
		min-height: 320px;
		border: 1px solid var(--neutral--200);
		border-radius: 12px;
	}

	&__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 8px;
		flex-wrap: wrap;
		border-top: 1px solid var(--neutral--200);
		padding: 16px 24px;
	}

	&__footer-start {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__footer-actions {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	&__footer-create,
	&__footer-refresh {
		border: 1px solid var(--neutral--300) !important;
	}

	@media (max-width: 768px) {
		&__list {
			grid-template-columns: 1fr;
		}

		&__forms-grid {
			grid-template-columns: 1fr;
		}

		&__list-preview {
			display: none;
		}
	}
}
</style>
