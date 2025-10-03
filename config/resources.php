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
    'providers' => [
        'gnd' => [
            'label' => 'GND', // not localized, as it is a standard identifier
            'api-type' => 'Gnd',
            'base_url' => 'https://lobid.org/gnd/',
            'target_url' => 'https://d-nb.info/gnd/{provider_id}', // For saved links
            'test_search' => 'Hannah Arendt',
            'wikidata_property' => 'P227', // For syncing from Wikidata
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
        ],
        'georgfischer' => [
            'label' => [
                'de' => 'Konzernarchiv der Georg Fischer AG',
                'en' => 'Corporate Archives of Georg Fischer Ltd',
            ],
            'api-type' => 'Anton',
            'base_url' => 'https://archives.georgfischer.com/api/',
            'api_token' => env('GEORGFISCHER_API_TOKEN', ''),
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
            'target_url' => 'https://gosteli.anton.ch/api/{endpoint}/{short_provider_id}',
            'slug' => 'gosteli',
            'test_search' => 'Marthe Gosteli',
        ],
        'idiotikon' => [
            'label' => 'Idiotikon',
            'api-type' => 'Idiotikon',
            'base_url' => 'https://api.idiotikon.ch/',
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
            'target_url' => 'https://kba.karl-barth.ch/api/{endpoint}/{short_provider_id}',
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
            'base_url' => 'https://metagrid.ch/api/',
            'target_url' => 'https://api.metagrid.ch/concordance/{provider_id}.json', // since metagrid has no Gui for these entries
            'test_search' => 'Anna Tumarkin',
        ],
        'ortsnamen' => [
            'label' => 'ortsnamen.ch',
            'api-type' => 'Ortsnamen',
            'base_url' => 'https://search.ortsnamen.ch/de/api/',
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
        ],
        'huygens' => [
            'label' => 'Huygens Instituut',
        ],
        'idref' => [
            'label' => 'IdRef',
            'wikidata_property' => 'P269',
        ],
        'kalliope-verbund' => [
            'label' => 'Kallipope Verbund',
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
