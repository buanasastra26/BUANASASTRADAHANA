import { useState, useEffect } from '@wordpress/element';
import { Modal, Button, Spinner, SearchControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import './SelectFormModal.scss';

const EMBED_SCRIPT_URL = window.hostinger_reach_block_editor_data?.embed_script_url;

const previewDoc = (uuid) =>
	`<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;">` +
	`<div data-reach-form="${uuid}"></div>` +
	`<script src="${EMBED_SCRIPT_URL}" defer></scr` +
	`ipt></body></html>`;

const SelectFormModal = ({ forms, isLoading, previewImageUrl, initialSelectedId, createFormUrl, onRefresh, onClose, onContinue }) => {
	const [selectedId, setSelectedId] = useState(initialSelectedId || '');
	const [search, setSearch] = useState('');
	const [failedPreviews, setFailedPreviews] = useState({});

	const filteredForms = forms.filter((form) =>
		form.name.toLowerCase().includes(search.trim().toLowerCase())
	);

	useEffect(() => {
		if (selectedId && filteredForms.some((form) => form.uuid === selectedId)) {
			return;
		}

		if (initialSelectedId && filteredForms.some((form) => form.uuid === initialSelectedId)) {
			setSelectedId(initialSelectedId);
		} else {
			setSelectedId(filteredForms[0]?.uuid || '');
		}
	}, [search, forms, initialSelectedId]);

	const markPreviewFailed = (uuid) => setFailedPreviews((prev) => ({ ...prev, [uuid]: true }));

	const handleContinue = () => {
		if (!selectedId) {
			return;
		}

		onContinue(selectedId);
	};

	const emptyState = (
		<div className="hostinger-reach-select-form-modal__empty-state">
			<span className="hostinger-reach-select-form-modal__empty-icon" aria-hidden="true">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
					<path
						d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"
						stroke="#18181A"
						strokeWidth="1.5"
						strokeLinejoin="round"
					/>
					<path d="M14 3v5h5" stroke="#18181A" strokeWidth="1.5" strokeLinejoin="round" />
				</svg>
			</span>

			<div className="hostinger-reach-select-form-modal__empty-body">
				<div className="hostinger-reach-select-form-modal__empty-text">
					<h3 className="hostinger-reach-select-form-modal__empty-title">
						{__('No forms yet', 'hostinger-reach')}
					</h3>
					<p className="hostinger-reach-select-form-modal__empty-subtitle">
						{__(
							'Build your first form in Hostinger Reach and add it to any page on your site.',
							'hostinger-reach'
						)}
					</p>
				</div>

				{(createFormUrl || onRefresh) && (
					<div className="hostinger-reach-select-form-modal__empty-action">
						{createFormUrl && (
							<a
								className="hostinger-reach-select-form-modal__empty-create"
								href={createFormUrl}
								target="_blank"
								rel="noopener noreferrer"
							>
								<svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path d="M8 3.25v9.5M3.25 8h9.5" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" />
								</svg>
								<span>{__('Create a form in Reach', 'hostinger-reach')}</span>
							</a>
						)}

						{onRefresh && (
							<button
								type="button"
								className="hostinger-reach-select-form-modal__empty-refresh"
								onClick={onRefresh}
							>
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
									<path
										d="M4 12a8 8 0 0 1 13.657-5.657L20 8m0 0V3m0 5h-5M20 12a8 8 0 0 1-13.657 5.657L4 16m0 0v5m0-5h5"
										stroke="currentColor"
										strokeWidth="1.6"
										strokeLinecap="round"
										strokeLinejoin="round"
									/>
								</svg>
								<span>{__('Refresh forms', 'hostinger-reach')}</span>
							</button>
						)}
					</div>
				)}
			</div>
		</div>
	);

	return (
		<Modal
			title={__('Select a form', 'hostinger-reach')}
			onRequestClose={onClose}
			className="hostinger-reach-select-form-modal"
			size="large"
		>
			<div className="hostinger-reach-select-form-modal__content">
				{isLoading && (
					<div className="hostinger-reach-select-form-modal__loading">
						<Spinner />
					</div>
				)}

				{!isLoading && forms.length === 0 && (
					<div className="hostinger-reach-select-form-modal__empty">{emptyState}</div>
				)}

				{!isLoading && forms.length > 0 && (
					<div className="hostinger-reach-select-form-modal__list">
						<div className="hostinger-reach-select-form-modal__list-forms">
							<div className="hostinger-reach-select-form-modal__search">
								<SearchControl
									__nextHasNoMarginBottom
									value={search}
									onChange={setSearch}
									placeholder={__('Search a template', 'hostinger-reach')}
									label={__('Search a template', 'hostinger-reach')}
								/>
							</div>

							{filteredForms.length === 0 ? (
								<p className="hostinger-reach-select-form-modal__no-results">
									{__('No forms match your search.', 'hostinger-reach')}
								</p>
							) : (
								<div className="hostinger-reach-select-form-modal__forms-grid">
									{filteredForms.map((form) => (
										<button
											key={form.uuid}
											type="button"
											className={
												'hostinger-reach-select-form-modal__form-item' +
												(form.uuid === selectedId ? ' hostinger-reach-select-form-modal__form-item--selected' : '')
											}
											onClick={() => setSelectedId(form.uuid)}
										>
											<span className="hostinger-reach-select-form-modal__form-name">{form.name}</span>
											<div className="hostinger-reach-select-form-modal__form-thumb">
												{failedPreviews[form.uuid] ? (
													<span className="hostinger-reach-select-form-modal__form-thumb-fallback" />
												) : (
													<img
														className="hostinger-reach-select-form-modal__form-image"
														src={previewImageUrl(form.uuid)}
														alt={form.name}
														loading="lazy"
														onError={() => markPreviewFailed(form.uuid)}
													/>
												)}
											</div>
										</button>
									))}
								</div>
							)}
						</div>

						<div className="hostinger-reach-select-form-modal__list-preview">
							{selectedId && (
								<iframe
									key={selectedId}
									className="hostinger-reach-select-form-modal__preview-frame"
									srcDoc={previewDoc(selectedId)}
									title={__('Select a form', 'hostinger-reach')}
								/>
							)}
						</div>
					</div>
				)}
			</div>

			{(isLoading || forms.length > 0) && (
				<div className="hostinger-reach-select-form-modal__footer">
					<div className="hostinger-reach-select-form-modal__footer-start">
						{createFormUrl && (
							<Button
								variant="secondary"
								href={createFormUrl}
								target="_blank"
								rel="noopener noreferrer"
								className="hostinger-reach-select-form-modal__create"
							>
								{__('Create a new form', 'hostinger-reach')}
							</Button>
						)}

						{onRefresh && (
							<Button
								variant="secondary"
								disabled={isLoading}
								onClick={onRefresh}
								className="hostinger-reach-select-form-modal__refresh"
							>
								{__('Refresh', 'hostinger-reach')}
							</Button>
						)}
					</div>

					<div className="hostinger-reach-select-form-modal__footer-actions">
						<Button variant="tertiary" onClick={onClose}>
							{__('Cancel', 'hostinger-reach')}
						</Button>
						<Button variant="primary" disabled={!selectedId} onClick={handleContinue}>
							{__('Continue', 'hostinger-reach')}
						</Button>
					</div>
				</div>
			)}
		</Modal>
	);
};

export default SelectFormModal;
