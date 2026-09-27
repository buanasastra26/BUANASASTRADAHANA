export const AMPLITUDE_EVENT = {
	FORM_SUBMITTED: "wordpress.reach.form_submitted",
} as const;

export type AmplitudeEvent =
	(typeof AMPLITUDE_EVENT)[keyof typeof AMPLITUDE_EVENT];
