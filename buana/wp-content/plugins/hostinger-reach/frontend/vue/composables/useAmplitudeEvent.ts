import { amplitudeRepo } from '@/data/repositories';
import type { AmplitudeEvent } from '@/types/enums/amplitudeEnum';

export const useAmplitudeEvent = () => {
	const sendAmplitudeEvent = async (action: AmplitudeEvent, properties?: { [key: string]: unknown }) =>
		await amplitudeRepo.postHostingerAmplitude(action, properties);

	return {
		sendAmplitudeEvent
	};
};
