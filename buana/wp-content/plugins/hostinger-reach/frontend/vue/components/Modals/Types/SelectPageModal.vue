<script lang="ts" setup>
import { HButton, HIcon, HRadio, HSkeletonLoader, HText } from '@hostinger/hcomponents';
import { computed, onMounted, ref } from 'vue';

import BaseModal from '@/components/Modals/Base/BaseModal.vue';
import Pagination from '@/components/Pagination.vue';
import { useModal } from '@/composables';
import type { BuilderForm } from '@/data/repositories/reachRepo';
import { usePagesStore } from '@/stores/pagesStore';
import type { Page, WordPressPage } from '@/types/models/pagesModels';
import { translate } from '@/utils/translate';

interface Props {
	pages?: Page[];
	data?: Record<string, unknown>;
}

const props = defineProps<Props>();

const { closeModal } = useModal();

const selectedForm = computed(() => props.data?.selectedForm as BuilderForm | undefined);
const formPreviewUrl = computed(() => props.data?.formPreviewUrl as string | undefined);
const hasPreviewFailed = ref(false);

const pagesStore = usePagesStore();

const reachData = hostinger_reach_reach_data;
const adminUrl = (reachData?.admin_url || '/wp-admin/').replace(/\/?$/, '/');
const addBlockNonce = reachData?.add_block_nonce || '';
const isElementorActive = reachData?.is_elementor_active ?? false;
const elementorNewPageUrl = reachData?.elementor_new_page_url || '';

const addBlockValue = computed(() => selectedForm.value?.uuid ?? '1');

const newPageLink = computed(() => {
	if (isElementorActive && elementorNewPageUrl) {
		const separator = elementorNewPageUrl.includes('?') ? '&' : '?';

		return `${elementorNewPageUrl}${separator}hostinger_reach_add_block=${encodeURIComponent(addBlockValue.value)}`;
	}

	return `${adminUrl}post-new.php?post_type=page&hostinger_reach_add_block=${encodeURIComponent(addBlockValue.value)}&_wpnonce=${encodeURIComponent(addBlockNonce)}`;
});

const loadingPageId = ref<string | null>(null);
const selectedPageId = ref<string | null>(null);
const isNewFormButtonLoading = ref(false);

const currentPages = computed(() => {
	const storePages = pagesStore.items;

	return storePages && storePages.length > 0 ? storePages : props.pages || [];
});

const getPageDisplayName = (page: Page | WordPressPage): string => {
	let name = translate('hostinger_reach_forms_no_title');

	if ('name' in page && page.name) {
		name = page.name;
	} else if ('title' in page && page.title?.rendered) {
		name = page.title.rendered;
	}

	if (page.status === 'draft') {
		name = `${name} ${translate('hostinger_reach_forms_page_draft_suffix')}`;
	}

	return name;
};

const isPageSelected = (page: Page | WordPressPage): boolean => selectedPageId.value === String(page.id);

const isLoading = computed(() => pagesStore.isLoading);
const currentPage = computed(() => pagesStore.currentPage);
const totalItems = computed(() => pagesStore.totalItems);
const itemsPerPage = computed(() => pagesStore.itemsPerPage);

const handlePageChange = async (page: number) => {
	selectedPageId.value = null;
	await pagesStore.goToPage(page);
};

const handlePageSelect = (page: Page | WordPressPage) => {
	if (loadingPageId.value) return;

	selectedPageId.value = String(page.id);
};

const handlePageClick = (page: Page | WordPressPage) => {
	if (loadingPageId.value) return;

	loadingPageId.value = String(page.id);

	if (page.link) {
		const pageUrl = `${page.link}&hostinger_reach_add_block=${encodeURIComponent(addBlockValue.value)}&_wpnonce=${encodeURIComponent(addBlockNonce)}`;
		window.location.href = pageUrl;
	}
};

const handleConfirm = () => {
	if (!selectedPageId.value) return;

	const page = currentPages.value.find((item) => String(item.id) === selectedPageId.value);
	if (page) {
		handlePageClick(page);
	}
};

const handleNewFormClick = () => {
	if (isNewFormButtonLoading.value) return;

	isNewFormButtonLoading.value = true;

	window.location.href = newPageLink.value;
};

const handleBackClick = () => {
	const backButtonAction = props.data?.backButtonRedirectAction as (() => void) | undefined;
	if (backButtonAction) {
		backButtonAction();
	}
};

onMounted(async () => {
	await pagesStore.resetToFirstPage();
});
</script>

<template>
	<BaseModal title-alignment="left" :title="translate('hostinger_reach_select_page_modal_title')">
		<template v-if="data?.backButtonRedirectAction && !selectedForm" #back-button>
			<button class="select-page-modal__back-button" type="button" @click="handleBackClick">
				<HIcon name="ic-chevron-left-16" color="neutral--600" />
			</button>
		</template>

		<template v-if="selectedForm" #header-content>
			<div class="select-page-modal__selected-form">
				<div class="select-page-modal__selected-form-info">
					<img
						v-if="formPreviewUrl && !hasPreviewFailed"
						class="select-page-modal__selected-form-image"
						:src="formPreviewUrl"
						:alt="selectedForm.name"
						@error="hasPreviewFailed = true"
					/>
					<div v-else class="select-page-modal__selected-form-image select-page-modal__selected-form-image--fallback">
						<HIcon name="ic-image-24" color="neutral--400" />
					</div>

					<div class="select-page-modal__selected-form-text">
						<HText variant="body-1-bold" as="span" class="select-page-modal__selected-form-name">
							{{ selectedForm.name }}
						</HText>
						<HText variant="body-2-medium" as="span" class="select-page-modal__selected-form-hint">
							{{ translate('hostinger_reach_select_page_modal_selected_form_hint') }}
						</HText>
					</div>
				</div>

				<HButton variant="text" color="primary" size="small" @click="handleBackClick">
					{{ translate('hostinger_reach_select_page_modal_change_selection') }}
				</HButton>
			</div>
		</template>

		<div class="select-page-modal">
			<div class="select-page-modal__content">
				<div class="select-page-modal__pages">
					<template v-if="isLoading">
						<div
							v-for="n in itemsPerPage"
							:key="`skeleton-${n}`"
							class="select-page-modal__page-item select-page-modal__page-item--loading"
						>
							<div class="select-page-modal__page-loading">
								<HSkeletonLoader width="60%" height="20px" border-radius="sm" />
							</div>
						</div>
					</template>

					<template v-else-if="currentPages && currentPages.length > 0">
						<div
							v-for="page in currentPages"
							:key="page.id"
							class="select-page-modal__page-item"
							:class="{
								'select-page-modal__page-item--selected': isPageSelected(page),
								'select-page-modal__page-item--loading': loadingPageId === String(page.id)
							}"
							@click="handlePageSelect(page)"
						>
							<div v-if="loadingPageId === String(page.id)" class="select-page-modal__page-loading">
								<HSkeletonLoader width="60%" height="20px" border-radius="sm" />
							</div>
							<template v-else>
								<div class="select-page-modal__page-content">
									<HText variant="body-2-bold" as="span" class="select-page-modal__page-name">
										{{ getPageDisplayName(page) }}
									</HText>
								</div>

								<div>
									<HRadio
										:value="String(page.id)"
										:model-value="selectedPageId ?? ''"
										color="primary"
										non-interactive-mode
									/>
								</div>
							</template>
						</div>
					</template>

					<template v-else-if="!isLoading">
						<div class="select-page-modal__no-pages">
							<HText variant="body-2" as="p" class="select-page-modal__no-pages-text">
								{{ translate('hostinger_reach_forms_no_pages_available') }}
							</HText>
						</div>
					</template>
				</div>
			</div>

			<div class="select-page-modal__pagination">
				<Pagination
					:current-page="currentPage"
					:total-items="totalItems"
					:items-per-page="itemsPerPage"
					:total-visible="5"
					:disabled="isLoading"
					@page-change="handlePageChange"
					@update:current-page="handlePageChange"
				/>
			</div>

			<div class="select-page-modal__footer">
				<HButton
					variant="outline"
					color="neutral"
					size="small"
					:icon-prepend="isNewFormButtonLoading ? undefined : 'ic-add-16'"
					:is-loading="isNewFormButtonLoading"
					@click="handleNewFormClick"
				>
					{{ translate('hostinger_reach_select_page_modal_create_new_page') }}
				</HButton>

				<div class="select-page-modal__footer-actions">
					<HButton variant="text" color="neutral" size="small" @click="closeModal">
						{{ translate('hostinger_reach_select_page_modal_cancel') }}
					</HButton>
					<HButton color="primary" size="small" :is-disabled="!selectedPageId" @click="handleConfirm">
						{{ translate('hostinger_reach_select_page_modal_confirm') }}
					</HButton>
				</div>
			</div>
		</div>
	</BaseModal>
</template>

<style lang="scss" scoped>
:deep(.base-modal__header) {
	margin: 0;
	padding: 24px;
	border-bottom: 1px solid var(--neutral--200);
}

.select-page-modal {
	&__back-button {
		position: absolute;
		top: 0;
		left: 0;
		display: flex;
		align-items: center;
		justify-content: center;
		width: 32px;
		height: 32px;
		border: none;
		background: transparent;
		border-radius: 8px;
		cursor: pointer;
		transition: background-color 0.2s ease;

		&:hover {
			background-color: var(--neutral--100);
		}

		&:active {
			background-color: var(--neutral--200);
		}
	}

	&__content {
		display: flex;
		flex-direction: column;
		gap: 20px;
		align-items: center;
		padding: 24px 24px 0;
	}

	&__selected-form {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 12px;
		width: 100%;
		margin-top: 24px;
		padding: 12px;
		border: 2px solid transparent;
		border-radius: var(--h-border-radius-lg);
		background:
			linear-gradient(var(--neutral--0), var(--neutral--0)) padding-box,
			linear-gradient(90deg, rgba(58, 176, 255, 1), rgba(103, 61, 230, 1), rgba(229, 54, 219, 1)) border-box;
	}

	&__selected-form-info {
		display: flex;
		align-items: center;
		gap: 12px;
		min-width: 0;
	}

	&__selected-form-image {
		height: 44px;
		width: auto;
		max-width: 72px;
		flex-shrink: 0;
		object-fit: cover;
		border-radius: var(--h-border-radius-sm);

		&--fallback {
			display: flex;
			align-items: center;
			justify-content: center;
			width: 44px;
			background: var(--neutral--100);
		}
	}

	&__selected-form-text {
		display: flex;
		flex-direction: column;
		min-width: 0;
	}

	&__selected-form-name {
		color: var(--neutral--800);
	}

	&__selected-form-hint {
		color: var(--neutral--500);
	}

	&__pages {
		display: flex;
		flex-direction: column;
		gap: 8px;
		width: 100%;
		max-width: 100%;
	}

	&__page-item {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 60px;
		gap: 16px;
		padding: 20px;
		border: 1px solid var(--neutral--200);
		border-radius: 16px;
		background: var(--neutral--0);
		cursor: pointer;
		transition:
			border-color 0.2s ease,
			opacity 0.2s ease;

		&:hover:not(&--loading) {
			border-color: var(--primary--500);
		}

		&--selected {
			border-color: var(--primary--500);
			background: #f5f6ff;
		}

		&--loading {
			cursor: not-allowed;
			opacity: 0.7;
			border-color: var(--neutral--300);
		}
	}

	&__page-loading {
		display: flex;
		align-items: center;
		width: 100%;
		gap: 12px;
	}

	&__page-content {
		display: flex;
		align-items: center;
		gap: 12px;
		flex: 1;
	}

	&__page-name {
		font-weight: 700;
	}

	&__checkmark {
		width: 20px;
		height: 20px;
		display: flex;
		align-items: center;
		justify-content: center;
		flex-shrink: 0;
	}

	&__no-pages {
		display: flex;
		flex-direction: column;
		align-items: center;
		justify-content: center;
		gap: 12px;
		padding: 40px 20px;
		text-align: center;
	}

	&__no-pages-icon {
		width: 32px;
		height: 32px;
		color: var(--neutral--400);
	}

	&__no-pages-text {
		color: var(--neutral--500);
		margin: 0;
	}

	&__pagination {
		display: flex;
		justify-content: center;
		align-items: center;
		padding: 16px 24px;
	}

	&__footer {
		display: flex;
		justify-content: space-between;
		align-items: center;
		gap: 8px;
		padding: 16px 24px;
	}

	&__footer-actions {
		display: flex;
		align-items: center;
		gap: 8px;
	}

	@media (max-width: 640px) {
		&__page-item {
			padding: 16px;
		}

		&__footer {
			flex-direction: column-reverse;
			gap: 12px;

			:deep(.h-button) {
				width: 100%;
			}
		}
	}

	@media (max-width: 480px) {
		&__pages {
			gap: 6px;
		}

		&__page-item {
			padding: 14px;
		}
	}
}
</style>
