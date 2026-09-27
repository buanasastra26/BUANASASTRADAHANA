import type { HColor, HIconUnion } from "@hostinger/hcomponents";

export interface ContactList {
	id: number;
	name: string;
}

export interface Form {
	id?: number;
	formId: string;
	formTitle?: string;
	postId?: number;
	contactListId: number;
	type: string;
	isActive: boolean;
	isLoading?: boolean;
	submissions: number;
	post?: {
		ID: number;
		postAuthor: string;
		postDate: string;
		postDateGmt: string;
		postContent: string;
		postTitle: string;
		postExcerpt: string;
		postStatus: string;
		commentStatus: string;
		pingStatus: string;
		postPassword: string;
		postName: string;
		toPing: string;
		pinged: string;
		postModified: string;
		postModifiedGmt: string;
		postContentFiltered: string;
		postParent: number;
		guid: string;
		menuOrder: number;
		postType: string;
		postMimeType: string;
		commentCount: string;
		filter: string;
		ancestors: unknown[];
		pageTemplate: string;
		postCategory: number[];
		tagsInput: unknown[];
		id?: number;
		title?: string;
		url?: string;
	};
}

export interface FormsFilter {
	contactListId?: number;
	type?: string;
	limit?: number;
	offset?: number;
}

export const IMPORT_STATUSES = {
	OFF: "off",
	PARTIALLY_IMPORTED: "partially_imported",
	NOT_IMPORTED: "not_imported",
	IMPORTING: "importing",
	IMPORTED: "imported",
} as const;

export type ImportStatusType =
	(typeof IMPORT_STATUSES)[keyof typeof IMPORT_STATUSES];

export interface ImportSummaryItem {
	title: string;
	contacts: string;
	status: ImportStatusType;
}

export interface ImportSummary {
	[key: string]: ImportSummaryItem;
}

export interface ImportStatus {
	status: ImportStatusType;
	total: number;
	summary: ImportSummary | [];
}

export interface Integration {
	id: string;
	type: string;
	icon: string;
	isActive: boolean;
	title: string;
	url: string;
	adminUrl: string;
	addFormUrl: string;
	isPluginActive: boolean;
	canDeactivate: boolean;
	isGoToPluginVisible: boolean;
	isViewFormHidden: boolean;
	isEditFormHidden: boolean;
	canToggleForms: boolean;
	editUrl?: string;
	forms?: Form[];
	importEnabled: boolean;
	importStatus: ImportStatus;
	isInstallable: boolean;
}

export interface IntegrationsResponse {
	[key: string]: Integration;
}

export interface StatusIcon {
	icon: HIconUnion;
	color: HColor;
}
