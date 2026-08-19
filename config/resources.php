<?php

return [
    /**
     * Database table name for the resources
     */
    'table' => 'resources',

    /**
     * Default limit for search results
     */
    'limit' => 5,

    /**
     * User-Agent sent with every provider request. Wikimedia and lobid require
     * a descriptive one. Resolved here rather than in the helper so it keeps
     * working under `php artisan config:cache`, where env() returns null.
     */
    'user_agent' => env(
        'RESOURCES_USER_AGENT',
        'resources/' . (class_exists(\Composer\InstalledVersions::class)
            ? (\Composer\InstalledVersions::getPrettyVersion('kraenzle-ritter/resources') ?? 'dev')
            : 'dev') . ' (+https://github.com/kraenzle-ritter/resources)'
    ),
    /**
     * ResourceSyncService behaviour.
     *
     * cache_ttl covers the Wikidata SPARQL lookup of provider URL patterns
     * (P1630). The answer changes on the order of months; caching it removes a
     * network round trip from every resource save.
     */
    'sync' => [
        'cache_ttl' => 86400,
    ],

    /**
     * The /resources-check diagnostics UI.
     *
     * Disabled by default: the pages render provider configuration, so they
     * should not become publicly reachable routes as a side effect of
     * `composer require`. Enable per environment, and tighten the middleware
     * if the host application has an admin gate.
     */
    'diagnostics' => [
        'enabled' => env('RESOURCES_DIAGNOSTICS', false),
        'middleware' => ['web'],
    ],

    /**
     * URL schemes a stored resource url may use. Anything else becomes a script
     * vector once rendered into an href.
     */
    'allowed_url_schemes' => ['http', 'https'],

    'providers' => [
        'gnd' => [
            'label' => 'GND', // not localized, as it is a standard identifier
            'api-type' => 'Gnd',
            'base_url' => 'https://lobid.org/gnd/',
            'target_url' => 'https://d-nb.info/gnd/{provider_id}', // For saved links
            'test_search' => 'Hannah Arendt',
            'wikidata_property' => 'P227', // For syncing from Wikidata
            // HTTP timeout settings
            'timeout' => 15, // Request timeout in seconds
            'connect_timeout' => 5, // Connection timeout in seconds
        ],
        'geonames' => [
            'label' => "GeoNames", // not localized as it is a standard identifier
            'api-type' => 'Geonames',
            'base_url' => 'http://api.geonames.org/',
            'user_name' => env('GEONAMES_USERNAME', 'demo'),
            // Standardized configuration keys with underscores:
            'continent_code' => null, // Restricts the search for toponym of the given continent
            'country_bias' => null,   // Records from the countryBias are listed first
            'target_url' => 'https://www.geonames.org/{provider_id}',
            'test_search' => 'Augsburg',
            'wikidata_property' => 'P1566', // For syncing from Wikidata
            // HTTP timeout settings
            'timeout' => 15, // Request timeout in seconds
            'connect_timeout' => 5, // Connection timeout in seconds
            'retry_attempts' => 2, // Number of retry attempts on failure
            'retry_delay' => 1000, // Delay between retries in milliseconds
        ],
        'georgfischer' => [
            'label' => [
                'de' => 'Konzernarchiv der Georg Fischer AG',
                'en' => 'Corporate Archives of Georg Fischer Ltd',
            ],
            'api-type' => 'Anton',
            'base_url' => 'https://archives.georgfischer.com/api/',
            'api_token' => env('GEORGFISCHER_API_TOKEN', ''),
            // 'header' sends Authorization: Bearer <token>; set to 'query'
            // to fall back to the api_token query parameter.
            'api_token_transport' => 'header',
            'target_url' => 'https://archives.georgfischer.com/{endpoint}/{short_provider_id}',
            'slug' => 'gfa',
            'test_search' => 'Georg Fischer',
        ],
        'gosteli' => [
            'label' => [
                'de' => 'Gosteli Archiv',
                'en' => 'Gosteli Archive',
                'fr' => 'Archives Gosteli',
                'it' => 'Archivio Gosteli',
            ],
            'api-type' => 'Anton',
            'base_url' => 'https://gosteli.anton.ch/api/',
            'api_token' => env('GOSTELI_API_TOKEN', ''),
            // 'header' sends Authorization: Bearer <token>; set to 'query'
            // to fall back to the api_token query parameter.
            'api_token_transport' => 'header',
            'target_url' => 'https://gosteli.anton.ch/{endpoint}/{short_provider_id}',
            'slug' => 'gosteli',
            'test_search' => 'Marthe Gosteli',
        ],
        'idiotikon' => [
            'label' => 'Idiotikon',
            'api-type' => 'Idiotikon',
            // api.idiotikon.ch does not resolve; the working host is digital.*
            'base_url' => 'https://digital.idiotikon.ch/api/',
            'target_url' => 'https://digital.idiotikon.ch/p/lem/{provider_id}',
            'test_search' => 'Allmend',
        ],
        'kba' => [
            'label' => [
                'de' => 'Karl Barth-Archiv',
                'en' => 'Karl Barth Archive',
                'fr' => 'Archives Karl Barth',
                'it' => 'Archivio Karl Barth',
            ],
            'api-type' => 'Anton',
            'base_url' => 'https://kba.karl-barth.ch/api/',
            'api_token' => env('KBA_API_TOKEN', ''),
            // 'header' sends Authorization: Bearer <token>; set to 'query'
            // to fall back to the api_token query parameter.
            'api_token_transport' => 'header',
            'target_url' => 'https://kba.karl-barth.ch/{endpoint}/{short_provider_id}',
            'slug' => 'kba',
            'test_search' => 'Karl Barth',
        ],
        'manual-input' => [
            'label' => [
                'en' => 'Manual Input',
                'de' => 'Manuelle Eingabe',
                'fr' => 'Saisie manuelle',
                'it' => 'Inserimento manuale',
            ],
            'api-type' => 'ManualInput',
        ],
        'metagrid' => [
            'label' => 'Metagrid',
            'api-type' => 'Metagrid',
            // metagrid.ch/api/ answers 404; the API lives on api.metagrid.ch
            'base_url' => 'https://api.metagrid.ch/',
            'target_url' => 'https://api.metagrid.ch/concordance/{provider_id}.json', // since metagrid has no Gui for these entries
            'test_search' => 'Anna Tumarkin',
        ],
        'ortsnamen' => [
            'label' => 'ortsnamen.ch',
            'api-type' => 'Ortsnamen',
            // search.ortsnamen.ch redirects here; use the target directly
            'base_url' => 'https://ortsnamen.ch/de/api/',
            'target_url' => 'https://search.ortsnamen.ch/de/record/{provider_id}',
            'test_search' => 'Wiedikon',
            'wikidata_property' => 'P6144', // For syncing from Wikidata
        ],
        'wikidata' => [
            'label' => 'Wikidata',
            'api-type' => 'Wikidata',
            'base_url' => 'https://www.wikidata.org/w/api.php',
            'target_url' => 'https://www.wikidata.org/wiki/{provider_id}',
            'test_search' => 'Lucretia Marinella',
        ],
        'wikipedia-de' => [
            'label' => 'Wikipedia (de)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://de.wikipedia.org/w/api.php',
            'target_url' => 'https://de.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Bertha von Suttner',
        ],
        'wikipedia-en' => [
            'label' => 'Wikipedia (en)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://en.wikipedia.org/w/api.php',
            'target_url' => 'https://en.wikipedia.org/wiki/{provider_id}',
            'test_search' => 'Lucretia Marinella',
        ],
        'wikipedia-fr' => [
            'label' => 'Wikipedia (fr)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://fr.wikipedia.org/w/api.php',
            'target_url' => 'https://fr.wikipedia.org/wiki/{provider_id}',
            'test_search' => 'Lucretia Marinella',
        ],
        'wikipedia-it' => [
            'label' => 'Wikipedia (it)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://it.wikipedia.org/w/api.php',
            'target_url' => 'https://it.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Laura Bassi',
        ],
        'wikipedia-fi' => [
            'label' => 'Wikipedia (fi)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://fi.wikipedia.org/w/api.php',
            'target_url' => 'https://fi.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Lucina Hagman',
        ],
        'wikipedia-da' => [
            'label' => 'Wikipedia (da)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://da.wikipedia.org/w/api.php',
            'target_url' => 'https://da.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Mary Steen',
        ],
        'wikipedia-nl' => [
            'label' => 'Wikipedia (nl)',
            'api-type' => 'Wikipedia',
            'base_url' => 'https://nl.wikipedia.org/w/api.php',
            'target_url' => 'https://nl.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Aletta Jacobs',
        ],
        'wikipedia-sv' => [
            'label' => 'Wikipedia (sv)',
            // the API does not exist?
            'api-type' => 'Wikipedia',
            'base_url' => 'https://sv.wikipedia.org/w/api.php',
            'target_url' => 'https://sv.wikipedia.org/wiki/{underscored_name}',
            'test_search' => 'Sophia Elisabet Brenner',
        ],
        'alfred-escher' => [
            'label' => 'Alfred Escher Briefedition',

        ],
        'bnf' => [
            'label' => 'Bibliothèque nationale de France (BnF)',
            'wikidata_property' => 'P268',
        ],
        'bsg' => [
            'label' => [
                'de' => 'Bibliographie der Schweizergeschichte (BSG)',
                'en' => 'Bibliography of Swiss History (BSH)',
                'fr' => 'Bibliographie de l‘histoire suisse (BHS)',
                'it' => 'Bibliografia della storia svizzera (BSS)',
            ],
        ],
        'burgerbibliothek' => [
            'label' => 'Burgerbibliothek Bern',
        ],
        'catholic-encyclopedia' => [
            'label' => 'Catholic Encyclopedia',
            'wikidata_property' => 'P3241',
        ],
        'ddb' => [
            'label' => 'Deutsche Digitale Bibliothek (DDB)',
            'wikidata_property' => 'P13049',
        ],
        'deutsche-biographie' => [
            'label' => 'Deutsche Biographie',
            'wikidata_property' => 'P7902',
        ],
        'diju' => [
            'label' => [
                'de' => 'Lexikon des Jura',
                'fr' => 'Dictionaire du Jura',
            ],
        ],
        'dodis' => [
            'label' => 'Dodis'
        ],
        'dwds' => [
            'label' => 'DWDS (Digitales Wörterbuch der deutschen Sprache)',
        ],
        'elites-suisses-au-xxe-siecle' => [
            'label' => 'Elites suisses au XXème siècle',
        ],
        'encyclopaedia-britannica-online' => [
            'label' => 'Encyclopaedia Britannica Online',
            'wikidata_property' => 'P1417'
        ],
        'e-rara' => [
            'label' => 'e-rara',
        ],
        'ethz' => [
            'label' => [
                'de' => 'ETH Zürich (Hochschularchiv)',
                'en' => 'ETH Zurich (University Archives)'
            ],
        ],
        'europeana' => [
            'label' => 'Europeana',
            'wikidata_property' => 'P7704',
        ],
        'familienlexikon' => [
            'label' => 'Familienlexikon der Schweiz',
        ],
        'fotoch' => [
            'label' => 'fotoCH',
        ],
        'fotostiftung' => [
            'label' => 'Fotostiftung Schweiz',
        ],
        'hallernet' => [
            'label' => 'HallerNet',
        ],
        'helveticat' => [
            'label' =>  'Helveticat',
            'wikidata_property' => 'P12899',
        ],
        'hfls' => [
            'label' => 'Historisches Familienlexikon der Schweiz (HLFS)',
        ],
        'histhub' => [
            'label' => 'Histhub',
        ],
        'histoirerurale' => [
            'label' => [
                'de' => 'Archiv für Argrargeschichte',
                'en' => 'Archives of rural history',
                'fr' => 'Archives de l‘histoire rurale',
            ],
        ],
        'hls-dhs-dss' => [
            'label' => [
                'de' => 'Historisches Lexikon der Schweiz (HLS)',
                'en' => 'Historical Dictionary of Switzerland',
                'fr' => 'Dictionnaire historique de la Suisse (DHS)',
                'it' => 'Dizionario storico della Svizzera (DSS)',
            ],
            'wikidata_property' => 'P902',
            'target_url' => 'https://hls-dhs-dss.ch/{locale}/articles/{provider_id}',
            'locale' => 'de', // Default locale for HLS URLs (de, fr, it)
        ],
        'huygens' => [
            'label' => 'Huygens Instituut',
        ],
        'idref' => [
            'label' => 'IdRef',
            'api-type' => 'IdRef',
            // The core paths in the older ABES docs (/Sru/Solr/sudoc/select)
            // answer 404; this is the endpoint that works.
            'base_url' => 'https://www.idref.fr/Sru/Solr',
            'target_url' => 'https://www.idref.fr/{provider_id}',
            'test_search' => 'Karl Barth',
            'wikidata_property' => 'P269',
            'timeout' => 15,
            'connect_timeout' => 5,
            // Exact-phrase boost over the loose clause; see src/IdRef.php.
            'phrase_boost' => 10,
            // IdRef record types, with the Solr index that searches each.
            // `code` is the value of recordtype_z on a result.
            'record_types' => [
                'person' => ['code' => 'a', 'index' => 'persname_t'],
                'corporate' => ['code' => 'b', 'index' => 'corpname_t'],
                'place' => ['code' => 'c', 'index' => 'geogname_t'],
                'family' => ['code' => 'e', 'index' => 'famname_t'],
                'subject' => ['code' => 'j', 'index' => 'subjectheading_t'],
            ],
            'default_record_types' => ['person', 'corporate'],
            // Endpoint names differ per application (Anton: actors/places/
            // keywords; KB adds songs/bibls), so the mapping lives in config
            // and an unmapped endpoint falls back to default_record_types.
            'endpoint_record_types' => [
                'actors' => ['person', 'corporate', 'family'],
                'places' => ['place'],
                'keywords' => ['subject'],
            ],
        ],
        'kalliope-verbund' => [
            'label' => 'Kalliope Verbund',
            'wikidata_property' => 'P9964',
        ],
        'kartenportal.ch' => [
            'label' => 'Kartenportal.ch',
        ],
        'kbga' => [
            'label' => 'Karl Barth-Gesamtausgabe (KBGA)',
        ],
        'lavater' => [
            'label' => 'Lavater Briefwechsel'
        ],
        'lcnaf' => [
            'label' => 'Library of Congress (LCNAF)',
            'wikidata_property' => 'P244',
        ],
        'lonsea' => [
            'label' => 'Lonsea',
            'full-label' => 'League of nations search engine',
            'wikidata_property' => 'P5306',
        ],
        'mcclintock-and-strong-biblical-cyclopedia' => [
            'label' => 'McClintock and Strong Biblical Cyclopedia',
            'wikidata_property' => 'P8636',
        ],
        'munzinger-person' => [
            'label' => 'Munzinger Online',
            'wikidata_property' => 'P1284',
        ],
        'oesterreichisches-biographisches-lexikon' => [
            'label' => 'Österreichisches Biographisches Lexikon',
        ],
        'okumenisches-heiligenlexikon' => [
            'label' => 'Ökumenisches Heiligenlexikon',
            'wikidata_property' => 'P8080',
        ],
        'oxford-dnb' => [
            'label' => 'Oxford Dictionary of National Biography',
            'wikidata_property' => 'P1415',
        ],
        'parlamentch' => [
            'label' => 'Schweizer Parlament',
        ],
        'perlentaucher' => [
            'label' => 'Perlentaucher',
            'wikidata_property' => 'P866',
        ],
        'pestalozzianum' => [
            'label' => 'Pestalozzianum',
        ],
        'phoebus' => [
            'label' => 'Phoebus',
        ],
        'rag' => [
            'label' => 'RAG (Repertorium Academicum Germanicum)',
            'wikidata_property' => 'P12697',
        ],
        'sbn' => [
            'label' => 'SBN (Servizio Bibliotecario Nazionale)',
            'wikidata_property' => 'P296',
        ],
        'scottish-shale' => [
            'label'  => 'Scottish Shale',
            'full-label' => 'Museum of the Scottish Shale Oil Industry',
        ],
        'sikart' => [
            'label' => 'Sikart',
            'wikidata_property' => 'P781',
        ],
        'smartify' => [
            'label' => 'Smartify',
            'wikidata_property' => 'P9787',
        ],
        'ssrq' => [
            'label' => [
                'de' => 'Schweizerischer Rechtsquellen (SSRQ)',
                'en' => 'Swiss Legal Sources (SLS)',
                'fr' => 'Les sources du droit suisse (SDS)',
                'it' => 'Fonti del diritto svizzero (FDS)',
            ],
        ],
        'stanford-encyclopedia-of-philosophy' => [
            'label' => 'Stanford Encyclopedia of Philosophy',
            'wikidata_property' => 'P3123',
        ],
        'sturzenegger' => [
            'label' => 'Sturzenegger Stiftung',
        ],
        'swa' => [
            'label' => 'Schweizerisches Wirtschaftsarchiv',
        ],
        'viaf' => [
            'label' => 'VIAF (Virtual International Authority File)',
            'wikidata_property' => 'P214',
        ],
        'vitrosearch' => [
            'label' => 'Vitrosearch',
        ],
        'wikimedia-commons' => [
            'label' => 'Wikimedia Commons',
        ],
        'wiktionary' => [
            'label' => 'Wiktionary',
        ],
        'worldcat' => [
            'label' => 'WorldCat',
            'wikidata_property' => 'P5505',
        ]
    ],
    'rename' => [
        'deutsch-biographie' => 'deutsche-biographie',
        'hls' => 'hls-dhs-dss',
        'library of congress' => 'lcnaf',
        'loc' => 'lcnaf',
        'oxford dnb' => 'oxford-dnb',
        'scottish shale' => 'scottish-shale',
        'sturzenegger-stiftung' => 'sturzenegger',
        'sudoc' => 'idref',
        'wikimedia commons' => 'wikimedia-commons',
        'wikipedia' => 'wikipedia-de',
        'wikipedia (en)' => 'wikipedia-en',
        'wikipedia (fr)' => 'wikipedia-fr',
        'wikipedia (nl)' => 'wikipedia-nl',
        'wikipedia (da)' => 'wikipedia-da',
        'wikipedia (fi)' => 'wikipedia-fi',
    ],
];
