import {useState, useEffect} from "react";
import apiFetch from '@wordpress/api-fetch';
import {useSelect, select} from '@wordpress/data';

import ServerSideRender from "@wordpress/server-side-render";
import {useBlockProps, InspectorControls} from "@wordpress/block-editor";
import {
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
	CheckboxControl, Flex, FlexItem, Button,
} from "@wordpress/components";
import {__} from '@wordpress/i18n';
import Connect from "./Components/Connect";
import Dialog from "./Components/Dialog";
import SelectFormModal from "./Components/SelectFormModal";

const REST_URL = (window.hostinger_reach_block_editor_data?.rest_url || '/wp-json/');
const RESOURCE_ID = (window.hostinger_reach_block_editor_data?.resource_id || '');
const REACH_DOMAIN = (window.hostinger_reach_block_editor_data?.reach_domain || 'https://reach.hostinger.com/');
const DOMAIN = (window.hostinger_reach_block_editor_data?.domain || '');
const EMBED_SCRIPT_URL = window.hostinger_reach_block_editor_data?.embed_script_url;
const NONCE = (window.hostinger_reach_block_editor_data?.nonce || '');
const previewImageUrl = (uuid) =>
	`${REST_URL}hostinger-reach/v1/builder-form-preview/${uuid}?_wpnonce=${encodeURIComponent(NONCE)}`;
const formEditUrl = (formBuilderId) =>
	`${REACH_DOMAIN}?resourceId=${encodeURIComponent(RESOURCE_ID)}` +
	`&routeTo=form-publish` +
	`&routeId=${encodeURIComponent(formBuilderId)}`;
const reachCreateFormUrl = RESOURCE_ID
	? `${REACH_DOMAIN}?resourceId=${encodeURIComponent(RESOURCE_ID)}&domain=${encodeURIComponent(DOMAIN)}&routeTo=forms&action=create-form`
	: `${REACH_DOMAIN}?routeTo=forms&action=create-form`;

const PencilIcon = (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path fillRule="evenodd" clipRule="evenodd" d="M6.85667 14.1806C6.85667 13.7664 7.14459 13.4306 7.49976 13.4306H13.6067C13.9619 13.4306 14.2498 13.7664 14.2498 14.1806C14.2498 14.5949 13.9619 14.9306 13.6067 14.9306H7.49976C7.14459 14.9306 6.85667 14.5949 6.85667 14.1806Z" fill="#673DE6"/>
		<path fillRule="evenodd" clipRule="evenodd" d="M4.29059 12.6648L13.0584 3.80217C13.0832 3.77712 13.1066 3.75351 13.1286 3.73115C13.1063 3.70902 13.0827 3.68567 13.0577 3.66088L12.2096 2.82162C12.1845 2.79681 12.1609 2.77343 12.1385 2.75132C12.1163 2.77365 12.0929 2.79725 12.0681 2.8223L6.68571 8.25206L3.29933 11.6871C3.29304 11.6935 3.28714 11.6995 3.28159 11.7051C3.27938 11.7127 3.27703 11.7208 3.27453 11.7294L2.87773 13.0942L4.24774 12.6904C4.25651 12.6878 4.26473 12.6854 4.27247 12.6831C4.27814 12.6774 4.28416 12.6713 4.29059 12.6648ZM5.6195 7.19698L2.23021 10.635C2.12442 10.7423 2.07152 10.796 2.0277 10.8561C1.9888 10.9095 1.95529 10.9666 1.92766 11.0266C1.89654 11.0941 1.87551 11.1665 1.83345 11.3111L1.24282 13.3426C1.05669 13.9828 0.963621 14.3029 1.04553 14.5207C1.11691 14.7106 1.2672 14.8601 1.45749 14.9306C1.67583 15.0116 1.99583 14.9173 2.63581 14.7287L4.67228 14.1285C4.81967 14.0851 4.89337 14.0634 4.96204 14.0312C5.02302 14.0027 5.08096 13.9681 5.13498 13.9279C5.19581 13.8826 5.24982 13.828 5.35786 13.7188L14.1257 4.85619C14.5192 4.4584 14.716 4.25951 14.7889 4.03103C14.853 3.83004 14.8519 3.61394 14.7856 3.41365C14.7103 3.18595 14.5114 2.98916 14.1137 2.59558L13.2656 1.75632C12.8675 1.36239 12.6684 1.16542 12.4396 1.09237C12.2384 1.0281 12.022 1.02915 11.8213 1.09534C11.5933 1.1706 11.3961 1.36947 11.0019 1.76722L5.6195 7.19698ZM12.4706 2.43235L12.4686 2.43381ZM11.8053 2.43702L11.8033 2.43556ZM13.4476 4.06263L13.4461 4.06063ZM13.4426 3.39828L13.444 3.3963Z" fill="#673DE6"/>
	</svg>
);

const ReachIcon = (
	<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M13.9245 7.35306L18.0629 3.21429L20.7851 5.9365L18.5031 8.21843H15.8003L15.7818 8.20957L10.0742 5.67124V1.5H13.9245V7.35306Z" fill="#1D1E20"/>
		<path d="M5.49634 15.7812L8.21686 15.7893L13.9257 18.3284V22.4997H10.0754L10.0741 16.6488L5.93704 20.7854L3.21484 18.0632L5.49634 15.7812Z" fill="#1D1E20"/>
		<path d="M22.5008 10.0762H18.3287L15.7832 15.8002V18.5055L18.0643 20.7866L20.7865 18.064L16.6477 13.9256L22.5008 13.9248V10.0762Z" fill="#1D1E20"/>
		<path d="M5.9365 3.21484L8.21632 5.49466V8.20463L5.67208 13.9249H1.5V10.0763H7.35306L3.21429 5.93789L5.9365 3.21484Z" fill="#1D1E20"/>
	</svg>
);

const ArrowSquareIcon = (
	<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
		<path d="M2.5 5.24805C2.5 3.72926 3.73122 2.49805 5.25 2.49805H6C6.41421 2.49805 6.75 2.83383 6.75 3.24805C6.75 3.66226 6.41421 3.99805 6 3.99805H5.25C4.55964 3.99805 4 4.55769 4 5.24805V10.6861C4 11.3765 4.55964 11.9361 5.25 11.9361H10.7508C11.4411 11.9361 12.0008 11.3765 12.0008 10.6861V10.0002C12.0008 9.58603 12.3366 9.25024 12.7508 9.25024C13.165 9.25024 13.5008 9.58603 13.5008 10.0002V10.6861C13.5008 12.2049 12.2696 13.4361 10.7508 13.4361H5.25C3.73122 13.4361 2.5 12.2049 2.5 10.6861V5.24805Z" fill="#673DE6"/>
		<path d="M12 5.06101L8.03033 9.03068C7.73744 9.32357 7.26256 9.32357 6.96967 9.03068C6.67678 8.73779 6.67678 8.26291 6.96967 7.97002L10.9393 4.00035H9C8.58579 4.00035 8.25 3.66456 8.25 3.25035C8.25 2.83614 8.58579 2.50035 9 2.50035L12.25 2.50035C12.9404 2.50035 13.5 3.05999 13.5 3.75035V7.00035C13.5 7.41456 13.1642 7.75035 12.75 7.75035C12.3358 7.75035 12 7.41456 12 7.00035V5.06101Z" fill="#673DE6"/>
	</svg>
);

const Edit = ({attributes, setAttributes, clientId}) => {
	const {isPostPublished, postPermalink} = useSelect((select) => ({
		isPostPublished: select('core/editor').isCurrentPostPublished()
	}));
	const [newTagName, setNewTagName] = useState('');
	const blockProps = useBlockProps();
	const nonce = wp.data.select('core/editor').getEditorSettings().nonce || '';
	const [isConnected, setIsConnected] = useState(true);
	const [showAddNewTag, setShowAddNewTag] = useState(false);
	const [isLoading, setIsLoading] = useState(false);
	const [tags, setTags] = useState([]);
	const [forms, setForms] = useState([]);
	const [isFormsLoading, setIsFormsLoading] = useState(true);
	const [isFormModalOpen, setIsFormModalOpen] = useState(false);
	const [showDialog, setShowDialog] = useState(false);
	const [lastStatus, setLastStatus] = useState(null);

	const selectedForm = forms.find((form) => form.uuid === attributes.formBuilderId);
	const selectedFormName = selectedForm
		? selectedForm.name
		: isFormsLoading
			? __('Loading…', 'hostinger-reach')
			: attributes.formBuilderId;

	const layoutOptions = [
		{label: __('Default', 'hostinger-reach'), value: 'default'},
		{label: __('Inline', 'hostinger-reach'), value: 'inline'}
	];

	const isValidFormId = (id) => /^[a-zA-Z0-9-]+$/.test(id || '');
	const formBuilderPreviewDoc = isValidFormId(attributes.formBuilderId)
		? `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">`
			+ `<div data-reach-form="${attributes.formBuilderId}"></div>`
			+ `<script src="${EMBED_SCRIPT_URL}" defer></script>`
			+ `</body></html>`
		: `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;"></body></html>`;

	const handleSelectForm = (formBuilderId) => {
		setAttributes({formBuilderId});
		setIsFormModalOpen(false);
	};

	useEffect(() => {
		if (isPostPublished && lastStatus !== null && lastStatus !== 'publish') {
			setShowDialog(true);
		}
	}, [isPostPublished, lastStatus]);


	useEffect(() => {
		setLastPostStatus();
		fetchTags();
		fetchForms();
		checkConnection();
	}, []);


	useEffect(() => {
		if (attributes.formId) return;
		setAttributes({formId: clientId});
	}, [setAttributes]);

	const fetchTags = async () => {
		await getTags();
	};

	const fetchForms = async () => {
		try {
			setIsFormsLoading(true);
			const response = await apiFetch({
				path: `/hostinger-reach/v1/builder-forms?_=${Date.now()}`,
				method: 'GET',
				headers: {
					'X-WP-Nonce': nonce,
				},
				parse: false,
			});

			if (response.ok) {
				const responseData = await response.json();
				const resources = responseData?.data?.resources ?? [];
				setForms(
					resources
						.filter((resource) => resource.status === 'active')
						.map((resource) => ({
							uuid: resource.uuid,
							name: resource.name,
						}))
				);
			}
		} catch (err) {
			console.error(err);
		} finally {
			setIsFormsLoading(false);
		}
	};


	const checkConnection = async () => {
		try {
			const response = await apiFetch({
				path: '/hostinger-reach/v1/overview',
				method: 'GET',
				headers: {
					'X-WP-Nonce': nonce,
				},
				parse: false,
			});

			if (response.ok) {
				setIsConnected(true);
			} else {
				setIsConnected(false);
			}

		} catch (err) {
			setIsConnected(false);
		}
	}

	const setLastPostStatus = () => {
		const lastKnownStatus = select('core/editor').getEditedPostAttribute('status');
		setLastStatus(lastKnownStatus);
	}

	const getTags = async () => {
		try {
			const response = await apiFetch({
				path: '/hostinger-reach/v1/tags',
				method: 'GET',
				headers: {
					'X-WP-Nonce': nonce,
				},
				parse: false,
			});

			if (response.ok) {
				const responseData = await response.json();
				const tagNames = responseData.data.map(tag => tag.value);
				setTags(tagNames);
			}
		} catch (err) {
			console.error(err);
		}
	}

	const createTag = async ( tag ) => {
		try {
			setIsLoading(true);
			const response = await apiFetch({
				path: '/hostinger-reach/v1/tags',
				method: 'POST',
				headers: {
					'X-WP-Nonce': nonce,
				},
				data: {
					names: [tag]
				},
			});

			if (response && response.data) {
				const tagNames = response.data.map(tag => tag.value);
				setTags([...tagNames, ...tags]);
			}
		} catch ( err ) {
			console.error(err);
		} finally {
			setIsLoading(false);
		}
	}

	const isTagSelected = (tagName) => {
		const selectedTags = attributes.tags || [];
		return selectedTags.includes(tagName);
	}

	const handleTagToggle = (tagName) => {
		const selectedTags = attributes.tags || [];
		let newTags;
		if (isTagSelected(tagName)) {
			newTags = selectedTags.filter(tag => tag !== tagName);
		} else {
			newTags = [...selectedTags, tagName];
		}

		setAttributes({tags: newTags});
	};

	const addTag = async () => {
		const newTag = newTagName.trim();

		if (!newTag) {
			return;
		}

		const tagExists = tags.some(tag => tag === newTag);

		if (!tagExists) {
			await createTag( newTag );
		}

		if (!isTagSelected(newTag)) {
			handleTagToggle(newTag);
		}

		setNewTagName('');
	}

	const handleKeyPress = (event) => {
		if (event.key === 'Enter') {
			event.preventDefault();
			addTag();
		}
	};

	const toggleAddNewTag = () => {
		setShowAddNewTag( ! showAddNewTag );
	}

	return <div {...blockProps}>
		<InspectorControls key="hostinger-reach-block-controls">
			<PanelBody title={__("Settings", "hostinger-reach")}>
				{!isConnected && <Connect hideButton />}
				{attributes.formBuilderId ? (
					<div className="hostinger-reach-block-selected-form">
						<div className="hostinger-reach-block-selected-form__title">
							{ReachIcon}
							<span className="hostinger-reach-block-selected-form__name">
								{isConnected ? selectedFormName : __('You are not connected to Hostinger Reach', 'hostinger-reach')}
							</span>
						</div>
						{isConnected ? (
							<>
								<img
									className="hostinger-reach-block-selected-form__image"
									src={previewImageUrl(attributes.formBuilderId)}
									alt={selectedFormName}
								/>
								<Button
									variant="tertiary"
									className="hostinger-reach-block-selected-form__change"
									onClick={() => setIsFormModalOpen(true)}
								>
									{__('Change the selection', 'hostinger-reach')}
								</Button>
								{RESOURCE_ID && (
									<Button
										className="hostinger-reach-block-selected-form__edit"
										href={formEditUrl(attributes.formBuilderId)}
										target="_blank"
										rel="noopener noreferrer"
									>
										{PencilIcon}
										<span className="hostinger-reach-block-selected-form__edit-text">
											{__('Edit in Reach', 'hostinger-reach')}
										</span>
										{ArrowSquareIcon}
									</Button>
								)}
							</>
						) : (
							<Button
								variant="tertiary"
								className="hostinger-reach-block-selected-form__change"
								href="/wp-admin/admin.php?page=hostinger-reach"
								target="_blank"
								rel="noopener noreferrer"
							>
								{__('Connect to Reach', 'hostinger-reach')}
							</Button>
						)}
					</div>
				) : (
					isConnected ? (
						<Button
							variant="secondary"
							icon="plus"
							className="hostinger-reach-block-use-template"
							onClick={() => setIsFormModalOpen(true)}
						>
							{__('Use Form builder template', 'hostinger-reach')}
						</Button>
					) : (
						<Button
							variant="secondary"
							className="hostinger-reach-block-use-template"
							href="/wp-admin/admin.php?page=hostinger-reach"
							target="_blank"
							rel="noopener noreferrer"
						>
							{__('Connect to Reach', 'hostinger-reach')}
						</Button>
					)
				)}
				{attributes.formBuilderId && (
					<Button
						variant="secondary"
						className="hostinger-reach-block-classic-form"
						onClick={() => setAttributes({formBuilderId: ''})}
					>
						{__('Use classic Reach Form', 'hostinger-reach')}
					</Button>
				)}
				{!attributes.formBuilderId && (
					<>
				<div>
					<strong>{__('Tags', 'hostinger-reach')}</strong>
					{tags.length > 0 && (
						<div className="hostinger-reach-block-tags">
							{tags.map((tag) => {
								const selectedTags = attributes.tags || [];
								return (
									<CheckboxControl
										key={tag}
										label={tag}
										checked={selectedTags.includes(tag)}
										onChange={() => handleTagToggle(tag)}
									/>
								);
							})}
						</div>
					)}
				</div>
				<Button
					className="hostinger-reach-block-toggler"
					variant="link"
					onClick={toggleAddNewTag}
				>
					{__('Add New Tag', 'hostinger-reach')}
				</Button>
				{showAddNewTag && <Flex className="hostinger-reach-block-newtag" direction="column" align="flex-start" gap={1}>
					<FlexItem style={{flex: 1}}>
						<TextControl
							label={__('New Tag Name', 'hostinger-reach')}
							value={newTagName}
							onChange={(value) => {
								setNewTagName(value);
							}}
							onKeyDown={handleKeyPress}
							help={__('Enter tag name and press Enter or click Add Tag', 'hostinger-reach')}
						/>
					</FlexItem>
					<FlexItem>
						<Button
							variant="secondary"
							onClick={addTag}
							disabled={!newTagName.trim() || isLoading}
						>
							{__('Add Tag', 'hostinger-reach')}
						</Button>
					</FlexItem>
				</Flex> }
						<ToggleControl
							label={__("Show Name Field?", "hostinger-reach")}
							key="hostinger-reach-block-show-name-field"
							checked={attributes.showName}
							onChange={(value) =>
								setAttributes({showName: value})
							}
						/>
						<ToggleControl
							label={__("Show Surname Field?", "hostinger-reach")}
							key="hostinger-reach-block-show-surname-field"
							checked={attributes.showSurname}
							onChange={(value) =>
								setAttributes({showSurname: value})
							}
						/>
					</>
				)}
			</PanelBody>
			{!attributes.formBuilderId && (
				<PanelBody title={__('Layout Settings', 'hostinger-reach')}>
					<SelectControl
						label={__('Layout', 'hostinger-reach')}
						value={attributes.layout}
						options={layoutOptions}
						onChange={(value) => setAttributes({layout: value})}
					/>
				</PanelBody>
			)}

		</InspectorControls>
		{!isConnected && <Connect/>}
		{attributes.formBuilderId ? (
			<iframe
				title={__('Reach form preview', 'hostinger-reach')}
				srcDoc={formBuilderPreviewDoc}
				style={{width: '100%', minHeight: '420px', border: '0', pointerEvents: 'none'}}
			/>
		) : (
			<ServerSideRender
				key="hostinger-reach-server-side-renderer"
				block="hostinger-reach/subscription"
				attributes={attributes}
			/>
		)}
		{showDialog && <Dialog onClose={() => setShowDialog(false)}/>}
		{isFormModalOpen && (
			<SelectFormModal
				forms={forms}
				isLoading={isFormsLoading}
				previewImageUrl={previewImageUrl}
				initialSelectedId={attributes.formBuilderId}
				createFormUrl={reachCreateFormUrl}
				onRefresh={fetchForms}
				onClose={() => setIsFormModalOpen(false)}
				onContinue={handleSelectForm}
			/>
		)}
	</div>

}

export default Edit;
