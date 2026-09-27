export interface DomainDetails {
	isPointing: boolean;
	isHostingerNs: boolean;
	isFreeSubdomain: boolean;
	isInPropagation: boolean;
	isAvailable: boolean;
	isExternal: boolean;
	isTldSupported: boolean;
	isTransferring: boolean;
	isGrace: boolean;
	isRedemption: boolean;
	status: string;
	createdAt: string;
	expiresAt: string;
	addedAt: string;
	nameservers: string[];
	phoneVerificationRequired: boolean;
	emailVerificationStatus: string;
}
