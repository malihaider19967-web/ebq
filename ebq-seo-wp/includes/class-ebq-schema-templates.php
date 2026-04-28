<?php
/**
 * Catalogue of supported schema templates and the renderer that turns a stored
 * template entry (template id + user data) into a JSON-LD node.
 *
 * Phase 1 ships with: Article (incl. NewsArticle / BlogPosting subtypes),
 * Product (with optional Review / AggregateRating), Event, FAQPage, Recipe,
 * LocalBusiness. Each template defines its field shape (mirrored in JS for
 * the editor UI) and a callable that builds the JSON-LD array from the
 * user-supplied + variable-resolved data.
 *
 * Adding a new template = add one entry to all_templates(); the UI picks it
 * up via the parallel JS registry in src/sidebar/schema/templates.js.
 */

if (! defined('ABSPATH')) {
    exit;
}

final class EBQ_Schema_Templates
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            'article'         => self::article_template(),
            'product'         => self::product_template(),
            'event'           => self::event_template(),
            'faq'             => self::faq_template(),
            'recipe'          => self::recipe_template(),
            'local_business'  => self::local_business_template(),
            'book'            => self::book_template(),
            'course'          => self::course_template(),
            'job_posting'     => self::job_posting_template(),
            'video'           => self::video_template(),
            'software'        => self::software_template(),
            'service'         => self::service_template(),
            'person'          => self::person_template(),
            'music_album'     => self::music_album_template(),
            'movie'           => self::movie_template(),
            'review'          => self::review_template(),
            // Site-identity overrides (auto-emitted by EBQ; user can
            // configure their own version which then takes precedence
            // for that @type).
            'website'         => self::website_template(),
            'organization'    => self::organization_template(),
            'webpage'         => self::webpage_template(),
            'custom'          => self::custom_template(),
        ];
    }

    public static function get(string $id): ?array
    {
        $all = self::all();
        return $all[$id] ?? null;
    }

    /**
     * Render a stored schema entry to a JSON-LD node, or null if unknown
     * template / disabled / missing required data.
     *
     * @param  array<string, mixed>  $entry  Stored entry from _ebq_schemas.
     * @return array<string, mixed>|null
     */
    public static function render(array $entry, int $post_id): ?array
    {
        if (empty($entry['enabled'])) {
            return null;
        }
        $template = self::get((string) ($entry['template'] ?? ''));
        if ($template === null) {
            return null;
        }
        $data = is_array($entry['data'] ?? null) ? $entry['data'] : [];
        // Resolve %vars% in every string before handing to the builder so each
        // template doesn't have to know about templating.
        $data = EBQ_Schema_Variables::resolve($data, $post_id);

        $type = (string) ($entry['type'] ?? $template['type']);

        $builder = $template['build'];
        if (! is_callable($builder)) {
            return null;
        }

        $node = $builder($data, $post_id, $type);
        return is_array($node) && ! empty($node) ? $node : null;
    }

    /* ─── Templates ──────────────────────────────────────────── */

    private static function article_template(): array
    {
        return [
            'id'    => 'article',
            'type'  => 'Article',
            'label' => 'Article',
            'group' => 'Content',
            'subtypes' => ['Article', 'BlogPosting', 'NewsArticle'],
            'build' => static function (array $d, int $post_id, string $type): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $headline = self::strv($d['headline'] ?? '') ?: (string) get_the_title($post_id);
                $node = [
                    '@type'    => $type ?: 'Article',
                    '@id'      => $url . '#schema-article-' . substr(md5($url . $type), 0, 8),
                    'headline' => $headline,
                    'mainEntityOfPage' => ['@id' => $url . '#webpage'],
                ];
                if ($d['description'] ?? '')   $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')         $node['image'] = self::strv($d['image']);
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                if ($d['dateModified'] ?? '')  $node['dateModified'] = self::strv($d['dateModified']);
                if ($d['authorName'] ?? '')    $node['author'] = ['@type' => 'Person', 'name' => self::strv($d['authorName'])];
                $node['publisher'] = ['@id' => home_url('/') . '#organization'];
                return $node;
            },
        ];
    }

    private static function product_template(): array
    {
        return [
            'id'    => 'product',
            'type'  => 'Product',
            'label' => 'Product',
            'group' => 'Commerce',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $name = self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id);
                $node = [
                    '@type' => 'Product',
                    '@id'   => $url . '#schema-product',
                    'name'  => $name,
                ];
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')       $node['image'] = self::strv($d['image']);
                if ($d['sku'] ?? '')         $node['sku'] = self::strv($d['sku']);
                if ($d['brand'] ?? '')       $node['brand'] = ['@type' => 'Brand', 'name' => self::strv($d['brand'])];

                $price = self::strv($d['price'] ?? '');
                if ($price !== '') {
                    $offer = [
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => self::strv($d['currency'] ?? 'USD') ?: 'USD',
                        'url'   => $url,
                    ];
                    $availability = self::strv($d['availability'] ?? '');
                    if ($availability !== '') {
                        $offer['availability'] = self::availability_to_url($availability);
                    }
                    $node['offers'] = $offer;
                }

                $rating = self::strv($d['reviewRating'] ?? '');
                $reviewCount = self::strv($d['reviewCount'] ?? '');
                if ($rating !== '' && $reviewCount !== '' && (float) $reviewCount > 0) {
                    $node['aggregateRating'] = [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => (string) $rating,
                        'reviewCount' => (int) $reviewCount,
                        'bestRating'  => '5',
                        'worstRating' => '1',
                    ];
                }
                return $node;
            },
        ];
    }

    private static function event_template(): array
    {
        return [
            'id'    => 'event',
            'type'  => 'Event',
            'label' => 'Event',
            'group' => 'Schedule',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $name = self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id);
                $node = [
                    '@type' => 'Event',
                    '@id'   => $url . '#schema-event',
                    'name'  => $name,
                ];
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')       $node['image'] = self::strv($d['image']);
                if ($d['startDate'] ?? '')   $node['startDate'] = self::strv($d['startDate']);
                if ($d['endDate'] ?? '')     $node['endDate'] = self::strv($d['endDate']);
                if ($d['eventStatus'] ?? '') $node['eventStatus'] = 'https://schema.org/' . self::strv($d['eventStatus']);
                if ($d['eventAttendanceMode'] ?? '') $node['eventAttendanceMode'] = 'https://schema.org/' . self::strv($d['eventAttendanceMode']);

                $loc_name = self::strv($d['locationName'] ?? '');
                $loc_addr = self::strv($d['locationAddress'] ?? '');
                if ($loc_name !== '' || $loc_addr !== '') {
                    $location = ['@type' => 'Place'];
                    if ($loc_name !== '') $location['name'] = $loc_name;
                    if ($loc_addr !== '') $location['address'] = $loc_addr;
                    $node['location'] = $location;
                }

                if ($d['performer'] ?? '')     $node['performer'] = ['@type' => 'PerformingGroup', 'name' => self::strv($d['performer'])];
                if ($d['organizerName'] ?? '') $node['organizer'] = ['@type' => 'Organization', 'name' => self::strv($d['organizerName'])];

                $offer_price = self::strv($d['offerPrice'] ?? '');
                if ($offer_price !== '') {
                    $node['offers'] = [
                        '@type' => 'Offer',
                        'price' => $offer_price,
                        'priceCurrency' => self::strv($d['offerCurrency'] ?? 'USD') ?: 'USD',
                        'url' => self::strv($d['offerUrl'] ?? '') ?: $url,
                        'availability' => 'https://schema.org/InStock',
                    ];
                }
                return $node;
            },
        ];
    }

    private static function faq_template(): array
    {
        return [
            'id'    => 'faq',
            'type'  => 'FAQPage',
            'label' => 'FAQ',
            'group' => 'Content',
            'build' => static function (array $d, int $post_id): ?array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $items = is_array($d['questions'] ?? null) ? $d['questions'] : [];
                $entries = [];
                foreach ($items as $q) {
                    if (! is_array($q)) continue;
                    $question = self::strv($q['question'] ?? '');
                    $answer = self::strv($q['answer'] ?? '');
                    if ($question === '' || $answer === '') continue;
                    $entries[] = [
                        '@type' => 'Question',
                        'name'  => $question,
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer],
                    ];
                }
                if (empty($entries)) {
                    return null;
                }
                return [
                    '@type'      => 'FAQPage',
                    '@id'        => $url . '#schema-faq',
                    'mainEntity' => $entries,
                ];
            },
        ];
    }

    private static function recipe_template(): array
    {
        return [
            'id'    => 'recipe',
            'type'  => 'Recipe',
            'label' => 'Recipe',
            'group' => 'Content',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $name = self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id);
                $node = [
                    '@type' => 'Recipe',
                    '@id'   => $url . '#schema-recipe',
                    'name'  => $name,
                ];
                if ($d['description'] ?? '')    $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')          $node['image'] = self::strv($d['image']);
                if ($d['prepTime'] ?? '')       $node['prepTime'] = self::strv($d['prepTime']);
                if ($d['cookTime'] ?? '')       $node['cookTime'] = self::strv($d['cookTime']);
                if ($d['totalTime'] ?? '')      $node['totalTime'] = self::strv($d['totalTime']);
                if ($d['recipeYield'] ?? '')    $node['recipeYield'] = self::strv($d['recipeYield']);
                if ($d['recipeCategory'] ?? '') $node['recipeCategory'] = self::strv($d['recipeCategory']);
                if ($d['recipeCuisine'] ?? '')  $node['recipeCuisine'] = self::strv($d['recipeCuisine']);

                $ingredients = self::list_of_strings($d['ingredients'] ?? null);
                if (! empty($ingredients)) {
                    $node['recipeIngredient'] = $ingredients;
                }

                $instructions = self::list_of_strings($d['instructions'] ?? null);
                if (! empty($instructions)) {
                    $node['recipeInstructions'] = array_map(static fn ($text) => [
                        '@type' => 'HowToStep',
                        'text'  => $text,
                    ], $instructions);
                }

                $calories = self::strv($d['calories'] ?? '');
                if ($calories !== '') {
                    $node['nutrition'] = ['@type' => 'NutritionInformation', 'calories' => $calories . ' calories'];
                }
                return $node;
            },
        ];
    }

    private static function local_business_template(): array
    {
        return [
            'id'    => 'local_business',
            'type'  => 'LocalBusiness',
            'label' => 'Local Business',
            'group' => 'Business',
            'subtypes' => ['LocalBusiness', 'Restaurant', 'Store', 'ProfessionalService', 'MedicalBusiness'],
            'build' => static function (array $d, int $post_id, string $type): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $name = self::strv($d['name'] ?? '') ?: (string) get_bloginfo('name');
                $node = [
                    '@type' => $type ?: 'LocalBusiness',
                    '@id'   => $url . '#schema-local-business',
                    'name'  => $name,
                ];
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')       $node['image'] = self::strv($d['image']);
                if ($d['telephone'] ?? '')   $node['telephone'] = self::strv($d['telephone']);
                if ($d['priceRange'] ?? '')  $node['priceRange'] = self::strv($d['priceRange']);

                $address_parts = array_filter([
                    'streetAddress'   => self::strv($d['streetAddress'] ?? ''),
                    'addressLocality' => self::strv($d['addressLocality'] ?? ''),
                    'addressRegion'   => self::strv($d['addressRegion'] ?? ''),
                    'postalCode'      => self::strv($d['postalCode'] ?? ''),
                    'addressCountry'  => self::strv($d['addressCountry'] ?? ''),
                ], static fn ($v) => $v !== '');
                if (! empty($address_parts)) {
                    $node['address'] = array_merge(['@type' => 'PostalAddress'], $address_parts);
                }

                $lat = self::strv($d['latitude'] ?? '');
                $lng = self::strv($d['longitude'] ?? '');
                if ($lat !== '' && $lng !== '') {
                    $node['geo'] = [
                        '@type'    => 'GeoCoordinates',
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ];
                }

                $hours = self::list_of_strings($d['openingHours'] ?? null);
                if (! empty($hours)) {
                    $node['openingHours'] = $hours;
                }
                return $node;
            },
        ];
    }

    private static function book_template(): array
    {
        return [
            'id' => 'book', 'type' => 'Book', 'label' => 'Book', 'group' => 'Creative work',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'Book',
                    '@id'   => $url . '#schema-book',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['author'] ?? '')      $node['author'] = ['@type' => 'Person', 'name' => self::strv($d['author'])];
                if ($d['isbn'] ?? '')        $node['isbn'] = self::strv($d['isbn']);
                if ($d['bookFormat'] ?? '')  $node['bookFormat'] = 'https://schema.org/' . self::strv($d['bookFormat']);
                if ($d['numberOfPages'] ?? '') $node['numberOfPages'] = (int) $d['numberOfPages'];
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                if ($d['publisher'] ?? '')   $node['publisher'] = ['@type' => 'Organization', 'name' => self::strv($d['publisher'])];
                if ($d['inLanguage'] ?? '')  $node['inLanguage'] = self::strv($d['inLanguage']);
                if ($d['image'] ?? '')       $node['image'] = self::strv($d['image']);
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                return $node;
            },
        ];
    }

    private static function course_template(): array
    {
        return [
            'id' => 'course', 'type' => 'Course', 'label' => 'Course', 'group' => 'Education',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'Course',
                    '@id'   => $url . '#schema-course',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')   $node['description'] = self::strv($d['description']);
                if ($d['providerName'] ?? '')  $node['provider'] = [
                    '@type' => 'Organization',
                    'name'  => self::strv($d['providerName']),
                    'sameAs' => self::strv($d['providerUrl'] ?? '') ?: null,
                ];
                if (! empty($node['provider']) && empty($node['provider']['sameAs'])) {
                    unset($node['provider']['sameAs']);
                }
                if ($d['courseCode'] ?? '')      $node['courseCode'] = self::strv($d['courseCode']);
                if ($d['educationalLevel'] ?? '') $node['educationalLevel'] = self::strv($d['educationalLevel']);
                if ($d['timeRequired'] ?? '')    $node['timeRequired'] = self::strv($d['timeRequired']);
                if ($d['inLanguage'] ?? '')      $node['inLanguage'] = self::strv($d['inLanguage']);
                return $node;
            },
        ];
    }

    private static function job_posting_template(): array
    {
        return [
            'id' => 'job_posting', 'type' => 'JobPosting', 'label' => 'Job posting', 'group' => 'Business',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'JobPosting',
                    '@id'   => $url . '#schema-job',
                    'title' => self::strv($d['title'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')   $node['description'] = self::strv($d['description']);
                if ($d['datePosted'] ?? '')    $node['datePosted'] = self::strv($d['datePosted']);
                if ($d['validThrough'] ?? '')  $node['validThrough'] = self::strv($d['validThrough']);
                if ($d['employmentType'] ?? '') $node['employmentType'] = self::strv($d['employmentType']);
                if ($d['hiringOrgName'] ?? '') {
                    $org = ['@type' => 'Organization', 'name' => self::strv($d['hiringOrgName'])];
                    if ($d['hiringOrgUrl'] ?? '') $org['sameAs'] = self::strv($d['hiringOrgUrl']);
                    if ($d['hiringOrgLogo'] ?? '') $org['logo'] = self::strv($d['hiringOrgLogo']);
                    $node['hiringOrganization'] = $org;
                }
                if ($d['locationLocality'] ?? '' || $d['locationRegion'] ?? '' || $d['locationCountry'] ?? '') {
                    $node['jobLocation'] = [
                        '@type' => 'Place',
                        'address' => array_filter([
                            '@type' => 'PostalAddress',
                            'addressLocality' => self::strv($d['locationLocality'] ?? ''),
                            'addressRegion' => self::strv($d['locationRegion'] ?? ''),
                            'addressCountry' => self::strv($d['locationCountry'] ?? ''),
                            'postalCode' => self::strv($d['locationPostalCode'] ?? ''),
                            'streetAddress' => self::strv($d['locationStreet'] ?? ''),
                        ], static fn ($v) => $v !== '' && $v !== null),
                    ];
                }
                $salary_min = self::strv($d['salaryMin'] ?? '');
                $salary_max = self::strv($d['salaryMax'] ?? '');
                if ($salary_min !== '' || $salary_max !== '') {
                    $node['baseSalary'] = [
                        '@type' => 'MonetaryAmount',
                        'currency' => self::strv($d['salaryCurrency'] ?? 'USD') ?: 'USD',
                        'value' => array_filter([
                            '@type' => 'QuantitativeValue',
                            'minValue' => $salary_min !== '' ? (float) $salary_min : null,
                            'maxValue' => $salary_max !== '' ? (float) $salary_max : null,
                            'unitText' => self::strv($d['salaryUnit'] ?? 'YEAR') ?: 'YEAR',
                        ], static fn ($v) => $v !== null),
                    ];
                }
                return $node;
            },
        ];
    }

    private static function video_template(): array
    {
        return [
            'id' => 'video', 'type' => 'VideoObject', 'label' => 'Video', 'group' => 'Creative work',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'VideoObject',
                    '@id'   => $url . '#schema-video',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')  $node['description'] = self::strv($d['description']);
                if ($d['thumbnailUrl'] ?? '') $node['thumbnailUrl'] = self::strv($d['thumbnailUrl']);
                if ($d['contentUrl'] ?? '')   $node['contentUrl'] = self::strv($d['contentUrl']);
                if ($d['embedUrl'] ?? '')     $node['embedUrl'] = self::strv($d['embedUrl']);
                if ($d['uploadDate'] ?? '')   $node['uploadDate'] = self::strv($d['uploadDate']);
                if ($d['duration'] ?? '')     $node['duration'] = self::strv($d['duration']);
                return $node;
            },
        ];
    }

    private static function software_template(): array
    {
        return [
            'id' => 'software', 'type' => 'SoftwareApplication', 'label' => 'Software', 'group' => 'Commerce',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'SoftwareApplication',
                    '@id'   => $url . '#schema-software',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')         $node['description'] = self::strv($d['description']);
                if ($d['operatingSystem'] ?? '')     $node['operatingSystem'] = self::strv($d['operatingSystem']);
                if ($d['applicationCategory'] ?? '') $node['applicationCategory'] = self::strv($d['applicationCategory']);
                if ($d['softwareVersion'] ?? '')     $node['softwareVersion'] = self::strv($d['softwareVersion']);
                if ($d['downloadUrl'] ?? '')         $node['downloadUrl'] = self::strv($d['downloadUrl']);
                if ($d['image'] ?? '')               $node['image'] = self::strv($d['image']);
                $price = self::strv($d['price'] ?? '');
                if ($price !== '') {
                    $node['offers'] = [
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => self::strv($d['currency'] ?? 'USD') ?: 'USD',
                    ];
                }
                $rating = self::strv($d['ratingValue'] ?? '');
                $reviewCount = self::strv($d['reviewCount'] ?? '');
                if ($rating !== '' && $reviewCount !== '' && (float) $reviewCount > 0) {
                    $node['aggregateRating'] = [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => (string) $rating,
                        'reviewCount' => (int) $reviewCount,
                    ];
                }
                return $node;
            },
        ];
    }

    private static function service_template(): array
    {
        return [
            'id' => 'service', 'type' => 'Service', 'label' => 'Service', 'group' => 'Business',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'Service',
                    '@id'   => $url . '#schema-service',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')   $node['description'] = self::strv($d['description']);
                if ($d['serviceType'] ?? '')   $node['serviceType'] = self::strv($d['serviceType']);
                if ($d['areaServed'] ?? '')    $node['areaServed'] = self::strv($d['areaServed']);
                if ($d['providerName'] ?? '')  $node['provider'] = ['@type' => 'Organization', 'name' => self::strv($d['providerName'])];
                $price = self::strv($d['price'] ?? '');
                if ($price !== '') {
                    $node['offers'] = [
                        '@type' => 'Offer',
                        'price' => $price,
                        'priceCurrency' => self::strv($d['currency'] ?? 'USD') ?: 'USD',
                    ];
                }
                return $node;
            },
        ];
    }

    private static function website_template(): array
    {
        return [
            'id' => 'website', 'type' => 'WebSite', 'label' => 'WebSite', 'group' => 'Site identity',
            'build' => static function (array $d, int $post_id): array {
                $home = (string) home_url('/');
                $node = [
                    '@type' => 'WebSite',
                    '@id'   => $home . '#website',
                    'url'   => $home,
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_bloginfo('name'),
                ];
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['inLanguage'] ?? '')  $node['inLanguage'] = self::strv($d['inLanguage']);
                if ($d['publisher'] ?? '')   $node['publisher'] = ['@type' => 'Organization', 'name' => self::strv($d['publisher'])];

                $sameAs = self::list_of_strings($d['sameAs'] ?? null);
                if (! empty($sameAs)) {
                    $node['sameAs'] = $sameAs;
                }
                return $node;
            },
        ];
    }

    private static function organization_template(): array
    {
        return [
            'id' => 'organization', 'type' => 'Organization', 'label' => 'Organization', 'group' => 'Site identity',
            'subtypes' => ['Organization', 'Corporation', 'NewsMediaOrganization', 'EducationalOrganization', 'NGO'],
            'build' => static function (array $d, int $post_id, string $type): array {
                $home = (string) home_url('/');
                $node = [
                    '@type' => $type ?: 'Organization',
                    '@id'   => $home . '#organization',
                    'url'   => self::strv($d['url'] ?? '') ?: $home,
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_bloginfo('name'),
                ];
                if ($d['legalName'] ?? '')  $node['legalName']  = self::strv($d['legalName']);
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['logo'] ?? '') {
                    $node['logo'] = [
                        '@type' => 'ImageObject',
                        '@id'   => $home . '#logo',
                        'url'   => self::strv($d['logo']),
                    ];
                }
                if ($d['email'] ?? '')      $node['email']      = self::strv($d['email']);
                if ($d['telephone'] ?? '')  $node['telephone']  = self::strv($d['telephone']);
                if ($d['foundingDate'] ?? '') $node['foundingDate'] = self::strv($d['foundingDate']);

                $sameAs = self::list_of_strings($d['sameAs'] ?? null);
                if (! empty($sameAs)) {
                    $node['sameAs'] = $sameAs;
                }
                return $node;
            },
        ];
    }

    private static function webpage_template(): array
    {
        return [
            'id' => 'webpage', 'type' => 'WebPage', 'label' => 'WebPage (this URL)', 'group' => 'Site identity',
            'subtypes' => ['WebPage', 'AboutPage', 'ContactPage', 'FAQPage', 'CollectionPage', 'CheckoutPage', 'ProfilePage'],
            'build' => static function (array $d, int $post_id, string $type): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => $type ?: 'WebPage',
                    '@id'   => $url . '#webpage',
                    'url'   => $url,
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '') $node['description'] = self::strv($d['description']);
                if ($d['inLanguage'] ?? '')  $node['inLanguage'] = self::strv($d['inLanguage']);
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                if ($d['dateModified'] ?? '')  $node['dateModified']  = self::strv($d['dateModified']);
                if ($d['primaryImage'] ?? '') {
                    $node['primaryImageOfPage'] = ['@type' => 'ImageObject', 'url' => self::strv($d['primaryImage'])];
                }
                return $node;
            },
        ];
    }

    private static function person_template(): array
    {
        return [
            'id' => 'person', 'type' => 'Person', 'label' => 'Person', 'group' => 'Profile',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'Person',
                    '@id'   => $url . '#schema-person-custom',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['jobTitle'] ?? '')    $node['jobTitle'] = self::strv($d['jobTitle']);
                if ($d['email'] ?? '')       $node['email'] = self::strv($d['email']);
                if ($d['telephone'] ?? '')   $node['telephone'] = self::strv($d['telephone']);
                if ($d['url'] ?? '')         $node['url'] = self::strv($d['url']);
                if ($d['image'] ?? '')       $node['image'] = self::strv($d['image']);
                if ($d['workForName'] ?? '') $node['worksFor'] = ['@type' => 'Organization', 'name' => self::strv($d['workForName'])];

                $sameAs = self::list_of_strings($d['sameAs'] ?? null);
                if (! empty($sameAs)) {
                    $node['sameAs'] = $sameAs;
                }
                return $node;
            },
        ];
    }

    private static function music_album_template(): array
    {
        return [
            'id' => 'music_album', 'type' => 'MusicAlbum', 'label' => 'Music album', 'group' => 'Creative work',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'MusicAlbum',
                    '@id'   => $url . '#schema-album',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['byArtist'] ?? '')      $node['byArtist'] = ['@type' => 'MusicGroup', 'name' => self::strv($d['byArtist'])];
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                if ($d['genre'] ?? '')         $node['genre'] = self::strv($d['genre']);
                if ($d['numTracks'] ?? '')     $node['numTracks'] = (int) $d['numTracks'];
                if ($d['image'] ?? '')         $node['image'] = self::strv($d['image']);
                return $node;
            },
        ];
    }

    private static function movie_template(): array
    {
        return [
            'id' => 'movie', 'type' => 'Movie', 'label' => 'Movie', 'group' => 'Creative work',
            'build' => static function (array $d, int $post_id): array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $node = [
                    '@type' => 'Movie',
                    '@id'   => $url . '#schema-movie',
                    'name'  => self::strv($d['name'] ?? '') ?: (string) get_the_title($post_id),
                ];
                if ($d['description'] ?? '')   $node['description'] = self::strv($d['description']);
                if ($d['image'] ?? '')         $node['image'] = self::strv($d['image']);
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                if ($d['director'] ?? '')      $node['director'] = ['@type' => 'Person', 'name' => self::strv($d['director'])];
                if ($d['duration'] ?? '')      $node['duration'] = self::strv($d['duration']);
                if ($d['genre'] ?? '')         $node['genre'] = self::strv($d['genre']);

                $actors = self::list_of_strings($d['actors'] ?? null);
                if (! empty($actors)) {
                    $node['actor'] = array_map(static fn ($name) => ['@type' => 'Person', 'name' => $name], $actors);
                }
                return $node;
            },
        ];
    }

    private static function review_template(): array
    {
        return [
            'id' => 'review', 'type' => 'Review', 'label' => 'Review', 'group' => 'Content',
            'build' => static function (array $d, int $post_id): ?array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $rating = self::strv($d['ratingValue'] ?? '');
                $itemName = self::strv($d['itemName'] ?? '');
                if ($rating === '' || $itemName === '') {
                    return null;
                }
                $node = [
                    '@type' => 'Review',
                    '@id'   => $url . '#schema-review',
                    'itemReviewed' => [
                        '@type' => self::strv($d['itemType'] ?? 'Thing') ?: 'Thing',
                        'name'  => $itemName,
                    ],
                    'reviewRating' => [
                        '@type'       => 'Rating',
                        'ratingValue' => $rating,
                        'bestRating'  => self::strv($d['bestRating'] ?? '5') ?: '5',
                        'worstRating' => self::strv($d['worstRating'] ?? '1') ?: '1',
                    ],
                ];
                if ($d['reviewBody'] ?? '')   $node['reviewBody'] = self::strv($d['reviewBody']);
                if ($d['authorName'] ?? '')   $node['author'] = ['@type' => 'Person', 'name' => self::strv($d['authorName'])];
                if ($d['datePublished'] ?? '') $node['datePublished'] = self::strv($d['datePublished']);
                return $node;
            },
        ];
    }

    /**
     * Custom user-defined schema. Type comes from the entry, properties are
     * key/value rows; values are passed through as strings unless they parse
     * as JSON (object/array), in which case the parsed structure is used.
     */
    private static function custom_template(): array
    {
        return [
            'id' => 'custom', 'type' => 'Thing', 'label' => 'Custom', 'group' => 'Custom',
            'build' => static function (array $d, int $post_id, string $type): ?array {
                $url = $post_id > 0 ? (string) get_permalink($post_id) : (string) home_url('/');
                $type = self::strv($type) ?: 'Thing';
                $node = [
                    '@type' => $type,
                    '@id'   => $url . '#schema-custom-' . substr(md5($type . wp_json_encode($d)), 0, 8),
                ];
                $props = is_array($d['properties'] ?? null) ? $d['properties'] : [];
                foreach ($props as $row) {
                    if (! is_array($row)) continue;
                    $key = preg_replace('/[^A-Za-z0-9_@]/', '', (string) ($row['name'] ?? ''));
                    if ($key === '') continue;
                    $raw_value = $row['value'] ?? '';
                    $value = self::parse_custom_value($raw_value);
                    if ($value === '' || $value === null) continue;
                    $node[$key] = $value;
                }
                // A bare @type/@id node has no value; only emit if at least
                // one property is present.
                $extras = array_diff_key($node, ['@type' => true, '@id' => true]);
                return $extras === [] ? null : $node;
            },
        ];
    }

    /**
     * Custom property values are stored as strings. If the value parses as
     * JSON to an object or array, return that structure so users can express
     * nested schema.org nodes. Otherwise return the trimmed string.
     *
     * @param  mixed  $raw
     * @return mixed
     */
    private static function parse_custom_value($raw)
    {
        if (is_array($raw)) {
            return $raw;
        }
        $s = trim((string) $raw);
        if ($s === '') return '';
        if ($s[0] === '{' || $s[0] === '[') {
            $decoded = json_decode($s, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return wp_strip_all_tags($s);
    }

    /* ─── Helpers ────────────────────────────────────────────── */

    private static function strv($value): string
    {
        if (is_array($value)) return '';
        $s = trim((string) $value);
        return wp_strip_all_tags($s);
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function list_of_strings($value): array
    {
        if (! is_array($value)) return [];
        $out = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                // Repeater rows arrive as { value: '...' } from the JS UI.
                $item = $item['value'] ?? $item['text'] ?? '';
            }
            $clean = self::strv($item);
            if ($clean !== '') {
                $out[] = $clean;
            }
        }
        return $out;
    }

    private static function availability_to_url(string $value): string
    {
        $key = strtolower(preg_replace('/[^a-z]/i', '', $value));
        $map = [
            'instock'             => 'https://schema.org/InStock',
            'outofstock'          => 'https://schema.org/OutOfStock',
            'preorder'            => 'https://schema.org/PreOrder',
            'discontinued'        => 'https://schema.org/Discontinued',
            'limitedavailability' => 'https://schema.org/LimitedAvailability',
            'soldout'             => 'https://schema.org/SoldOut',
        ];
        return $map[$key] ?? 'https://schema.org/InStock';
    }
}
