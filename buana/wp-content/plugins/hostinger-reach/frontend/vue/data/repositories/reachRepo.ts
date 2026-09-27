import { useGeneralDataStore } from '@/stores/generalDataStore';
import { Header } from '@/types/enums';
import type { AuthorizeRequestHeaders } from '@/types/models';
import type { OverviewData } from '@/types/models/reachDataModels';
import { generateCorrelationId } from '@/utils/helpers';
import httpService from '@/utils/services/httpService';

const URL = `${hostinger_reach_reach_data.rest_base_url}hostinger-reach/v1`;

export interface BuilderForm {
	uuid: string;
	name: string;
	status?: string;
	type?: string;
}

export interface BuilderFormsResponse {
	resources: BuilderForm[];
	pagination?: {
		page: number;
		perPage: number;
		total: number;
		totalPages: number;
	};
}

export const reachRepo = {
	getBuilderForms: (headers?: AuthorizeRequestHeaders) => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: headers?.[Header.CORRELATION_ID] || generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			},
			params: {
				_: Date.now()
			}
		};

		return httpService.get<BuilderFormsResponse>(`${URL}/builder-forms`, config);
	},

	generateAuthUrl: (headers?: AuthorizeRequestHeaders) => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: headers?.[Header.CORRELATION_ID] || generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.post<{ authUrl: string; success: boolean }>(`${URL}/generate-auth-url`, {}, config);
	},

	postToken: (csrfField: string, token: string) => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.post<{ success: boolean }>(`${URL}/token`, { csrfField, token }, config);
	},

	postConnect: () => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.post<{ success: boolean }>(`${URL}/connect`, {}, config);
	},

	getConnectionSuccess: () => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.get<{ success: boolean }>(`${URL}/connection-success`, config);
	},

	deleteConnectionSuccess: () => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.delete<{ success: boolean }>(`${URL}/connection-success`, config);
	},

	getOverview: (headers?: AuthorizeRequestHeaders) => {
		const { nonce } = useGeneralDataStore();

		const config = {
			headers: {
				[Header.CORRELATION_ID]: headers?.[Header.CORRELATION_ID] || generateCorrelationId(),
				[Header.WP_NONCE]: nonce
			}
		};

		return httpService.get<OverviewData>(`${URL}/overview`, config);
	}
};
