<script setup lang="ts">
import { computed } from 'vue';

import reachBackgroundImage from '@/assets/images/backgrounds/reach-welcome-background.png';
import reachBackgroundImageMobile from '@/assets/images/backgrounds/reach-welcome-background-mobile.png';
import reachLogo from '@/assets/images/icons/reach-logo.svg';
import { useReachUrls } from '@/composables/useReachUrls';
import { translate } from '@/utils/translate';

const { reachBaseDomain } = useReachUrls();

const reachDashboardUrl = computed(() => `https://${reachBaseDomain.value}`);

interface Props {
	isConnectedToAnotherSite?: boolean;
	isButtonLoading?: boolean;
	domain: string;
	onGetStarted: () => void;
	onManualApiKeyClick?: () => void;
}

const props = withDefaults(defineProps<Props>(), {
	isConnectedToAnotherSite: false,
	isButtonLoading: false,
	onManualApiKeyClick: () => {}
});
</script>

<template>
	<section class="hero" role="main" aria-labelledby="hero-heading">
		<header class="hero__header">
			<div class="hero__header-content">
				<div class="hero__header-brand">
					<img :src="reachLogo" :alt="translate('hostinger_reach_header_logo_alt')" class="hero__header-logo" />
				</div>
				<HButton
					variant="outline"
					target="_blank"
					color="neutral"
					size="small"
					icon-append="ic-launch-16"
					:to="reachDashboardUrl"
				>
					{{ translate('hostinger_reach_header_go_to_reach_button') }}
				</HButton>
			</div>
		</header>

		<HCard border-radius="20px" padding="0" border-color="neutral--200">
			<div class="hero__image">
				<img
					:src="reachBackgroundImage"
					:alt="translate('hostinger_reach_hero_background_alt')"
					role="img"
					style="display: none"
				/>
				<img
					:src="reachBackgroundImageMobile"
					:alt="translate('hostinger_reach_hero_background_alt')"
					class="hero__background"
					role="img"
				/>
			</div>
			<div
				style="
					background-image: url('/wp-content/plugins/hostinger-reach/frontend/dist/assets/reach-welcome-background.png');
				"
				class="hero__content"
			>
				<div class="hero__content-wrapper">
					<HText id="hero-heading" as="h1" variant="heading-1">
						{{ translate('hostinger_reach_welcome_view_title') }}
					</HText>
					<HText id="hero-description" as="p" variant="body-2 h-mb-24">
						{{ translate('hostinger_reach_welcome_view_description') }}
					</HText>
					<template v-if="!isConnectedToAnotherSite">
						<div class="hero__actions">
							<HButton
								color="primary"
								size="small"
								:is-loading="props.isButtonLoading"
								aria-describedby="hero-description"
								:aria-label="translate('hostinger_reach_welcome_view_start_button')"
								@click="onGetStarted"
							>
								{{ translate('hostinger_reach_welcome_view_start_button') }}
							</HButton>
							<HButton variant="text" color="neutral" size="small" @click="onManualApiKeyClick">
								{{ translate('hostinger_reach_api_key_modal_link') }}
							</HButton>
						</div>
					</template>
				</div>
			</div>
			<div class="hero__footer" role="contentinfo" aria-label="Site information">
				<HIcon class="h-mr-8" name="ic-globe-16" color="neutral--300" aria-hidden="true" />
				<HText as="p" variant="body-2-bold" color="neutral--500">
					{{ domain }}
				</HText>
			</div>
		</HCard>
	</section>
</template>

<style scoped lang="scss">
.hero {
	display: flex;
	align-items: center;
	flex-direction: column;
	max-width: 780px;
	margin: 40px auto 16px auto;

	@media (max-width: 1024px) {
		max-width: 90%;
		margin: 32px auto 16px auto;
	}

	@media (max-width: 768px) {
		flex-direction: column;
		text-align: left;
		padding: 24px 16px;
		gap: 32px;
		margin: 20px auto 16px auto;
	}

	@media (max-width: 480px) {
		padding: 16px 8px;
		gap: 24px;
		margin: 16px auto 12px auto;
	}

	&__banner {
		display: flex;
		width: 100%;
		align-items: center;
		justify-content: space-between;
		gap: 16px;

		@media (max-width: 768px) {
			flex-direction: column;
			align-items: flex-start;
			gap: 12px;
		}

		@media (max-width: 480px) {
			gap: 8px;
		}
	}

	&__banner-content {
		display: flex;
		flex-direction: column;
		gap: 4px;

		@media (max-width: 768px) {
			width: 100%;
		}
	}

	&__content {
		flex: 1;
		padding: 28px;
		width: 100%;
		border-radius: 20px;
		background-size: cover;
		background-position: right;
		background-repeat: no-repeat;

		@media (max-width: 768px) {
			padding: 24px 24px 0;
			background: none;
			border-radius: 0;
		}
	}

	&__image {
		width: 100%;
		display: flex;
		justify-content: center;
		position: relative;
		overflow: hidden;
		margin-bottom: 0;
		border-bottom: 1px solid var(--neutral--50);

		@media (min-width: 768px) {
			display: none;
		}
	}

	&__background {
		width: 100%;
		height: auto;
		border-top-right-radius: 20px;
		border-top-left-radius: 20px;
		border-bottom-right-radius: 0;
		border-bottom-left-radius: 0;
	}

	&__content-wrapper {
		display: flex;
		flex-direction: column;
		gap: 10px;

		@media (min-width: 768px) {
			width: 50%;
			max-width: 345px;
		}
	}

	&__actions {
		display: flex;
		align-items: center;
		gap: 12px;
	}

	&__manual-link {
		appearance: none;
		background: transparent;
		border: 0;
		color: var(--primary--500);
		cursor: pointer;
		font: inherit;
		text-decoration: underline;
	}

	&__api-key-box {
		display: flex;
		flex-direction: column;
		gap: 8px;
		max-width: 420px;
	}

	&__api-key-label {
		color: var(--neutral--700);
		font-weight: 600;
	}

	&__api-key-input {
		width: 100%;
		border: 1px solid var(--neutral--200);
		border-radius: 8px;
		padding: 8px 12px;
		font-size: 14px;
	}

	&__api-actions {
		display: flex;
		gap: 12px;
		align-items: center;
	}

	&__footer {
		width: 100%;
		display: flex;
		align-items: center;
		padding: 16px 24px 20px 24px;
		border-bottom-left-radius: 20px;
		background: var(--neutral--50);
		border-bottom-right-radius: 20px;

		@media (max-width: 768px) {
			padding: 16px;
		}
	}

	&__header {
		width: 100%;
		margin-bottom: 20px;

		@media (max-width: 768px) {
			padding: 0 12px;
			margin-bottom: 16px;
		}

		@media (max-width: 480px) {
			padding: 0 8px;
			margin-bottom: 12px;
		}
	}

	&__warning-snackbar {
		display: flex;
		align-items: center;
	}

	&__header-content {
		display: flex;
		justify-content: space-between;
		align-items: center;
		width: 100%;

		@media (max-width: 768px) {
			flex-direction: column;
			gap: 16px;
			align-items: flex-start;
		}

		@media (max-width: 480px) {
			gap: 12px;
		}

		:deep(.h-button-v2) {
			@media (max-width: 768px) {
				width: 100%;
			}
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

	&__header-title {
		margin-bottom: 12px;
		color: var(--neutral--700);
	}

	&__warning-snackbar {
		margin-bottom: 24px;
	}
}

:deep(.h-card) {
	width: 100%;
}

:deep(.h-notification-row) {
	border: 1px solid #ffd28c;
	@media (max-width: 768px) {
		padding: 0;
	}
}

@media (max-width: 320px) {
	.hero {
		padding: 12px 4px;
		gap: 20px;

		&__content {
			padding: 12px 12px 0px 12px;
		}
	}
}
</style>
