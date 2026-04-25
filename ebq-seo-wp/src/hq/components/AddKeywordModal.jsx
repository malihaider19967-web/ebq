import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Modal from './Modal';
import { Button } from './primitives';
import { Api } from '../api';

/**
 * Full-options "Add tracked keyword" form. Mirrors the Livewire RankTracking
 * Manager form so anything you can configure on EBQ.io you can configure
 * from inside WordPress.
 *
 * The form is split into "Essentials" (always shown) and "Advanced" (toggle)
 * to keep the casual-user path one click long while still exposing depth /
 * tbs / device / autocorrect / safe-search / competitors / tags / notes /
 * check interval. Same field set EBQ_v1_HqController::storeKeyword() validates.
 */
export default function AddKeywordModal({ open, onClose, onCreated, defaultDomain, seedKeyword = '' }) {
	const [form, setForm] = useState(initialForm(defaultDomain));
	const [submitting, setSubmitting] = useState(false);
	const [error, setError] = useState(null);
	const [showAdvanced, setShowAdvanced] = useState(false);
	const [candidates, setCandidates] = useState({ loading: false, data: [] });

	useEffect(() => {
		if (!open) return;
		const next = initialForm(defaultDomain);
		if (seedKeyword) next.keyword = seedKeyword;
		setForm(next);
		setError(null);
		setShowAdvanced(false);
		// Pre-load GSC candidates so the user can promote them with one click.
		// Skip when we already have a seed (the user picked one — don't crowd
		// the modal with more suggestions).
		if (!seedKeyword) {
			setCandidates({ loading: true, data: [] });
			Api.keywordCandidates(15).then((res) => {
				setCandidates({ loading: false, data: res?.data || [] });
			});
		} else {
			setCandidates({ loading: false, data: [] });
		}
	}, [open, defaultDomain, seedKeyword]);

	const set = (key, value) => setForm((prev) => ({ ...prev, [key]: value }));

	const submit = useCallback(async () => {
		if (!form.keyword.trim()) {
			setError({ message: __('Keyword is required.', 'ebq-seo') });
			return;
		}
		setSubmitting(true);
		setError(null);
		const payload = buildPayload(form);
		const res = await Api.createKeyword(payload);
		setSubmitting(false);
		if (res?.ok === false || res?.error) {
			setError(res);
			return;
		}
		onCreated?.(res?.keyword);
		onClose?.();
	}, [form, onClose, onCreated]);

	const promoteCandidate = (kw) => {
		set('keyword', kw);
		// Scroll the keyword input into view so the user sees the promotion.
		setTimeout(() => document.querySelector('.ebq-hq-form input[name="keyword"]')?.focus(), 0);
	};

	const footer = (
		<div className="ebq-hq-modal__foot-actions">
			<Button variant="ghost" onClick={onClose} disabled={submitting}>{__('Cancel', 'ebq-seo')}</Button>
			<Button variant="primary" onClick={submit} disabled={submitting || !form.keyword.trim()}>
				{submitting ? __('Saving…', 'ebq-seo') : __('Add keyword', 'ebq-seo')}
			</Button>
		</div>
	);

	return (
		<Modal open={open} onClose={onClose} title={__('Track a new keyword', 'ebq-seo')} size="lg" footer={footer}>
			<div className="ebq-hq-form">
				{error ? (
					<div className="ebq-hq-form-error">
						<strong>{__('Could not add keyword', 'ebq-seo')}</strong>
						<span>{error.message || error.error || __('Unknown error.', 'ebq-seo')}</span>
					</div>
				) : null}

				<div className="ebq-hq-form-grid">
					<Field label={__('Keyword', 'ebq-seo')} required help={__('What people type into Google. One keyword per row.', 'ebq-seo')}>
						<input name="keyword" type="text" value={form.keyword} onChange={(e) => set('keyword', e.target.value)} autoFocus />
					</Field>
					<Field label={__('Target domain', 'ebq-seo')} help={__('We score positions against this domain. Defaults to the site you connected.', 'ebq-seo')}>
						<input type="text" value={form.target_domain} onChange={(e) => set('target_domain', e.target.value)} />
					</Field>
					<Field label={__('Target URL (optional)', 'ebq-seo')} help={__('If set, we flag when a different URL is the one ranking.', 'ebq-seo')}>
						<input type="url" value={form.target_url} onChange={(e) => set('target_url', e.target.value)} placeholder="https://" />
					</Field>
					<Field label={__('Country', 'ebq-seo')}>
						<select value={form.country} onChange={(e) => set('country', e.target.value)}>
							{COUNTRIES.map((c) => <option key={c.code} value={c.code}>{c.label}</option>)}
						</select>
					</Field>
					<Field label={__('Language', 'ebq-seo')}>
						<select value={form.language} onChange={(e) => set('language', e.target.value)}>
							{LANGUAGES.map((l) => <option key={l.code} value={l.code}>{l.label}</option>)}
						</select>
					</Field>
					<Field label={__('Device', 'ebq-seo')}>
						<select value={form.device} onChange={(e) => set('device', e.target.value)}>
							<option value="desktop">{__('Desktop', 'ebq-seo')}</option>
							<option value="mobile">{__('Mobile', 'ebq-seo')}</option>
						</select>
					</Field>
				</div>

				<button type="button" className="ebq-hq-form-toggle" onClick={() => setShowAdvanced((v) => !v)}>
					{showAdvanced ? '▼' : '▶'} {__('Advanced options', 'ebq-seo')}
				</button>

				{showAdvanced ? (
					<div className="ebq-hq-form-grid">
						<Field label={__('Search type', 'ebq-seo')}>
							<select value={form.search_type} onChange={(e) => set('search_type', e.target.value)}>
								<option value="organic">{__('Organic', 'ebq-seo')}</option>
								<option value="news">{__('News', 'ebq-seo')}</option>
								<option value="images">{__('Images', 'ebq-seo')}</option>
								<option value="videos">{__('Videos', 'ebq-seo')}</option>
								<option value="shopping">{__('Shopping', 'ebq-seo')}</option>
								<option value="maps">{__('Maps', 'ebq-seo')}</option>
								<option value="scholar">{__('Scholar', 'ebq-seo')}</option>
							</select>
						</Field>
						<Field label={__('SERP depth', 'ebq-seo')} help={__('How many results to inspect per check (10–100).', 'ebq-seo')}>
							<input type="number" min="10" max="100" value={form.depth} onChange={(e) => set('depth', e.target.value)} />
						</Field>
						<Field label={__('Check interval (hours)', 'ebq-seo')} help={__('1–168. Daily is the sweet spot.', 'ebq-seo')}>
							<input type="number" min="1" max="168" value={form.check_interval_hours} onChange={(e) => set('check_interval_hours', e.target.value)} />
						</Field>
						<Field label={__('Location (optional)', 'ebq-seo')} help={__('e.g. "New York, NY" — narrows local rankings.', 'ebq-seo')}>
							<input type="text" value={form.location} onChange={(e) => set('location', e.target.value)} />
						</Field>
						<Field label={__('TBS (optional)', 'ebq-seo')} help={__('Google "tbs" parameter — power users only.', 'ebq-seo')}>
							<input type="text" value={form.tbs} onChange={(e) => set('tbs', e.target.value)} />
						</Field>
						<Field label={__('Tags', 'ebq-seo')} help={__('Comma-separated. Useful for grouping campaigns.', 'ebq-seo')}>
							<input type="text" value={form.tags} onChange={(e) => set('tags', e.target.value)} placeholder="brand, top-funnel" />
						</Field>
						<Field label={__('Competitors', 'ebq-seo')} help={__('Domains to track alongside yours. Comma-separated.', 'ebq-seo')} grow>
							<input type="text" value={form.competitors} onChange={(e) => set('competitors', e.target.value)} placeholder="competitor1.com, competitor2.com" />
						</Field>
						<Field label={__('Notes', 'ebq-seo')} grow>
							<textarea rows="2" value={form.notes} onChange={(e) => set('notes', e.target.value)} />
						</Field>

						<div className="ebq-hq-form-row ebq-hq-form-row--toggles">
							<label className="ebq-hq-form-check">
								<input type="checkbox" checked={form.autocorrect} onChange={(e) => set('autocorrect', e.target.checked)} />
								{__('Allow autocorrect', 'ebq-seo')}
							</label>
							<label className="ebq-hq-form-check">
								<input type="checkbox" checked={form.safe_search} onChange={(e) => set('safe_search', e.target.checked)} />
								{__('Safe search on', 'ebq-seo')}
							</label>
						</div>
					</div>
				) : null}

				{candidates.data?.length > 0 ? (
					<div className="ebq-hq-candidates">
						<div className="ebq-hq-candidates__head">
							<strong>{__('Suggestions from your Search Console', 'ebq-seo')}</strong>
							<span>{__('queries with traffic that you don\'t track yet', 'ebq-seo')}</span>
						</div>
						<div className="ebq-hq-candidates__list">
							{candidates.data.map((c, i) => (
								<button key={i} type="button" className="ebq-hq-candidate" onClick={() => promoteCandidate(c.keyword)}>
									<span className="ebq-hq-candidate__kw">{c.keyword}</span>
									<span className="ebq-hq-candidate__meta">
										{c.impressions.toLocaleString()} impr · pos {c.position?.toFixed?.(0) || '—'}
									</span>
									<span className="ebq-hq-candidate__cta">+ {__('Track', 'ebq-seo')}</span>
								</button>
							))}
						</div>
					</div>
				) : candidates.loading ? (
					<p className="ebq-hq-help">{__('Loading suggestions…', 'ebq-seo')}</p>
				) : null}
			</div>
		</Modal>
	);
}

function Field({ label, help, required, children, grow }) {
	return (
		<div className={`ebq-hq-form-field${grow ? ' ebq-hq-form-field--grow' : ''}`}>
			<label className="ebq-hq-form-label">
				{label}
				{required ? <span className="ebq-hq-form-required"> *</span> : null}
			</label>
			{children}
			{help ? <p className="ebq-hq-form-help">{help}</p> : null}
		</div>
	);
}

function initialForm(defaultDomain) {
	return {
		keyword: '',
		target_domain: defaultDomain || '',
		target_url: '',
		country: 'us',
		language: 'en',
		device: 'desktop',
		search_type: 'organic',
		depth: 100,
		check_interval_hours: 24,
		location: '',
		tbs: '',
		tags: '',
		competitors: '',
		notes: '',
		autocorrect: false,
		safe_search: false,
	};
}

function buildPayload(form) {
	const splitCsv = (s) => String(s || '').split(',').map((v) => v.trim()).filter(Boolean);
	return {
		keyword: form.keyword.trim(),
		target_domain: form.target_domain.trim() || undefined,
		target_url: form.target_url.trim() || undefined,
		country: form.country,
		language: form.language,
		device: form.device,
		search_type: form.search_type,
		depth: Number(form.depth) || 100,
		check_interval_hours: Number(form.check_interval_hours) || 24,
		location: form.location.trim() || undefined,
		tbs: form.tbs.trim() || undefined,
		tags: splitCsv(form.tags),
		competitors: splitCsv(form.competitors),
		notes: form.notes.trim() || undefined,
		autocorrect: !!form.autocorrect,
		safe_search: !!form.safe_search,
	};
}

// Compact common-country list — wider list available via free-text fallback
// later if anyone asks. ISO-3166 alpha-2.
const COUNTRIES = [
	{ code: 'us', label: 'United States' },
	{ code: 'gb', label: 'United Kingdom' },
	{ code: 'in', label: 'India' },
	{ code: 'ca', label: 'Canada' },
	{ code: 'au', label: 'Australia' },
	{ code: 'de', label: 'Germany' },
	{ code: 'fr', label: 'France' },
	{ code: 'es', label: 'Spain' },
	{ code: 'it', label: 'Italy' },
	{ code: 'nl', label: 'Netherlands' },
	{ code: 'br', label: 'Brazil' },
	{ code: 'mx', label: 'Mexico' },
	{ code: 'jp', label: 'Japan' },
	{ code: 'kr', label: 'South Korea' },
	{ code: 'sg', label: 'Singapore' },
	{ code: 'ae', label: 'United Arab Emirates' },
	{ code: 'sa', label: 'Saudi Arabia' },
	{ code: 'pk', label: 'Pakistan' },
	{ code: 'bd', label: 'Bangladesh' },
	{ code: 'id', label: 'Indonesia' },
	{ code: 'tr', label: 'Turkey' },
	{ code: 'ph', label: 'Philippines' },
	{ code: 'vn', label: 'Vietnam' },
	{ code: 'th', label: 'Thailand' },
	{ code: 'eg', label: 'Egypt' },
	{ code: 'za', label: 'South Africa' },
	{ code: 'ng', label: 'Nigeria' },
	{ code: 'pl', label: 'Poland' },
	{ code: 'se', label: 'Sweden' },
	{ code: 'no', label: 'Norway' },
];

const LANGUAGES = [
	{ code: 'en', label: 'English' },
	{ code: 'es', label: 'Spanish' },
	{ code: 'fr', label: 'French' },
	{ code: 'de', label: 'German' },
	{ code: 'it', label: 'Italian' },
	{ code: 'pt', label: 'Portuguese' },
	{ code: 'nl', label: 'Dutch' },
	{ code: 'ru', label: 'Russian' },
	{ code: 'ja', label: 'Japanese' },
	{ code: 'ko', label: 'Korean' },
	{ code: 'zh', label: 'Chinese' },
	{ code: 'ar', label: 'Arabic' },
	{ code: 'hi', label: 'Hindi' },
	{ code: 'ur', label: 'Urdu' },
	{ code: 'tr', label: 'Turkish' },
	{ code: 'pl', label: 'Polish' },
	{ code: 'sv', label: 'Swedish' },
	{ code: 'no', label: 'Norwegian' },
	{ code: 'da', label: 'Danish' },
	{ code: 'fi', label: 'Finnish' },
	{ code: 'th', label: 'Thai' },
	{ code: 'vi', label: 'Vietnamese' },
	{ code: 'id', label: 'Indonesian' },
];
