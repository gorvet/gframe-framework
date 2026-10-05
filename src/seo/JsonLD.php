<?php

class JsonLD {
    public function renderSchema($schema, $metaTags, $routeParams): string {
        if (empty($schema)) return '';

        $mt = $metaTags ?? [];
        $sch = $schema;

        $siteUrl = rtrim(site_url, '/');
        $pageUrl = rtrim(($routeParams['currentURL'] ?? ''), '/');
        if ($pageUrl === '') $pageUrl = $siteUrl;

        $siteName = $sch['siteName'] ?? ($mt['ogsite_name'] ?? ($mt['title'] ?? parse_url($siteUrl, PHP_URL_HOST)));
        $title = $sch['title'] ?? ($mt['title'] ?? null);
        $desc = $sch['description'] ?? ($mt['description'] ?? null);
        $image = $sch['image'] ?? ($sch['webpage']['primaryImageOfPage']['url'] ?? ($mt['ogimage'] ?? null));

        $langRaw = $sch['lang'] ?? ($routeParams['lang'] ?? ($mt['oglocale'] ?? 'es'));
        $langRaw = str_replace('_', '-', $langRaw);
        if (preg_match('/^[a-z]{2}$/', $langRaw)) {
            $lang = $langRaw;
        } elseif (preg_match('/^[a-z]{2}-[A-Za-z]{2}$/', $langRaw)) {
            $lang = strtolower(substr($langRaw, 0, 2)) . '-' . strtoupper(substr($langRaw, 3, 2));
        } else {
            $lang = 'es';
        }

        $type = $sch['type'] ?? 'WebPage';
        $id = fn(string $frag) => $pageUrl . '#' . $frag;
        $graph = [];

        $orgData = $sch['org'] ?? [];
        $orgName = $orgData['name'] ?? $siteName;
        $orgUrl = $orgData['url'] ?? $siteUrl;
        $logoUrl = $orgData['logo'] ?? null;
        $sameAsArr = [];
        if (!empty($orgData['sameAs'])) {
            $sameAsArr = is_array($orgData['sameAs']) ? array_values(array_filter($orgData['sameAs'])) : [$orgData['sameAs']];
            $sameAsArr = array_values(array_filter($sameAsArr, fn($u) => is_string($u) && str_starts_with($u, 'http')));
        }

        $org = $this->compact([
            '@type' => 'Organization',
            '@id' => $siteUrl . '#organization',
            'name' => $orgName,
            'url' => $orgUrl,
            'logo' => $logoUrl ? ['@type' => 'ImageObject', 'url' => $logoUrl] : null,
            'sameAs' => $sameAsArr ?: null,
        ]);
        $graph[$org['@id']] = $org;

        $ws = $this->compact([
            '@type' => 'WebSite',
            '@id' => $siteUrl . '#website',
            'url' => $siteUrl,
            'name' => $siteName,
            'inLanguage' => $lang,
            'publisher' => ['@id' => $siteUrl . '#organization'],
            'potentialAction' =>
                (!empty($sch['search']['target']) && strpos($sch['search']['target'], '{search_term_string}') !== false)
                    ? [
                        '@type' => 'SearchAction',
                        'target' => $sch['search']['target'],
                        'query-input' => 'required name=search_term_string',
                    ]
                    : null,
        ]);
        $graph[$ws['@id']] = $ws;

        $types = ['WebPage'];
        if ($type === 'CollectionPage') $types[] = 'CollectionPage';
        if ($type === 'ContactPage') $types[] = 'ContactPage';

        $wp = $this->compact([
            '@type' => $types,
            '@id' => $id('webpage'),
            'url' => $pageUrl,
            'name' => $title,
            'description' => $desc,
            'inLanguage' => $lang,
            'isPartOf' => ['@id' => $siteUrl . '#website'],
            'datePublished' => $sch['datePublished'] ?? null,
            'dateModified' => $sch['dateModified'] ?? null,
        ]);
        if ($image) {
            $wp['image'] = [['@type' => 'ImageObject', '@id' => $id('primaryimage'), 'url' => $image]];
        }
        $graph[$wp['@id']] = $wp;

        if ($image) {
            $graph[$id('primaryimage')] = [
                '@type' => 'ImageObject',
                '@id' => $id('primaryimage'),
                'url' => $image,
            ];
        }

        if (in_array($type, ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle'], true)) {
            $article = $this->compact([
                '@type' => $type,
                '@id' => $id('article'),
                'headline' => $title,
                'mainEntityOfPage' => ['@id' => $id('webpage')],
                'image' => $image ? ['@id' => $id('primaryimage')] : null,
                'datePublished' => $sch['datePublished'] ?? null,
                'dateModified' => $sch['dateModified'] ?? null,
                'author' => !empty($sch['author']) ? [['@type' => 'Person', 'name' => $sch['author']]] : (!empty($mt['author']) ? [['@type' => 'Person', 'name' => $mt['author']]] : null),
                'publisher' => ['@id' => $siteUrl . '#organization'],
            ]);
            $graph[$article['@id']] = $article;
        }

        if ($type === 'Event' || !empty($sch['event'])) {
            $e = !empty($sch['event']) && is_array($sch['event']) ? $sch['event'] : $sch;
            $event = $this->compact([
                '@type' => 'Event',
                '@id' => $id('event'),
                'name' => $e['name'] ?? $title,
                'description' => $e['description'] ?? $desc,
                'startDate' => $e['startDate'] ?? null,
                'endDate' => $e['endDate'] ?? null,
                'eventAttendanceMode' => $e['eventAttendanceMode'] ?? null,
                'eventStatus' => $e['eventStatus'] ?? null,
                'image' => !empty($e['images']) ? $e['images'] : ($image ? [$image] : null),
                'location' => !empty($e['location']) ? $e['location'] : null,
                'organizer' => !empty($e['organizer']) ? $e['organizer'] : ['@id' => $siteUrl . '#organization'],
                'offers' => !empty($e['offers']) ? $e['offers'] : null,
            ]);
            $graph[$event['@id']] = $event;
        }

        if ($type === 'Service' || !empty($sch['service'])) {
            $serviceData = !empty($sch['service']) && is_array($sch['service']) ? $sch['service'] : $sch;
            $service = $this->compact([
                '@type' => 'Service',
                '@id' => $id('service'),
                'name' => $serviceData['name'] ?? $title,
                'description' => $serviceData['description'] ?? $desc,
                'provider' => $serviceData['provider'] ?? ['@id' => $siteUrl . '#organization'],
                'areaServed' => $serviceData['areaServed'] ?? null,
                'offers' => $serviceData['offers'] ?? null,
                'serviceType' => $serviceData['serviceType'] ?? null,
            ]);
            $graph[$service['@id']] = $service;
        }

        if ($type === 'LocalBusiness' || !empty($sch['business'])) {
            $businessData = !empty($sch['business']) && is_array($sch['business']) ? $sch['business'] : $sch;
            $business = $this->compact([
                '@type' => $businessData['type'] ?? 'LocalBusiness',
                '@id' => $id('localbusiness'),
                'name' => $businessData['name'] ?? $siteName,
                'description' => $businessData['description'] ?? $desc,
                'url' => $businessData['url'] ?? $siteUrl,
                'image' => $businessData['image'] ?? $image,
                'telephone' => $businessData['telephone'] ?? null,
                'address' => $businessData['address'] ?? null,
                'geo' => $businessData['geo'] ?? null,
                'openingHoursSpecification' => $businessData['openingHoursSpecification'] ?? null,
                'sameAs' => $businessData['sameAs'] ?? ($orgData['sameAs'] ?? null),
            ]);
            $graph[$business['@id']] = $business;
        }

        if ($type === 'Course' || !empty($sch['course'])) {
            $courseData = !empty($sch['course']) && is_array($sch['course']) ? $sch['course'] : $sch;
            $course = $this->compact([
                '@type' => 'Course',
                '@id' => $id('course'),
                'name' => $courseData['name'] ?? $title,
                'description' => $courseData['description'] ?? $desc,
                'provider' => $courseData['provider'] ?? ['@id' => $siteUrl . '#organization'],
                'url' => $courseData['url'] ?? $pageUrl,
            ]);
            $graph[$course['@id']] = $course;
        }

        if ($type === 'JobPosting' || !empty($sch['job'])) {
            $jobData = !empty($sch['job']) && is_array($sch['job']) ? $sch['job'] : $sch;
            $job = $this->compact([
                '@type' => 'JobPosting',
                '@id' => $id('job'),
                'title' => $jobData['title'] ?? $title,
                'description' => $jobData['description'] ?? $desc,
                'datePosted' => $jobData['datePosted'] ?? null,
                'validThrough' => $jobData['validThrough'] ?? null,
                'employmentType' => $jobData['employmentType'] ?? null,
                'hiringOrganization' => $jobData['hiringOrganization'] ?? ['@id' => $siteUrl . '#organization'],
                'jobLocation' => $jobData['jobLocation'] ?? null,
                'baseSalary' => $jobData['baseSalary'] ?? null,
                'applicantLocationRequirements' => $jobData['applicantLocationRequirements'] ?? null,
                'directApply' => $jobData['directApply'] ?? null,
            ]);
            $graph[$job['@id']] = $job;
        }

        if ($type === 'VideoObject' || !empty($sch['video'])) {
            $videoData = !empty($sch['video']) && is_array($sch['video']) ? $sch['video'] : $sch;
            $video = $this->compact([
                '@type' => 'VideoObject',
                '@id' => $id('video'),
                'name' => $videoData['name'] ?? $title,
                'description' => $videoData['description'] ?? $desc,
                'thumbnailUrl' => $videoData['thumbnailUrl'] ?? ($image ? [$image] : null),
                'uploadDate' => $videoData['uploadDate'] ?? null,
                'duration' => $videoData['duration'] ?? null,
                'contentUrl' => $videoData['contentUrl'] ?? null,
                'embedUrl' => $videoData['embedUrl'] ?? null,
                'publisher' => $videoData['publisher'] ?? ['@id' => $siteUrl . '#organization'],
            ]);
            $graph[$video['@id']] = $video;
        }

        if ($type === 'Recipe' || !empty($sch['recipe'])) {
            $recipeData = !empty($sch['recipe']) && is_array($sch['recipe']) ? $sch['recipe'] : $sch;
            $recipe = $this->compact([
                '@type' => 'Recipe',
                '@id' => $id('recipe'),
                'name' => $recipeData['name'] ?? $title,
                'description' => $recipeData['description'] ?? $desc,
                'image' => $recipeData['image'] ?? ($image ? [$image] : null),
                'author' => $recipeData['author'] ?? (!empty($mt['author']) ? [['@type' => 'Person', 'name' => $mt['author']]] : null),
                'recipeYield' => $recipeData['recipeYield'] ?? null,
                'prepTime' => $recipeData['prepTime'] ?? null,
                'cookTime' => $recipeData['cookTime'] ?? null,
                'totalTime' => $recipeData['totalTime'] ?? null,
                'recipeCategory' => $recipeData['recipeCategory'] ?? null,
                'recipeCuisine' => $recipeData['recipeCuisine'] ?? null,
                'keywords' => $recipeData['keywords'] ?? null,
                'recipeIngredient' => $recipeData['recipeIngredient'] ?? null,
                'recipeInstructions' => $recipeData['recipeInstructions'] ?? null,
                'nutrition' => $recipeData['nutrition'] ?? null,
            ]);
            $graph[$recipe['@id']] = $recipe;
        }

        if ($type === 'CreativeWork' || !empty($sch['creativeWork'])) {
            $creativeData = !empty($sch['creativeWork']) && is_array($sch['creativeWork']) ? $sch['creativeWork'] : $sch;
            $creativeWork = $this->compact([
                '@type' => 'CreativeWork',
                '@id' => $id('creativework'),
                'name' => $creativeData['name'] ?? $title,
                'description' => $creativeData['description'] ?? $desc,
                'url' => $creativeData['url'] ?? $pageUrl,
                'inLanguage' => $creativeData['inLanguage'] ?? $lang,
                'dateCreated' => $creativeData['dateCreated'] ?? null,
                'datePublished' => $creativeData['datePublished'] ?? null,
                'dateModified' => $creativeData['dateModified'] ?? null,
                'isAccessibleForFree' => $creativeData['isAccessibleForFree'] ?? null,
                'author' => $creativeData['author'] ?? (!empty($sch['author']) ? [['@type' => 'Person', 'name' => $sch['author']]] : (!empty($mt['author']) ? [['@type' => 'Person', 'name' => $mt['author']]] : null)),
                'publisher' => $creativeData['publisher'] ?? ['@id' => $siteUrl . '#organization'],
                'image' => $creativeData['image'] ?? ($image ? ['@id' => $id('primaryimage')] : null),
                'text' => $creativeData['text'] ?? null,
                'mainEntityOfPage' => $creativeData['mainEntityOfPage'] ?? ['@id' => $id('webpage')],
            ]);
            $graph[$creativeWork['@id']] = $creativeWork;
        }

        if ($type === 'Product' && (!empty($sch['product']) || !empty($sch['name']) || !empty($title))) {
            $p = !empty($sch['product']) && is_array($sch['product']) ? $sch['product'] : $sch;
            $hasPrice = array_key_exists('price', $p) && $this->hasValue($p['price']);
            $prod = $this->compact([
                '@type' => 'Product',
                '@id' => $id('product'),
                'name' => $p['name'] ?? $title,
                'description' => $p['description'] ?? $desc,
                'image' => !empty($p['images'])
                    ? array_map(fn($u) => ['@type' => 'ImageObject', 'url' => $u], $p['images'])
                    : ($image ? [['@type' => 'ImageObject', 'url' => $image]] : null),
                'sku' => $p['sku'] ?? null,
                'brand' => !empty($p['brand']) ? ['@type' => 'Brand', 'name' => $p['brand']] : null,
                'offers' => $hasPrice ? [
                    '@type' => 'Offer',
                    'price' => $p['price'],
                    'priceCurrency' => $p['currency'] ?? 'USD',
                    'availability' => $p['availability'] ?? 'https://schema.org/InStock',
                    'url' => $pageUrl,
                ] : null,
            ]);
            $graph[$prod['@id']] = $prod;
        }

        if ($type === 'SoftwareApplication' && (!empty($sch['software']) || !empty($sch['name']) || !empty($title))) {
            $s = !empty($sch['software']) && is_array($sch['software']) ? $sch['software'] : $sch;
            $offersNode = null;

            if (!empty($s['offers']) && is_array($s['offers'])) {
                $offersList = array_values(array_map(function($o) use ($pageUrl) {
                    return $this->compact([
                        '@type' => 'Offer',
                        'name' => $o['name'] ?? null,
                        'price' => $o['price'] ?? null,
                        'priceCurrency' => $o['currency'] ?? 'USD',
                        'availability' => $o['availability'] ?? null,
                        'url' => $o['url'] ?? $pageUrl,
                    ]);
                }, $s['offers']));

                $pricedOffers = array_values(array_filter($s['offers'], fn($o) => is_array($o) && array_key_exists('price', $o) && $this->hasValue($o['price'])));
                $prices = array_map(fn($o) => (float)$o['price'], $pricedOffers);
                $low = $prices ? min($prices) : null;
                $high = $prices ? max($prices) : null;
                $currency = $pricedOffers[0]['currency'] ?? ($s['offers'][0]['currency'] ?? 'USD');

                $offersNode = $this->compact([
                    '@type' => 'AggregateOffer',
                    'lowPrice' => $low !== null ? (string)$low : null,
                    'highPrice' => $high !== null ? (string)$high : null,
                    'priceCurrency' => $currency,
                    'offerCount' => $offersList ? (string)count($offersList) : null,
                    'offers' => $offersList,
                ]);
            } elseif (array_key_exists('price', $s) && $this->hasValue($s['price'])) {
                $offersNode = [
                    '@type' => 'Offer',
                    'price' => $s['price'],
                    'priceCurrency' => $s['currency'] ?? 'USD',
                    'url' => $pageUrl,
                ];
            }

            $app = $this->compact([
                '@type' => 'SoftwareApplication',
                '@id' => $id('app'),
                'name' => $s['name'] ?? $title,
                'applicationCategory' => $s['category'] ?? 'BusinessApplication',
                'operatingSystem' => $s['os'] ?? 'Web',
                'url' => $pageUrl,
                'publisher' => ['@id' => $siteUrl . '#organization'],
                'offers' => $offersNode,
                'aggregateRating' => !empty($s['aggregateRating']) ? $this->compact([
                    '@type' => 'AggregateRating',
                    'ratingValue' => $s['aggregateRating']['ratingValue'] ?? null,
                    'reviewCount' => $s['aggregateRating']['reviewCount'] ?? null,
                ]) : null,
            ]);
            $graph[$app['@id']] = $app;
        }

        if (!empty($sch['faq'])) {
            $graph[$id('faq')] = [
                '@type' => 'FAQPage',
                '@id' => $id('faq'),
                'mainEntityOfPage' => ['@id' => $id('webpage')],
                'mainEntity' => array_map(fn($qa) => [
                    '@type' => 'Question',
                    'name' => $qa['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa['a']],
                ], $sch['faq']),
            ];
        }

        if (!empty($sch['breadcrumbs'])) {
            $bc = [
                '@type' => 'BreadcrumbList',
                '@id' => $id('breadcrumbs'),
                'itemListElement' => array_values(array_map(function($crumb, $i) {
                    return [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $crumb['name'],
                        'item' => $crumb['url'],
                    ];
                }, $sch['breadcrumbs'], array_keys($sch['breadcrumbs']))),
            ];
            $graph[$bc['@id']] = $bc;
        }

        if (!empty($sch['entities']) && is_array($sch['entities'])) {
            foreach ($sch['entities'] as $index => $entity) {
                if (!is_array($entity) || empty($entity['@type'])) {
                    continue;
                }

                if (empty($entity['@id'])) {
                    $entity['@id'] = $id('entity-' . ($index + 1));
                }

                $graph[$entity['@id']] = $entity;
            }
        }

        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => array_values($graph)],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        return is_string($json) ? $json : '';
    }

    private function compact(array $values): array {
        return array_filter($values, static fn($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function hasValue(mixed $value): bool {
        return $value !== null && $value !== '';
    }
}
