/** Thin wrappers around apiFetch() for the EBQ REST proxy. */
import apiFetch from '@wordpress/api-fetch';

export async function fetchPostInsights(postId) {
	return apiFetch({ path: `/ebq/v1/post-insights/${postId}` });
}

export async function fetchFocusKeywordSuggestions(postId) {
	return apiFetch({ path: `/ebq/v1/focus-keyword-suggestions/${postId}` });
}

export async function fetchSerpPreview(postId, query) {
	return apiFetch({
		path: `/ebq/v1/serp-preview/${postId}?query=${encodeURIComponent(query)}`,
	});
}

export async function fetchDashboard() {
	return apiFetch({ path: `/ebq/v1/dashboard` });
}

export async function fetchInternalLinkSuggestions(postId) {
	return apiFetch({ path: `/ebq/v1/internal-link-suggestions/${postId}` });
}

export async function fetchRelatedKeywords(postId, keyword) {
	return apiFetch({
		path: `/ebq/v1/related-keywords/${postId}?keyword=${encodeURIComponent(keyword)}`,
	});
}
