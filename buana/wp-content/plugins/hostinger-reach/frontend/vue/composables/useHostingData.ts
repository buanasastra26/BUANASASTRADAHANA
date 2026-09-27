import { ref } from 'vue';

import { hostingRepo } from '@/data/repositories/hostingRepo';
import type { DomainDetails } from '@/types/models/hostingDataModels';
import { translate } from '@/utils/translate';

export const useHostingData = () => {
	const isLoading = ref(false);
	const error = ref<string | null>(null);
	const domainDetails = ref<DomainDetails | null>(null);

	const loadDomainDetails = async () => {
		isLoading.value = true;
		error.value = null;

		const [data, err] = await hostingRepo.getDomainDetails();

		isLoading.value = false;

		if (err) {
			error.value = err.message || translate('hostinger_reach_error_message');

			return;
		}

		if (data) {
			domainDetails.value = data;
		}
	};

	return {
		isLoading,
		error,
		domainDetails,
		loadDomainDetails
	};
};
