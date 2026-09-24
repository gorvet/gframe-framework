<?php
class JsonLD {

public function renderSchema($schema,$metaTags,$routeParams): string {

    if (empty($schema)) return '';

    $mt   = $metaTags ?? [];
    $sch  = $schema;

    $siteUrl = rtrim(site_url, '/');
    $pageUrl = rtrim(($routeParams['currentURL'] ?? ''), '/');
    if ($pageUrl === '') $pageUrl = $siteUrl; // ✅ fallback seguro

    $siteName = $sch['siteName'] ?? ($mt['ogsite_name'] ?? ($mt['title'] ?? parse_url($siteUrl, PHP_URL_HOST)));
    $title    = $sch['title'] ?? ($mt['title'] ?? null);
    $desc     = $sch['description'] ?? ($mt['description'] ?? null);
    $image    = $sch['image'] ?? ($sch['webpage']['primaryImageOfPage']['url'] ?? ($mt['ogimage'] ?? null));

    // ✅ Normaliza a BCP-47 (es-ES, en-US, o solo 'es')
    $langRaw  = $sch['lang'] ?? ($routeParams['lang'] ?? ($mt['oglocale'] ?? 'es'));
    $langRaw  = str_replace('_','-',$langRaw);
    if (preg_match('/^[a-z]{2}$/', $langRaw)) {
        $lang = $langRaw;
    } elseif (preg_match('/^[a-z]{2}-[A-Za-z]{2}$/', $langRaw)) {
        $lang = strtolower(substr($langRaw,0,2)).'-'.strtoupper(substr($langRaw,3,2));
    } else {
        $lang = 'es';
    }

    $type = $sch['type'] ?? 'WebPage';
    $id   = fn(string $frag) => $pageUrl.'#'.$frag;

    $graph = [];

    // Organization (con logo y sameAs opcionales desde schema['org'])
    $orgData   = $sch['org'] ?? [];
    $orgName   = $orgData['name'] ?? $siteName;
    $orgUrl    = $orgData['url'] ?? $siteUrl;
    $logoUrl   = $orgData['logo'] ?? null;
    $sameAsArr = [];
    if (!empty($orgData['sameAs'])) {
        $sameAsArr = is_array($orgData['sameAs']) ? array_values(array_filter($orgData['sameAs'])) : [$orgData['sameAs']];
        // opcional: solo https
        $sameAsArr = array_values(array_filter($sameAsArr, fn($u)=> is_string($u) && str_starts_with($u,'http')));
    }

    $org = array_filter([
        '@type'  => 'Organization',
        '@id'    => $siteUrl.'#organization',
        'name'   => $orgName,
        'url'    => $orgUrl,
        'logo'   => $logoUrl ? ['@type'=>'ImageObject', 'url'=>$logoUrl] : null,
        'sameAs' => $sameAsArr ?: null,
    ]);
    $graph[$org['@id']] = $org;

    // WebSite (con SearchAction opcional)
    $ws = array_filter([
        '@type'      => 'WebSite',
        '@id'        => $siteUrl.'#website',
        'url'        => $siteUrl,
        'name'       => $siteName,
        'inLanguage' => $lang,
        'publisher'  => ['@id' => $siteUrl.'#organization'],
        'potentialAction' =>
            (!empty($sch['search']['target']) &&
             strpos($sch['search']['target'], '{search_term_string}') !== false)
            ? [
                '@type'       => 'SearchAction',
                'target'      => $sch['search']['target'],
                'query-input' => 'required name=search_term_string',
              ]
            : null,
    ]);
    $graph[$ws['@id']] = $ws;

    // WebPage
    $types = ['WebPage'];
    if ($type === 'CollectionPage') $types[] = 'CollectionPage';
    if ($type === 'ContactPage')    $types[] = 'ContactPage';

    $wp = array_filter([
        '@type'        => $types,
        '@id'          => $id('webpage'),
        'url'          => $pageUrl,
        'name'         => $title,
        'description'  => $desc,
        'inLanguage'   => $lang,
        'isPartOf'     => ['@id' => $siteUrl.'#website'],
        'datePublished'=> $sch['datePublished'] ?? null,
        'dateModified' => $sch['dateModified'] ?? null,
    ]);
    if ($image) {
        $wp['image'] = [[ '@type'=>'ImageObject', '@id'=>$id('primaryimage'), 'url'=>$image ]];
    }
    $graph[$wp['@id']] = $wp;

    // ✅ Nodo explícito de la imagen principal (para referencias por @id)
    if ($image) {
        $graph[$id('primaryimage')] = [
            '@type' => 'ImageObject',
            '@id'   => $id('primaryimage'),
            'url'   => $image
        ];
    }

    // Article
    if (in_array($type, ['Article', 'BlogPosting', 'NewsArticle', 'TechArticle'], true)) {
        $article = array_filter([
            '@type'             => $type,
            '@id'               => $id('article'),
            'headline'          => $title,
            'mainEntityOfPage'  => ['@id'=>$id('webpage')],
            'image'             => $image ? ['@id'=>$id('primaryimage')] : null,
            'datePublished'     => $sch['datePublished'] ?? null,
            'dateModified'      => $sch['dateModified'] ?? null,
            'author'            => !empty($sch['author']) ? [['@type'=>'Person','name'=>$sch['author']]] : (!empty($mt['author']) ? [['@type'=>'Person','name'=>$mt['author']]] : null),
            'publisher'         => ['@id'=>$siteUrl.'#organization'],
        ]);
        $graph[$article['@id']] = $article;
    }

    // Event
    if ($type === 'Event' || !empty($sch['event'])) {
        $e = !empty($sch['event']) && is_array($sch['event']) ? $sch['event'] : $sch;
        $event = array_filter([
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
            'organizer' => !empty($e['organizer']) ? $e['organizer'] : ['@id' => $siteUrl.'#organization'],
            'offers' => !empty($e['offers']) ? $e['offers'] : null,
        ]);
        $graph[$event['@id']] = $event;
    }

    // Service
    if ($type === 'Service' || !empty($sch['service'])) {
        $serviceData = !empty($sch['service']) && is_array($sch['service']) ? $sch['service'] : $sch;
        $service = array_filter([
            '@type' => 'Service',
            '@id' => $id('service'),
            'name' => $serviceData['name'] ?? $title,
            'description' => $serviceData['description'] ?? $desc,
            'provider' => $serviceData['provider'] ?? ['@id' => $siteUrl.'#organization'],
            'areaServed' => $serviceData['areaServed'] ?? null,
            'offers' => $serviceData['offers'] ?? null,
            'serviceType' => $serviceData['serviceType'] ?? null,
        ]);
        $graph[$service['@id']] = $service;
    }

    // LocalBusiness
    if ($type === 'LocalBusiness' || !empty($sch['business'])) {
        $businessData = !empty($sch['business']) && is_array($sch['business']) ? $sch['business'] : $sch;
        $business = array_filter([
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

    // Course
    if ($type === 'Course' || !empty($sch['course'])) {
        $courseData = !empty($sch['course']) && is_array($sch['course']) ? $sch['course'] : $sch;
        $course = array_filter([
            '@type' => 'Course',
            '@id' => $id('course'),
            'name' => $courseData['name'] ?? $title,
            'description' => $courseData['description'] ?? $desc,
            'provider' => $courseData['provider'] ?? ['@id' => $siteUrl.'#organization'],
            'url' => $courseData['url'] ?? $pageUrl,
        ]);
        $graph[$course['@id']] = $course;
    }

    // JobPosting
    if ($type === 'JobPosting' || !empty($sch['job'])) {
        $jobData = !empty($sch['job']) && is_array($sch['job']) ? $sch['job'] : $sch;
        $job = array_filter([
            '@type' => 'JobPosting',
            '@id' => $id('job'),
            'title' => $jobData['title'] ?? $title,
            'description' => $jobData['description'] ?? $desc,
            'datePosted' => $jobData['datePosted'] ?? null,
            'validThrough' => $jobData['validThrough'] ?? null,
            'employmentType' => $jobData['employmentType'] ?? null,
            'hiringOrganization' => $jobData['hiringOrganization'] ?? ['@id' => $siteUrl.'#organization'],
            'jobLocation' => $jobData['jobLocation'] ?? null,
            'baseSalary' => $jobData['baseSalary'] ?? null,
            'applicantLocationRequirements' => $jobData['applicantLocationRequirements'] ?? null,
            'directApply' => $jobData['directApply'] ?? null,
        ]);
        $graph[$job['@id']] = $job;
    }

    // VideoObject
    if ($type === 'VideoObject' || !empty($sch['video'])) {
        $videoData = !empty($sch['video']) && is_array($sch['video']) ? $sch['video'] : $sch;
        $video = array_filter([
            '@type' => 'VideoObject',
            '@id' => $id('video'),
            'name' => $videoData['name'] ?? $title,
            'description' => $videoData['description'] ?? $desc,
            'thumbnailUrl' => $videoData['thumbnailUrl'] ?? ($image ? [$image] : null),
            'uploadDate' => $videoData['uploadDate'] ?? null,
            'duration' => $videoData['duration'] ?? null,
            'contentUrl' => $videoData['contentUrl'] ?? null,
            'embedUrl' => $videoData['embedUrl'] ?? null,
            'publisher' => $videoData['publisher'] ?? ['@id' => $siteUrl.'#organization'],
        ]);
        $graph[$video['@id']] = $video;
    }

    // Recipe
    if ($type === 'Recipe' || !empty($sch['recipe'])) {
        $recipeData = !empty($sch['recipe']) && is_array($sch['recipe']) ? $sch['recipe'] : $sch;
        $recipe = array_filter([
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

    // CreativeWork
    if ($type === 'CreativeWork' || !empty($sch['creativeWork'])) {
        $creativeData = !empty($sch['creativeWork']) && is_array($sch['creativeWork']) ? $sch['creativeWork'] : $sch;
        $creativeWork = array_filter([
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
            'publisher' => $creativeData['publisher'] ?? ['@id' => $siteUrl.'#organization'],
            'image' => $creativeData['image'] ?? ($image ? ['@id' => $id('primaryimage')] : null),
            'text' => $creativeData['text'] ?? null,
            'mainEntityOfPage' => $creativeData['mainEntityOfPage'] ?? ['@id' => $id('webpage')],
        ]);
        $graph[$creativeWork['@id']] = $creativeWork;
    }

    // Product
    if ($type === 'Product' && (!empty($sch['product']) || !empty($sch['name']) || !empty($title))) {
        $p = !empty($sch['product']) && is_array($sch['product']) ? $sch['product'] : $sch;
        $prod = array_filter([
            '@type'       => 'Product',
            '@id'         => $id('product'),
            'name'        => $p['name'] ?? $title,
            'description' => $p['description'] ?? $desc,
            'image'       => !empty($p['images'])
                              ? array_map(fn($u)=>['@type'=>'ImageObject','url'=>$u], $p['images'])
                              : ($image ? [['@type'=>'ImageObject','url'=>$image]] : null),
            'sku'         => $p['sku'] ?? null,
            'brand'       => !empty($p['brand']) ? ['@type'=>'Brand','name'=>$p['brand']] : null,
            'offers'      => !empty($p['price']) ? [
                '@type'         => 'Offer',
                'price'         => $p['price'],
                'priceCurrency' => $p['currency'] ?? 'USD',
                'availability'  => $p['availability'] ?? 'https://schema.org/InStock',
                'url'           => $pageUrl
            ] : null,
        ]);
        $graph[$prod['@id']] = $prod;
    }

    // SoftwareApplication (1 precio o varios planes)
    if ($type === 'SoftwareApplication' && (!empty($sch['software']) || !empty($sch['name']) || !empty($title))) {
        $s = !empty($sch['software']) && is_array($sch['software']) ? $sch['software'] : $sch;

        // offers
        $offersNode = null;
        if (!empty($s['offers']) && is_array($s['offers'])) {
            $offersList = array_values(array_map(function($o) use ($pageUrl) {
                return array_filter([
                    '@type'         => 'Offer',
                    'name'          => $o['name'] ?? null,
                    'price'         => $o['price'] ?? null,
                    'priceCurrency' => $o['currency'] ?? 'USD',
                    'availability'  => $o['availability'] ?? null,
                    'url'           => $o['url'] ?? $pageUrl,
                ]);
            }, $s['offers']));
            $prices   = array_map(fn($o)=>(float)($o['price'] ?? 0), $s['offers']);
            $low      = $prices ? min($prices) : null;
            $high     = $prices ? max($prices) : null;
            $currency = $s['offers'][0]['currency'] ?? 'USD';

            $offersNode = array_filter([
                '@type'         => 'AggregateOffer',
                'lowPrice'      => isset($low)  ? (string)$low  : null,
                'highPrice'     => isset($high) ? (string)$high : null,
                'priceCurrency' => $currency,
                'offerCount'    => $offersList ? (string)count($offersList) : null, // ✅ útil
                'offers'        => $offersList,
            ]);
        } elseif (!empty($s['price'])) {
            $offersNode = [
                '@type'         => 'Offer',
                'price'         => $s['price'],
                'priceCurrency' => $s['currency'] ?? 'USD',
                'url'           => $pageUrl,
            ];
        }

        $app = array_filter([
            '@type'               => 'SoftwareApplication',
            '@id'                 => $id('app'),
            'name'                => $s['name'] ?? $title,
            'applicationCategory' => $s['category'] ?? 'BusinessApplication',
            'operatingSystem'     => $s['os'] ?? 'Web',
            'url'                 => $pageUrl, // ✅ añade url del recurso
            'publisher'           => ['@id' => $siteUrl.'#organization'],
            'offers'              => $offersNode,
            'aggregateRating'     => !empty($s['aggregateRating']) ? array_filter([
                '@type'       => 'AggregateRating',
                'ratingValue' => $s['aggregateRating']['ratingValue'] ?? null,
                'reviewCount' => $s['aggregateRating']['reviewCount'] ?? null,
            ]) : null,
        ]);
        $graph[$app['@id']] = $app;
    }

    // FAQ (nodo aparte, enlazado a la WebPage)
    if (!empty($sch['faq'])) {
        $graph[$id('faq')] = [
            '@type'             => 'FAQPage',
            '@id'               => $id('faq'),
            'mainEntityOfPage'  => ['@id' => $id('webpage')],
            'mainEntity'        => array_map(fn($qa) => [
                '@type'=>'Question','name'=>$qa['q'],
                'acceptedAnswer'=>['@type'=>'Answer','text'=>$qa['a']]
            ], $sch['faq']),
        ];
    }

    // Breadcrumbs
    if (!empty($sch['breadcrumbs'])) {
        $bc = [
            '@type' => 'BreadcrumbList',
            '@id'   => $id('breadcrumbs'),
            'itemListElement' => array_values(array_map(function($crumb, $i){
                return [
                    '@type'   => 'ListItem',
                    'position'=> $i+1,
                    'name'    => $crumb['name'],
                    'item'    => $crumb['url']
                ];
            }, $sch['breadcrumbs'], array_keys($sch['breadcrumbs']))),
        ];
        $graph[$bc['@id']] = $bc;
    }

    // Entidades extra totalmente custom (ampliacion abierta)
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
        ['@context'=>'https://schema.org','@graph'=>array_values($graph)],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    return $json;
}
}
