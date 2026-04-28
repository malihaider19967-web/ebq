/**
 * Catalogue of schema templates exposed to the editor UI. Field defs here
 * mirror the PHP-side EBQ_Schema_Templates so the form can render the right
 * controls and the JSON the user composes round-trips through `_ebq_schemas`.
 *
 * Field types understood by SchemaForm.jsx:
 *   text       single-line input (supports %vars% via the helper hint)
 *   textarea   multi-line input
 *   url        URL input
 *   number     numeric input
 *   date       <input type="date">
 *   datetime   <input type="datetime-local">
 *   select     dropdown (uses options)
 *   repeater   repeating list — `subfields` describes each row
 *
 * `default` may be a literal or a %variable% string — it's used as the field
 * value the first time the user adds the schema, and only when blank.
 */

export const VARIABLES = [
	{ token: '%title%',          label: 'Post title' },
	{ token: '%excerpt%',        label: 'Post excerpt' },
	{ token: '%url%',            label: 'Post URL' },
	{ token: '%featured_image%', label: 'Featured image URL' },
	{ token: '%author%',         label: 'Author name' },
	{ token: '%date%',           label: 'Publish date' },
	{ token: '%modified%',       label: 'Modified date' },
	{ token: '%sitename%',       label: 'Site name' },
];

const TEMPLATES = {
	article: {
		id: 'article',
		type: 'Article',
		label: 'Article',
		group: 'Content',
		description: 'Default schema for blog posts and editorial articles. Switch the subtype to BlogPosting or NewsArticle when relevant.',
		subtypes: ['Article', 'BlogPosting', 'NewsArticle'],
		fields: [
			{ key: 'headline',      label: 'Headline',       type: 'text',     default: '%title%',          helper: 'Defaults to the post title. Keep under 110 characters.' },
			{ key: 'description',   label: 'Description',    type: 'textarea', default: '%excerpt%' },
			{ key: 'image',         label: 'Image URL',      type: 'url',      default: '%featured_image%', helper: 'Absolute URL. Defaults to the featured image.' },
			{ key: 'datePublished', label: 'Date published', type: 'datetime', default: '%date%' },
			{ key: 'dateModified',  label: 'Date modified',  type: 'datetime', default: '%modified%' },
			{ key: 'authorName',    label: 'Author name',    type: 'text',     default: '%author%' },
		],
	},

	product: {
		id: 'product',
		type: 'Product',
		label: 'Product',
		group: 'Commerce',
		description: 'Product card with optional offer + aggregate review rating.',
		fields: [
			{ key: 'name',         label: 'Name',           type: 'text',     default: '%title%' },
			{ key: 'description',  label: 'Description',    type: 'textarea', default: '%excerpt%' },
			{ key: 'image',        label: 'Image URL',      type: 'url',      default: '%featured_image%' },
			{ key: 'sku',          label: 'SKU',            type: 'text' },
			{ key: 'brand',        label: 'Brand',          type: 'text' },
			{ key: 'price',        label: 'Price',          type: 'number',   helper: 'Numeric only — e.g. 29.99' },
			{ key: 'currency',     label: 'Currency',       type: 'text',     default: 'USD',  helper: 'ISO 4217 — USD, EUR, GBP, INR, etc.' },
			{ key: 'availability', label: 'Availability',   type: 'select',   options: ['InStock', 'OutOfStock', 'PreOrder', 'Discontinued', 'LimitedAvailability', 'SoldOut'] },
			{ key: 'reviewRating', label: 'Average rating', type: 'number',   helper: '1–5. Pair with review count to emit AggregateRating.' },
			{ key: 'reviewCount',  label: 'Review count',   type: 'number' },
		],
	},

	event: {
		id: 'event',
		type: 'Event',
		label: 'Event',
		group: 'Schedule',
		description: 'Single event listing — concert, webinar, conference. Required for Event rich results: name, startDate, location.',
		fields: [
			{ key: 'name',                label: 'Event name',          type: 'text',     default: '%title%' },
			{ key: 'description',         label: 'Description',         type: 'textarea', default: '%excerpt%' },
			{ key: 'image',               label: 'Image URL',           type: 'url',      default: '%featured_image%' },
			{ key: 'startDate',           label: 'Start date / time',   type: 'datetime' },
			{ key: 'endDate',             label: 'End date / time',     type: 'datetime' },
			{ key: 'eventStatus',         label: 'Status',              type: 'select',   options: ['EventScheduled', 'EventCancelled', 'EventPostponed', 'EventRescheduled', 'EventMovedOnline'] },
			{ key: 'eventAttendanceMode', label: 'Attendance mode',     type: 'select',   options: ['OfflineEventAttendanceMode', 'OnlineEventAttendanceMode', 'MixedEventAttendanceMode'] },
			{ key: 'locationName',        label: 'Location name',       type: 'text' },
			{ key: 'locationAddress',     label: 'Location address',    type: 'text',     helper: 'Free-form — e.g. "350 5th Ave, New York, NY"' },
			{ key: 'performer',           label: 'Performer',           type: 'text' },
			{ key: 'organizerName',       label: 'Organizer',           type: 'text' },
			{ key: 'offerPrice',          label: 'Ticket price',        type: 'number' },
			{ key: 'offerCurrency',       label: 'Ticket currency',     type: 'text',     default: 'USD' },
			{ key: 'offerUrl',            label: 'Ticket URL',          type: 'url' },
		],
	},

	faq: {
		id: 'faq',
		type: 'FAQPage',
		label: 'FAQ',
		group: 'Content',
		description: 'Question / answer pairs. Use this to mark up a static FAQ section on the page.',
		fields: [
			{
				key: 'questions',
				label: 'Questions',
				type: 'repeater',
				addLabel: 'Add question',
				subfields: [
					{ key: 'question', label: 'Question', type: 'text' },
					{ key: 'answer',   label: 'Answer',   type: 'textarea' },
				],
			},
		],
	},

	recipe: {
		id: 'recipe',
		type: 'Recipe',
		label: 'Recipe',
		group: 'Content',
		description: 'Cooking recipe with ingredients and steps. Times use ISO-8601 durations (PT15M = 15 min, PT1H30M = 1h30m).',
		fields: [
			{ key: 'name',           label: 'Recipe name',     type: 'text',     default: '%title%' },
			{ key: 'description',    label: 'Description',     type: 'textarea', default: '%excerpt%' },
			{ key: 'image',          label: 'Image URL',       type: 'url',      default: '%featured_image%' },
			{ key: 'prepTime',       label: 'Prep time',       type: 'text',     helper: 'ISO-8601 — e.g. PT15M' },
			{ key: 'cookTime',       label: 'Cook time',       type: 'text',     helper: 'ISO-8601 — e.g. PT45M' },
			{ key: 'totalTime',      label: 'Total time',      type: 'text',     helper: 'ISO-8601 — e.g. PT1H' },
			{ key: 'recipeYield',    label: 'Yield',           type: 'text',     helper: 'e.g. "4 servings"' },
			{ key: 'recipeCategory', label: 'Category',        type: 'text',     helper: 'e.g. Dinner, Dessert' },
			{ key: 'recipeCuisine',  label: 'Cuisine',         type: 'text',     helper: 'e.g. Italian' },
			{
				key: 'ingredients',
				label: 'Ingredients',
				type: 'repeater',
				addLabel: 'Add ingredient',
				subfields: [{ key: 'value', label: 'Ingredient', type: 'text' }],
			},
			{
				key: 'instructions',
				label: 'Instructions',
				type: 'repeater',
				addLabel: 'Add step',
				subfields: [{ key: 'value', label: 'Step', type: 'textarea' }],
			},
			{ key: 'calories', label: 'Calories per serving', type: 'number' },
		],
	},

	local_business: {
		id: 'local_business',
		type: 'LocalBusiness',
		label: 'Local Business',
		group: 'Business',
		description: 'Brick-and-mortar listing. Switch the subtype to Restaurant / Store / etc. for tighter relevance.',
		subtypes: ['LocalBusiness', 'Restaurant', 'Store', 'ProfessionalService', 'MedicalBusiness'],
		fields: [
			{ key: 'name',            label: 'Business name',      type: 'text',     default: '%sitename%' },
			{ key: 'description',     label: 'Description',        type: 'textarea' },
			{ key: 'image',           label: 'Image URL',          type: 'url' },
			{ key: 'telephone',       label: 'Phone',              type: 'text' },
			{ key: 'priceRange',      label: 'Price range',        type: 'text',     helper: 'e.g. "$" through "$$$$"' },
			{ key: 'streetAddress',   label: 'Street address',     type: 'text' },
			{ key: 'addressLocality', label: 'City',               type: 'text' },
			{ key: 'addressRegion',   label: 'State / region',     type: 'text' },
			{ key: 'postalCode',      label: 'Postal code',        type: 'text' },
			{ key: 'addressCountry',  label: 'Country code',       type: 'text',     helper: 'ISO 3166-1 alpha-2 — US, GB, IN, …' },
			{ key: 'latitude',        label: 'Latitude',           type: 'number' },
			{ key: 'longitude',       label: 'Longitude',          type: 'number' },
			{
				key: 'openingHours',
				label: 'Opening hours',
				type: 'repeater',
				addLabel: 'Add line',
				subfields: [{ key: 'value', label: 'Hours', type: 'text', helper: 'e.g. Mo-Fr 09:00-17:00' }],
			},
		],
	},

	book: {
		id: 'book', type: 'Book', label: 'Book', group: 'Creative work',
		description: 'Book listing. Pair with ISBN for richer results.',
		fields: [
			{ key: 'name',          label: 'Title',         type: 'text',     default: '%title%' },
			{ key: 'author',        label: 'Author',        type: 'text' },
			{ key: 'isbn',          label: 'ISBN',          type: 'text' },
			{ key: 'bookFormat',    label: 'Format',        type: 'select', options: ['Hardcover', 'Paperback', 'EBook', 'AudiobookFormat'] },
			{ key: 'numberOfPages', label: 'Number of pages', type: 'number' },
			{ key: 'datePublished', label: 'Date published', type: 'date' },
			{ key: 'publisher',     label: 'Publisher',     type: 'text' },
			{ key: 'inLanguage',    label: 'Language',      type: 'text', helper: 'BCP-47 code, e.g. en, fr-CA' },
			{ key: 'image',         label: 'Cover image',   type: 'url',  default: '%featured_image%' },
			{ key: 'description',   label: 'Description',   type: 'textarea', default: '%excerpt%' },
		],
	},

	course: {
		id: 'course', type: 'Course', label: 'Course', group: 'Education',
		description: 'Educational course (online class, training program).',
		fields: [
			{ key: 'name',             label: 'Course name',     type: 'text',     default: '%title%' },
			{ key: 'description',      label: 'Description',     type: 'textarea', default: '%excerpt%' },
			{ key: 'providerName',     label: 'Provider',        type: 'text' },
			{ key: 'providerUrl',      label: 'Provider URL',    type: 'url' },
			{ key: 'courseCode',       label: 'Course code',     type: 'text' },
			{ key: 'educationalLevel', label: 'Level',           type: 'text', helper: 'Beginner, Intermediate, Advanced' },
			{ key: 'timeRequired',     label: 'Time required',   type: 'text', helper: 'ISO-8601 — e.g. PT10H' },
			{ key: 'inLanguage',       label: 'Language',        type: 'text' },
		],
	},

	job_posting: {
		id: 'job_posting', type: 'JobPosting', label: 'Job posting', group: 'Business',
		description: 'Job listing with hiring organization, location, and salary.',
		fields: [
			{ key: 'title',              label: 'Job title',          type: 'text',     default: '%title%' },
			{ key: 'description',        label: 'Description',        type: 'textarea', default: '%excerpt%' },
			{ key: 'datePosted',         label: 'Date posted',        type: 'date',     default: '%date%' },
			{ key: 'validThrough',       label: 'Valid through',      type: 'date' },
			{ key: 'employmentType',     label: 'Employment type',    type: 'select',   options: ['FULL_TIME', 'PART_TIME', 'CONTRACTOR', 'TEMPORARY', 'INTERN', 'VOLUNTEER', 'PER_DIEM', 'OTHER'] },
			{ key: 'hiringOrgName',      label: 'Hiring organization', type: 'text',    default: '%sitename%' },
			{ key: 'hiringOrgUrl',       label: 'Org URL',            type: 'url' },
			{ key: 'hiringOrgLogo',      label: 'Org logo URL',       type: 'url' },
			{ key: 'locationStreet',     label: 'Street address',     type: 'text' },
			{ key: 'locationLocality',   label: 'City',               type: 'text' },
			{ key: 'locationRegion',     label: 'State / region',     type: 'text' },
			{ key: 'locationPostalCode', label: 'Postal code',        type: 'text' },
			{ key: 'locationCountry',    label: 'Country',            type: 'text', helper: 'ISO 3166-1 alpha-2' },
			{ key: 'salaryMin',          label: 'Salary min',         type: 'number' },
			{ key: 'salaryMax',          label: 'Salary max',         type: 'number' },
			{ key: 'salaryCurrency',     label: 'Salary currency',    type: 'text', default: 'USD' },
			{ key: 'salaryUnit',         label: 'Salary unit',        type: 'select', options: ['HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR'] },
		],
	},

	video: {
		id: 'video', type: 'VideoObject', label: 'Video', group: 'Creative work',
		description: 'Standalone video object — Google needs name, description, thumbnailUrl, uploadDate.',
		fields: [
			{ key: 'name',         label: 'Title',         type: 'text',     default: '%title%' },
			{ key: 'description',  label: 'Description',   type: 'textarea', default: '%excerpt%' },
			{ key: 'thumbnailUrl', label: 'Thumbnail URL', type: 'url',      default: '%featured_image%' },
			{ key: 'contentUrl',   label: 'Content URL',   type: 'url',      helper: 'Direct video file URL' },
			{ key: 'embedUrl',     label: 'Embed URL',     type: 'url',      helper: 'YouTube, Vimeo, etc.' },
			{ key: 'uploadDate',   label: 'Upload date',   type: 'datetime', default: '%date%' },
			{ key: 'duration',     label: 'Duration',      type: 'text',     helper: 'ISO-8601 — e.g. PT5M30S' },
		],
	},

	software: {
		id: 'software', type: 'SoftwareApplication', label: 'Software', group: 'Commerce',
		description: 'Application or web app listing with pricing and ratings.',
		fields: [
			{ key: 'name',                label: 'Name',                type: 'text',     default: '%title%' },
			{ key: 'description',         label: 'Description',         type: 'textarea', default: '%excerpt%' },
			{ key: 'operatingSystem',     label: 'Operating system',    type: 'text',     helper: 'e.g. Windows, macOS, iOS, Android' },
			{ key: 'applicationCategory', label: 'Application category', type: 'text',    helper: 'e.g. BusinessApplication, GameApplication' },
			{ key: 'softwareVersion',     label: 'Version',             type: 'text' },
			{ key: 'downloadUrl',         label: 'Download URL',        type: 'url' },
			{ key: 'image',               label: 'Image URL',           type: 'url',      default: '%featured_image%' },
			{ key: 'price',               label: 'Price',               type: 'number' },
			{ key: 'currency',            label: 'Currency',            type: 'text',     default: 'USD' },
			{ key: 'ratingValue',         label: 'Average rating',      type: 'number',   helper: '1–5' },
			{ key: 'reviewCount',         label: 'Review count',        type: 'number' },
		],
	},

	service: {
		id: 'service', type: 'Service', label: 'Service', group: 'Business',
		description: 'A service offered by a business or person.',
		fields: [
			{ key: 'name',         label: 'Service name', type: 'text',     default: '%title%' },
			{ key: 'description',  label: 'Description',  type: 'textarea', default: '%excerpt%' },
			{ key: 'serviceType',  label: 'Service type', type: 'text',     helper: 'e.g. Plumbing, Tax preparation' },
			{ key: 'areaServed',   label: 'Area served',  type: 'text',     helper: 'e.g. New York, NY or worldwide' },
			{ key: 'providerName', label: 'Provider',     type: 'text',     default: '%sitename%' },
			{ key: 'price',        label: 'Starting price', type: 'number' },
			{ key: 'currency',     label: 'Currency',     type: 'text',     default: 'USD' },
		],
	},

	person: {
		id: 'person', type: 'Person', label: 'Person', group: 'Profile',
		description: 'Person/profile schema with optional social links.',
		fields: [
			{ key: 'name',        label: 'Full name',     type: 'text',     default: '%title%' },
			{ key: 'jobTitle',    label: 'Job title',     type: 'text' },
			{ key: 'email',       label: 'Email',         type: 'text' },
			{ key: 'telephone',   label: 'Phone',         type: 'text' },
			{ key: 'url',         label: 'Website',       type: 'url' },
			{ key: 'image',       label: 'Photo URL',     type: 'url',      default: '%featured_image%' },
			{ key: 'workForName', label: 'Works for',     type: 'text' },
			{
				key: 'sameAs',
				label: 'Social URLs (sameAs)',
				type: 'repeater',
				addLabel: 'Add link',
				subfields: [{ key: 'value', label: 'URL', type: 'url' }],
			},
		],
	},

	music_album: {
		id: 'music_album', type: 'MusicAlbum', label: 'Music album', group: 'Creative work',
		description: 'Music album listing.',
		fields: [
			{ key: 'name',          label: 'Album name',     type: 'text',     default: '%title%' },
			{ key: 'byArtist',      label: 'Artist',         type: 'text' },
			{ key: 'datePublished', label: 'Release date',   type: 'date' },
			{ key: 'genre',         label: 'Genre',          type: 'text' },
			{ key: 'numTracks',     label: 'Number of tracks', type: 'number' },
			{ key: 'image',         label: 'Cover image',    type: 'url', default: '%featured_image%' },
		],
	},

	movie: {
		id: 'movie', type: 'Movie', label: 'Movie', group: 'Creative work',
		description: 'Movie listing with director and cast.',
		fields: [
			{ key: 'name',          label: 'Title',         type: 'text',     default: '%title%' },
			{ key: 'description',   label: 'Description',   type: 'textarea', default: '%excerpt%' },
			{ key: 'image',         label: 'Poster URL',    type: 'url',      default: '%featured_image%' },
			{ key: 'datePublished', label: 'Release date',  type: 'date' },
			{ key: 'director',      label: 'Director',      type: 'text' },
			{ key: 'duration',      label: 'Duration',      type: 'text',     helper: 'ISO-8601 — e.g. PT2H10M' },
			{ key: 'genre',         label: 'Genre',         type: 'text' },
			{
				key: 'actors',
				label: 'Cast',
				type: 'repeater',
				addLabel: 'Add actor',
				subfields: [{ key: 'value', label: 'Name', type: 'text' }],
			},
		],
	},

	review: {
		id: 'review', type: 'Review', label: 'Review', group: 'Content',
		description: 'A standalone review of a product, business, book, etc. Item name + rating are required.',
		fields: [
			{ key: 'itemType',     label: 'Reviewed type', type: 'select', options: ['Thing', 'Product', 'Book', 'Movie', 'Restaurant', 'LocalBusiness', 'SoftwareApplication', 'Service', 'Course'] },
			{ key: 'itemName',     label: 'Reviewed name', type: 'text' },
			{ key: 'ratingValue',  label: 'Rating',        type: 'number', helper: 'Required. Defaults best/worst to 5/1.' },
			{ key: 'bestRating',   label: 'Best rating',   type: 'number' },
			{ key: 'worstRating',  label: 'Worst rating',  type: 'number' },
			{ key: 'reviewBody',   label: 'Review text',   type: 'textarea' },
			{ key: 'authorName',   label: 'Reviewer',      type: 'text', default: '%author%' },
			{ key: 'datePublished', label: 'Date published', type: 'date', default: '%date%' },
		],
	},

	// ─── Site-identity overrides ────────────────────────────────
	// These types are auto-emitted by EBQ on every page. Adding a
	// user-configured version of any of them suppresses the auto
	// emission for that @type and ships the user's instead.
	website: {
		id: 'website', type: 'WebSite', label: 'WebSite (override site root)', group: 'Site identity',
		description: 'Override the auto-emitted WebSite node — site name, search action, social URLs.',
		fields: [
			{ key: 'name',         label: 'Site name',       type: 'text', default: '%sitename%' },
			{ key: 'description',  label: 'Site description', type: 'textarea' },
			{ key: 'inLanguage',   label: 'Language code',    type: 'text', helper: 'e.g. en-US, en-GB' },
			{ key: 'publisher',    label: 'Publisher (org)',  type: 'text' },
			{
				key: 'sameAs',
				label: 'Social URLs (sameAs)',
				type: 'repeater',
				addLabel: 'Add link',
				subfields: [{ key: 'value', label: 'URL', type: 'url' }],
			},
		],
	},

	organization: {
		id: 'organization', type: 'Organization', label: 'Organization (override publisher)', group: 'Site identity',
		description: 'Override the auto-emitted Organization (publisher) node. Use this to set a logo URL, legal name, contact info, and social profiles.',
		subtypes: ['Organization', 'Corporation', 'NewsMediaOrganization', 'EducationalOrganization', 'NGO'],
		fields: [
			{ key: 'name',         label: 'Name',            type: 'text',     default: '%sitename%' },
			{ key: 'legalName',    label: 'Legal name',      type: 'text' },
			{ key: 'description',  label: 'Description',     type: 'textarea' },
			{ key: 'url',          label: 'Website URL',     type: 'url' },
			{ key: 'logo',         label: 'Logo URL',        type: 'url' },
			{ key: 'email',        label: 'Email',           type: 'text' },
			{ key: 'telephone',    label: 'Phone',           type: 'text' },
			{ key: 'foundingDate', label: 'Founding date',   type: 'date' },
			{
				key: 'sameAs',
				label: 'Social URLs (sameAs)',
				type: 'repeater',
				addLabel: 'Add link',
				subfields: [{ key: 'value', label: 'URL', type: 'url' }],
			},
		],
	},

	webpage: {
		id: 'webpage', type: 'WebPage', label: 'WebPage (override this URL)', group: 'Site identity',
		description: 'Override the auto-emitted WebPage node for this post — useful when the page is about something specific (FAQ page, contact page, profile page).',
		subtypes: ['WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage', 'CheckoutPage', 'ProfilePage'],
		fields: [
			{ key: 'name',          label: 'Page name',         type: 'text',     default: '%title%' },
			{ key: 'description',   label: 'Description',       type: 'textarea', default: '%excerpt%' },
			{ key: 'inLanguage',    label: 'Language code',     type: 'text',     helper: 'e.g. en-US' },
			{ key: 'datePublished', label: 'Date published',    type: 'date',     default: '%date%' },
			{ key: 'dateModified',  label: 'Date modified',     type: 'date',     default: '%modified%' },
			{ key: 'primaryImage',  label: 'Primary image URL', type: 'url',      default: '%featured_image%' },
		],
	},

	custom: {
		id: 'custom', type: 'Thing', label: 'Custom', group: 'Custom',
		description: 'Build your own schema. Set the @type and add any properties — values are strings, or paste JSON ({ ... } or [ ... ]) to embed nested objects/arrays.',
		typeFreeform: true,
		fields: [
			{
				key: 'properties',
				label: 'Properties',
				type: 'repeater',
				addLabel: 'Add property',
				subfields: [
					{ key: 'name',  label: 'Property',     type: 'text', helper: 'schema.org property — e.g. headline, founder, areaServed' },
					{ key: 'value', label: 'Value',        type: 'textarea', helper: 'String or JSON. Variables like %title% resolve at render time.' },
				],
			},
		],
	},
};

export const TEMPLATE_LIST = Object.values(TEMPLATES);

export function getTemplate(id) {
	return TEMPLATES[id] || null;
}

export function templatesByGroup() {
	const groups = {};
	for (const t of TEMPLATE_LIST) {
		const g = t.group || 'Other';
		(groups[g] ||= []).push(t);
	}
	return groups;
}

/**
 * Initial `data` object for a freshly-added schema — pre-fills any field whose
 * template defines a `default` so the user starts with the obvious values
 * already populated (headline = %title%, etc.).
 */
export function initialDataForTemplate(template) {
	const out = {};
	for (const f of template.fields) {
		if (f.default !== undefined) {
			out[f.key] = f.default;
		}
	}
	return out;
}
