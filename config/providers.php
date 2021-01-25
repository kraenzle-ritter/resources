<?php

return [
    'alfred-escher' => [
        'name' => 'alfred-escher',
        'description' => ['de' => 'Alfred Escher Briefedition'],
        'api-url-pattern' => '',
        'url-pattern' => 'http://www.briefedition.alfred-escher.ch/kontexte/personen/korrespondenten-und-erwahnte-personen/{ID}',
        'wikidata-property' => '',
        'comment' => '',
    ],
    'bsg' => [
        'name' => 'bsg',
        'description' => ['de' => 'Bibliographie der Schweizergeschichte Datenbank (1975-)'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://www.bsg.nb.admin.ch/discovery/search?query=lds50,contains,{ID}&vid=41SNL_54_INST:bsg',
        'wikidata-property' => '',
        'comment' => 'uses gnd-id'
    ],
    'burgerbibliothek' => [
        'name' => 'burgerbibliothek',
        'description' => ['de' => 'Burgerbbliothek'],
        'api-url-pattern' => '',
        'url-pattern' => 'http://katalog.burgerbib.ch/parametersuche.aspx?DeskriptorId={ID}',
        'wikidata-property' => '',
        'comment' => '',
        'beacon' => 'https://api.metagrid.ch/beacon/burgerbibliothek'
    ],
    'dodis' => [
        'name' => 'dodis',
        'description' => ['de' => 'Diplomatische Dokumente der Schweiz'],
        'regex' => '[PG]\d+',
        'api-url-pattern' => '',
        'url-pattern' => 'https://dodis.ch/{ID}',
        'wikidata-property' => 'P701',
        'comment' => 'Achtung ID im Beacon ist ungleich ID im URL Pattern (mal mit P|G mal ohne)',
        'beacon' => 'https://api.metagrid.ch/beacon/dodis'
    ],
    'elites-suisses-au-xxe-siecle' => [
        'name' => 'elites-suisses-au-xxe-siecle',
        'description' => ['de' => 'Base de données des élites suisses'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://www2.unil.ch/elitessuisses/index.php?page=detailPerso&idIdentite={ID}',
        'wikidata-property' => '',
        'comment' => ''
    ],
    'ethz' => [
        'name' => 'ethz',
        'description' => ['de' => 'Archivdatenbank des Hochschularchivs der ETH Zürich'],
        'api-url-pattern' => '',
        'url-pattern' => 'http://archivdatenbank-online.ethz.ch/hsa/#/search?q={ID}',
        'wikidata-property' => '',
        'comment' => ''
    ],
    'fotostiftung' => [
        'name' => 'fotostiftung',
        'description' => ['de' => 'Fotostiftung Schweiz'],
        'api-url-pattern' => '',
        'locales' => ['de', 'fr', 'it', 'en']
        'url-pattern' => 'https://www.fotostiftung.ch/{LOCALE}/nc/index-der-fotografinnen/fotografin/cumulus/{ID}/0/show/',
        'wikidata-property' => '',
        'comment' => ''
    ],
    'geonames' => [
        'name' =>  'geonames',
        'description' => ['de' => 'Geonames'],
        'regex' => '[1-9][0-9]{0,8}|',
        'api-url-pattern' => '',
        'url-pattern' => 'https://www.geonames.org/{ID}',
        'wikidata-property' => 'P1566',
        'comment' => ''
     ],
    'gnd' => [
        'name' => 'gnd',
        'description' => ['de' => 'GND'],
        'api-url-pattern' => 'https://lobid.org/gnd/{ID}.json',
        'url-pattern' => 'http://d-nb.info/gnd/{ID}',
        'wikidata-property' => 'P227',
        'comment' => ''
    ],
     'hallernet' => [
        'name' => 'hallernet',
        'description' => ['de' => 'hallerNet'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://hallernet.org/data/person/{ID}',
        'wikidata-property' => '',
        'comment' => ''
     ],
     'helveticat' => [
        'name' =>  'helveticat',
        'description' => ['de' => 'Helveticat'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://www.helveticat.ch/discovery/search?query=lds50,contains,{ID}&vid=41SNL_51_INST:helveticat',
        'wikidata-property' => '',
        'comment' => 'uses gnd-id'
     ],
     'histoirerurale' => [
        'name' =>  'histoirerurale',
        'description' => ['de' => 'Archiv für Argrargeschichte'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://www.histoirerurale.ch/pers/personnes/{ID}.html',
        'wikidata-property' => '',
        'comment' => ''
     ],
     'hls-dhs-dss' => [
        'name' =>  'hls-dhs-dss',
        'description' => ['de' => 'Historisches Lexikon der Schweiz'],
        'locales' => ['de', 'fr', 'it'],
        'regex' => '^\d{6}$',
        'api-url-pattern' => '',
        'url-pattern' => 'https://hls-dhs-dss.ch/{LOCALE}/articles/{ID}',
        'wikidata-property' => 'P902',
        'comment' => '',
        'beacon' => [
            'prefix' => 'http://d-nb.info/gnd/',
            'url' => 'https://api.metagrid.ch/beacon/hls-dhs-dss'
        ]
     ],
     'wikipedia-de' => [
        'name' =>  'wikipedia-de',
        'description' => ['de' => 'Wikipedia (deutsch)'],
        'api-url-pattern' => '',
        'url-pattern' => 'https://de.wikipedia.org/?curid={ID}',
        'wikidata-property' => '',
        'comment' => 'use the pageid or would it be better to take the title?',
     ]



];
