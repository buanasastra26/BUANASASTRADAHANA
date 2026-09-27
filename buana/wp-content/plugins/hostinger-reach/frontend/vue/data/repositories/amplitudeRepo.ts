import { useGeneralDataStore } from '@/stores';
import { Header } from '@/types/enums';
import type { AmplitudeEvent } from '@/types/enums/amplitudeEnum';
import httpService from '@/utils/services/httpService';

const URL = `${hostinger_reach_reach_data.rest_base_url}hostinger-amplitude/v1`;
export const amplitudeRepo = {
	postHostingerAmplitude: (action: AmplitudeEvent, properties?: { [key: string]: unknown }) => {
		const { nonce } = useGeneralDataStore();

		return httpService.post<boolean>(
			`${URL}/hostinger-amplitude-event`,
			{ params: { action, ...properties } },
			{
				headers: { [Header.WP_NONCE]: nonce }
			}
		);
	}
};
