<script setup lang="ts">
import { HHyperlink } from '@hostinger/hcomponents';
import { ref } from 'vue';
import { useRouter } from 'vue-router';

import { useModal } from '@/composables/useModal';
import { useReachUrls } from '@/composables/useReachUrls';
import { reachRepo } from '@/data/repositories/reachRepo';
import { Route } from '@/types/enums/routeEnum';
import { translate } from '@/utils/translate';

interface Props {
	apiKey?: string;
}

const props = withDefaults(defineProps<Props>(), {
	apiKey: ''
});

const { closeModal } = useModal();
const router = useRouter();
const { reachTokensLink } = useReachUrls();
const errorMessage = ref('');

const isLoading = ref(false);
const key = ref(props.apiKey || '');

const getCsrfFromGenerateAuthUrl = async (): Promise<string | null> => {
	const [data] = await reachRepo.generateAuthUrl();
	if (!data?.authUrl) return null;

	try {
		const url = new URL(data.authUrl);
		const redirectUrl = url.searchParams.get('redirectUrl');
		if (!redirectUrl) return null;

		const decoded = decodeURIComponent(redirectUrl);
		const innerUrl = new URL(decoded);
		const token = innerUrl.searchParams.get('token');

		return token;
	} catch (error) {
		console.error(error);

		return null;
	}
};

const tryConnect = async (csrf: string, token: string) => {
	isLoading.value = true;
	errorMessage.value = '';

	const [data, error] = await reachRepo.postToken(csrf, token);

	if (error || !data?.success) {
		isLoading.value = false;
		errorMessage.value = error?.message || translate('hostinger_reach_error_connection_failed');

		return;
	}

	const [connectData, connectError] = await reachRepo.postConnect();

	if (connectError || !connectData?.success) {
		isLoading.value = false;
		errorMessage.value = connectError?.message || translate('hostinger_reach_error_connection_failed');

		return;
	}

	isLoading.value = false;
	hostinger_reach_reach_data.is_connected = true;
	closeModal();
	router.push({ name: Route.Base.OVERVIEW });
};

const handleSaveAndConnect = async () => {
	errorMessage.value = '';

	if (!key.value) {
		errorMessage.value = translate('hostinger_reach_error_no_key');

		return;
	}

	const csrf = await getCsrfFromGenerateAuthUrl();

	if (!csrf) {
		errorMessage.value = translate('hostinger_reach_error_connection_failed');

		return;
	}

	await tryConnect(csrf, key.value);
};
</script>

<template>
	<div class="reach-api-key-modal">
		<HText as="h3" variant="heading-3" class="h-mb-12">
			{{ translate('hostinger_reach_reach_api_key_modal_title') }}
		</HText>
		<div class="reach-api-key-modal__body">
			<div class="reach-api-key-modal__steps">
				<HText as="h4" variant="body-1-bold">
					{{ translate('hostinger_reach_reach_api_key_modal_steps_title') }}
				</HText>
				<div class="reach-api-key-modal__steps-list">
					<div class="reach-api-key-modal__step">
						<span class="reach-api-key-modal__step-number">1</span>
						<p class="h-typography h-t-body-2 reach-api-key-modal__step-text">
							<HText as="span" variant="body-2">
								{{ translate('hostinger_reach_reach_api_key_modal_steps_one') }} {{ ' ' }}
							</HText>
							<HHyperlink
								:href="reachTokensLink"
								size="small"
								target="_blank"
								variant="regular"
								icon-append="ic-launch-16"
							>
								{{ translate('hostinger_reach_reach_api_key_modal_steps_one_link') }}
							</HHyperlink>
						</p>
					</div>
					<div class="reach-api-key-modal__step">
						<span class="reach-api-key-modal__step-number">2</span>
						<p
							class="h-typography h-t-body-2 reach-api-key-modal__step-text"
							v-html="translate('hostinger_reach_reach_api_key_modal_steps_two')"
						></p>
					</div>
					<div class="reach-api-key-modal__step">
						<span class="reach-api-key-modal__step-number">3</span>
						<p
							class="h-typography h-t-body-2 reach-api-key-modal__step-text"
							v-html="translate('hostinger_reach_reach_api_key_modal_steps_three')"
						></p>
					</div>
					<div class="reach-api-key-modal__step">
						<span class="reach-api-key-modal__step-number">4</span>
						<p
							class="h-typography h-t-body-2 reach-api-key-modal__step-text"
							v-html="translate('hostinger_reach_reach_api_key_modal_steps_four')"
						></p>
					</div>
				</div>
			</div>
			<div class="reach-api-key-modal__field">
				<label for="reach-api-key-modal-input" class="reach-api-key-modal__label">
					<HText as="span" variant="body-2-bold">
						{{ translate('hostinger_reach_reach_api_key_modal_field_label') }}
					</HText>
				</label>
				<input
					id="reach-api-key-modal-input"
					:class="{ 'reach-api-key-modal__input': true, 'reach-api-key-modal__input--error': errorMessage }"
					type="password"
					:placeholder="translate('hostinger_reach_reach_api_key_modal_field_placholder')"
					:value="key"
					autocomplete="off"
					@input="
						key = ($event.target as HTMLInputElement).value;
						errorMessage = '';
					"
				/>
				<HText
					v-if="errorMessage"
					as="p"
					color="danger--500"
					variant="body-3"
					class="reach-api-key-modal__error-message"
				>
					{{ errorMessage }}
				</HText>
			</div>
		</div>
		<div class="reach-api-key-modal__actions">
			<HButton variant="text" color="primary" size="small" @click="closeModal">
				{{ translate('hostinger_reach_reach_api_key_modal_cancel') }}
			</HButton>
			<HButton color="primary" size="small" :is-loading="isLoading" @click="handleSaveAndConnect">
				{{ translate('hostinger_reach_reach_api_key_modal_button') }}
			</HButton>
		</div>
	</div>
</template>

<style scoped lang="scss">
.reach-api-key-modal {
	display: flex;
	flex-direction: column;
	gap: 8px;

	&__body {
		border: 1px solid var(--neutral--100);
		border-radius: 8px;
		padding: 24px;
		display: flex;
		flex-direction: column;
		gap: 20px;
		background-color: var(--neutral--50);
	}

	&__steps {
		display: flex;
		flex-direction: column;
		gap: 16px;

		&-list {
			display: flex;
			flex-direction: column;
			gap: 16px;
		}
	}

	&__step {
		display: flex;
		align-items: center;
		gap: 12px;

		&-number {
			display: flex;
			align-items: center;
			justify-content: center;
			min-width: 24px;
			height: 24px;
			background-color: var(--primary--50);
			color: black;
			border-radius: 50%;
			font-size: 12px;
			font-weight: 700;
		}
	}

	&__step-text {
		margin: 0;
	}

	&__field {
		display: flex;
		flex-direction: column;
		gap: 8px;
	}

	&__input {
		width: 100%;
		border: 1px solid var(--neutral--200);
		border-radius: 8px;
		padding: 8px 12px;
		font-size: 14px;
		outline: none;

		&--error {
			border-color: var(--danger--500);
		}

		&:focus {
			border-color: var(--primary--500);
		}
	}

	&__label {
		color: var(--neutral--700);
		font-weight: 600;
	}

	&__error-message {
		color: var(--danger--500);
	}

	&__actions {
		display: flex;
		gap: 12px;
		align-items: center;
		margin-top: 8px;
		justify-content: flex-end;
	}
}
</style>
