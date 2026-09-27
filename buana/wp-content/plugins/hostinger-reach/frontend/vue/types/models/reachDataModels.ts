export interface ReachData {
	siteUrl: string;
	ajaxUrl: string;
	adminUrl: string;
	nonce: string;
	addBlockNonce: string;
	pluginUrl: string;
	translations: Record<string, string>;
	isConnected: boolean;
	totalFormPages: number;
	isHostingerUser: boolean;
	isStaging: boolean;
	domain: string;
	rawDomain: string;
	hasValidResourceId: boolean;
	resourceId: string;
}

export interface HstReachDataRaw {
	site_url: string;
	rest_base_url: string;
	ajax_url: string;
	admin_url: string;
	nonce: string;
	add_block_nonce: string;
	plugin_url: string;
	translations: Record<string, string>;
	is_connected: boolean;
	is_hostinger_user: boolean;
	is_staging: boolean;
	total_form_pages: number;
	domain: string;
	raw_domain: string;
	has_valid_resource_id: boolean;
	resource_id: string;
	is_elementor_active: boolean;
	elementor_new_page_url: string;
	embed_script_url: string;
}

export interface OverviewData {
	totalEmailsSentThisMonth: number;
	remainingEmailsQuota: number;
	totalCampaignsSentThisMonth: number;
	averageClickToOpenRate: number;
	totalSubscribed: number;
	totalSubscribedThisMonth: number;
	totalUnsubscribedThisMonth: number;
}
