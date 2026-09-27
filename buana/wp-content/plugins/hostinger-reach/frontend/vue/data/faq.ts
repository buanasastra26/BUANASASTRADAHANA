import { translate } from '@/utils/translate';

export interface FAQItem {
	id: string;
	question: string;
	answer: string;
}

export const connectFaqData: FAQItem[] = [
	{
		id: 'what-is-reach',
		question: translate('hostinger_reach_faq_what_is_reach_question'),
		answer: translate('hostinger_reach_faq_what_is_reach_answer')
	},
	{
		id: 'how-different',
		question: translate('hostinger_reach_faq_how_different_question'),
		answer: translate('hostinger_reach_faq_how_different_answer')
	},
	{
		id: 'how-much-cost',
		question: translate('hostinger_reach_faq_how_much_cost_question'),
		answer: translate('hostinger_reach_faq_how_much_cost_answer')
	},
	{
		id: 'what-is-plugin-difference',
		question: translate('hostinger_reach_faq_what_is_plugin_difference_question'),
		answer: translate('hostinger_reach_faq_what_is_plugin_difference_answer')
	}
];

export const overviewFaqData: FAQItem[] = [
	{
		id: 'how_sync_works',
		question: translate('hostinger_reach_faq_how_sync_works_question'),
		answer: translate('hostinger_reach_faq_how_sync_works_answer')
	},
	{
		id: 'contacts_auto_added',
		question: translate('hostinger_reach_faq_contacts_auto_added_question'),
		answer: translate('hostinger_reach_faq_contacts_auto_added_answer')
	},
	{
		id: 'how_segments_created',
		question: translate('hostinger_reach_faq_how_segments_created_question'),
		answer: translate('hostinger_reach_faq_how_segments_created_answer')
	},
	{
		id: 'whatif_connect_breaks',
		question: translate('hostinger_reach_faq_whatif_connect_breaks_question'),
		answer: translate('hostinger_reach_faq_whatif_connect_breaks_answer')
	}
];
