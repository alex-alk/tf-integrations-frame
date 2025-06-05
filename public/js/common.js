import { credentialsJson } from '../../credentials.js';

const $softwareSelect = $('select[name="to[System_Software]"]');
const $handleSelect = $('select[name="to[Handle]"]');
const $apiUrl = $('select[name="to[ApiUrl]"]');
const $methodSelect = $('select[name="method"]');
const $apiUsernameInput = $('input[name="to[ApiUsername]"]');
const $apiPasswordInput = $('input[name="to[ApiPassword]"]');
const $apiContextInput = $('input[name="to[ApiContext]"]');
const $apiCodeInput = $('input[name="to[ApiCode]"]');
const $bookingUrlInput = $('input[name="to[BookingUrl]"]');
const $bookingUsernameInput = $('input[name="to[BookingApiUsername]"]');
const $bookingPasswordInput = $('input[name="to[BookingApiPassword]"]');

const systems = {
    'infinitehotel' : {
        'infinitehotel' : 'url1',
        'infinitehotel-demo': 'url2'
    },
    'onetourismo' : {
        'filos_onetourismo' : 'url-one'
    },
    'odeon' : {
        'coral_tour' : 'https://product.coraltravel.ro/EEService.svc/json/processMessage',
        'coraltravel-test' : 'http://testproduct.coraltravel.ro/EEService.svc/json/processMessage'
    },
    'megatec' : {
        'solvex_v2' : 'https://iservice.solvex.bg/IntegrationService.asmx',
        'solvex-test' : 'https://evaluation.solvex.bg/iservice/integrationservice.asmx'
    }
};

// set system software dropdown
const systemValues = [
    'infinitehotel',
    //'cyberlogic',
    'onetourismo',
    'h2b_software',
    'alladyn-hotels',
    'alladyn-charters',
    'apitude',
    'eurosite',
    'amara',
    'etrip-agency',
    'brostravel',
    'odeon',
    'beapi',
    'etrip',
    'travelio',
    'travelio_v2',
    'tourvisio',
    'tourvisio_v2',
    'alladyn_old',
    'goglobal',
    'goglobal_v2',
    'megatec',
    'sejour',
    'sansejour',
    'teztour_v2',
    'calypso',
    'samo',
    'tbo',
    'sphinx',
    'hotelcon',
    'etg',
    'irix',
    'anex'
];

let optionSystem = '';
for (let system in systems) {
    optionSystem += '<option value="' + system + '">' + system + '</option>';
}
// for (let i = 0; i < systemValues.length; i++) {
//     optionSystem += '<option value="' + systemValues[i] + '">' + systemValues[i] + '</option>';
// }
$softwareSelect.html(optionSystem);

// get the first software and populate handles
const firstHandles = systems[Object.keys(systems)[0]];

let option = '';
for (let value in firstHandles) {
    option += '<option value="' + value + '">' + value + '</option>';
}
$handleSelect.html(option);

// get the first handle and populate urls and credentials
populateUrlAndCredentials(Object.keys(systems)[0], Object.keys(firstHandles)[0]);


const handleMap = new Map();

// set handle-url map
const infiniteMap = new Map()
    .set('infinitehotel-demo', 'https://uatapi.infinitehotel.com/gekko-front/ws/v2_4')
    .set('infinitehotel', 'https://api.infinitehotel.com/gekko-front/ws/v2_4');
const cyberlogicMap = new Map()
    .set('filos', 'https://filos-hub.cyberlogic.cloud/services/WebService.asmx');
const onetourismoMap = new Map()
    .set('filos_onetourismo', 'https://api-v2.onetourismo.com');

const h2bMap = new Map()
    .set('h2b-demo', 'https://h2b-demo.travelfuse.ro/api/')
    .set('h2b', 'https://portal.h2b.ro/api/');

const alladynHotelsMap = new Map()
    .set('tez-demo', 'https://api.test.tezhub.com/agent')
    .set('tez', 'https://api.tezhub.com/agent');

const alladynChartersMap = new Map()
    .set('tezc-demo', 'https://api.test.tezhub.com/agent')
    .set('tezc', 'https://api.tezhub.com/agent');

const apitudeMap = new Map()
    .set('hotelbeds', 'https://api.test.hotelbeds.com');

const euroSiteMap = new Map()
    .set('eximtur_v2', 'https://eximtur.touringit.ro/server_xml/server.php')
    .set('paralela45_v2', 'https://rezervari.paralela45.ro/server_xml/server.php')
    .set('aerovacante_v2', 'https://b2b.aerovacante.ro/server_xml/server.php')
    .set('iri_travel_v2', 'https://rezervari.iritravel.ro/server_xml/server.php')
    .set('<?php echo Handles::NOVA_TRAVEL_V2 ?>', 'https://parteneri.travelbrands.ro/server_xml/server.php')
    .set('<?php echo Handles::CISTOUR_V2 ?>', 'https://b2b.cistour.ro/server_xml/server.php')
    .set('<?php echo Handles::LAGUNA_V2 ?>', 'https://rezervari.infosejur.ro/server_xml/server.php')
    .set('<?php echo Handles::BIBI_V2 ?>', 'https://rezervari.tourclick.ro/server_xml/server.php')
    .set('<?php echo Handles::MALTA_TRAVEL_V2 ?>', 'https://newmalta.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::PARADIS_V2 ?>', 'https://rezervari.paradistours.ro/server_xml/server.php')
    .set('<?php echo Handles::DISCOVERY_V2 ?>', 'https://rezervari.discovery-romania.ro/server_xml/server.php')
    .set('<?php echo Handles::EXPERT_TRAVEL_V2 ?>', 'https://experttravel.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::BUSOLA_TRAVEL_V2 ?>', 'https://rezervari.busolatravel.ro/server_xml/server.php')
    .set('<?php echo Handles::INTER_TOUR_V2 ?>', 'https://rezervari.inter-tour.ro/server_xml/server.php')
    .set('<?php echo Handles::TRAMP_TRAVEL_V2 ?>', 'https://rezervari.tramptravel.ro/server_xml/server.php')
    .set('<?php echo Handles::ETALON_V2 ?>', 'https://etalon.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::AEROTRAVEL_V2 ?>', 'https://b2b.aerovacante.ro/server_xml/server.php')
    .set('<?php echo Handles::BUBURUZA_TRAVEL_V2 ?>', 'https://b2b.buburuzatravel.ro/server_xml/server.php')
    .set('<?php echo Handles::TRANSILVANIA_TRAVEL_V2 ?>', 'https://transilvania.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::PREMIO_HOLIDAYS_V2 ?>', 'https://premio.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::ULTRAMARIN_V2 ?>', 'https://ultramarin.touringit.ro/server_xml/server.php')
    .set('<?php echo Handles::DERTOUR_ES_V2 ?>', 'https://parteneri.travelbrands.ro/server_xml/server.php');

const amaraMap = new Map()
    .set('amara_v2', 'https://webapi.amaratour.ro');

const etripAgencyMap = new Map()
    .set('etrip-agency', 'https://sistem.etrip-agency.ro/api');

const brosTravelMap = new Map()
    .set('brostravel_test', 'https://testservices.bros-travel.com')
    .set('brostravel', 'https://services.bros-travel.com');

const odeonTravelMap = new Map()
    .set('coraltravel-test', 'http://testproduct.coraltravel.ro/EEService.svc/json/processMessage')
    .set('coral_tour', 'https://product.coraltravel.ro/EEService.svc/json/processMessage');

const dertourMap = new Map()
    .set('dertour_test', 'https://beapi-test.dertouristik.cz')
    .set('dertour', 'https://beapi.dertouristik.cz');

const etripMap = new Map()
    .set('holidayoffice', 'https://etrip.holidayoffice.ro/api')
    .set('holiday_office_stg', 'https://etripstaging.holidayoffice.ro/api')
    .set('christian_tour', 'https://etripstaging.christiantour.ro/api')
    .set('cocktail_holidays_v2', 'https://etrip.cocktailholidays.ro/api')
    .set('hello_holidays_v2', 'https://etrip.helloholidays.ro/api')
    .set('<?php echo Handles::HELLO_HOLIDAYS_V2_STAGING ?>', 'https://etripstaging.helloholidays.ro/api')
    .set('tui_travel_center_v2', 'https://etrip.tuitravelcenter.ro/api')
    .set('<?php echo Handles::CHRISTIAN_TOUR_V2 ?>', 'https://etrip.christiantour.ro/api')
    .set('<?php echo Handles::MARIO_VIAJES_V2 ?>', 'https://etrip.marioviajes.com/api');

const travelioMap = new Map()
    .set('karpaten-test', 'https://stgwebservice.karpaten.ro/webservice/')
    .set('karpaten', 'https://webservice.karpaten.ro/webservice/');

const travelioV2Map = new Map()
    .set('<?php echo Handles::KARPATEN_V2_STG ?>', 'https://stgwebservice.karpaten.ro/webservice/')
    .set('<?php echo Handles::KARPATEN_V2 ?>', 'https://webservice.karpaten.ro/webservice/');

const megatecMap = new Map()
    .set('solvex-test', 'https://evaluation.solvex.bg/iservice/integrationservice.asmx')
    .set('<?php echo Handles::SOLVEX_V2 ?>', 'https://iservice.solvex.bg/IntegrationService.asmx');

const tourVisioMap = new Map()
    .set('fibula_new', 'https://service.fibula.ro/v2');

const tourVisioV2Map = new Map()
    .set('fibula_v2', 'https://service.fibula.ro/v2')
    .set('rezeda_v2', 'https://service.rezeda.ro/v2');

const alladynOld = new Map().set('teztour', 'https://data.tez-tour.ro/xml/');

const goglobalMap = new Map()
    .set('goglobal', 'https://travelfuse.xml.goglobal.travel/xmlwebservice.asmx?WSDL')
    .set('hotelcon', 'https://argo-travelfuse.xml.hotelcon.travel/xmlwebservice.asmx?WSDL');

const goglobalV2Map = new Map().set('goglobal_v2', 'https://travelfuse.xml.goglobal.travel/xmlwebservice.asmx');

const sejourMap = new Map()
    .set('magellan', 'http://booking.clubmagellan.com/sws/')
    .set('ireles_travel', 'http://88.248.55.246/sws/');

const sansejourMap = new Map()
    .set('clubmagellan', 'http://booking.clubmagellan.com/sws')
    .set('ireles', 'http://88.248.55.246/sws');

const teztourv2Map = new Map().set('teztour_v2', 'https://data.tez-tour.ro/xml/');

const calypsoMap = new Map().set('prestige', 'https://online2.calypsotour.com/export/default.php')
    .set('join_up', 'https://online.joinup.ro/export/default.php');
const samoMap = new Map()
    .set('prestige_test', 'https://devonline2.calypsotour.com/export/default.php')
    .set('prestige_v2', 'https://online2.calypsotour.com/export/default.php')
    .set('join_up_test', 'https://devonline.joinup.ro/export/default.php')
    .set('join_up_v2', 'https://online.joinup.ro/export/default.php');

const tboMap = new Map().set('tbo_holidays_staging', 'http://api.tbotechnology.in/TBOHolidays_HotelAPI');

const sphinxMap = new Map()
    .set('christian_tour_sphinx_staging', 'https://api.sphinx2.christiantour.dev.ploi.imementohub.com')
    .set('christian_tour_sphinx', 'https://core.sphinx.christiantour.ro');

const hotelconMap = new Map()
    .set('accent_travel', 'https://tbs.accenttravel.ro/reseller/ws/');

const etgMap = new Map()
    .set('<?php echo Handles::RATEHAWK_STG ?>', 'https://api.worldota.net/api/b2b/v3');

const irixMap = new Map()
    .set('<?php echo Handles::HAPPYTOUR_V2 ?>', 'https://happybooking.ro/tbs/reseller');

const anexMap = new Map()
    .set('anex', 'https://webapi.anextour.com.ro');

// add to handleMap
handleMap.set('infinitehotel', infiniteMap);
handleMap.set('cyberlogic', cyberlogicMap);
handleMap.set('onetourismo', onetourismoMap);
handleMap.set('h2b_software', h2bMap);
handleMap.set('alladyn-hotels', alladynHotelsMap);
handleMap.set('alladyn-charters', alladynChartersMap);
handleMap.set('apitude', apitudeMap);
handleMap.set('eurosite', euroSiteMap);
handleMap.set('amara', amaraMap);
handleMap.set('etrip-agency', etripAgencyMap);
handleMap.set('brostravel', brosTravelMap);
handleMap.set('odeon', odeonTravelMap);
handleMap.set('beapi', dertourMap);
handleMap.set('etrip', etripMap);
handleMap.set('travelio', travelioMap);
handleMap.set('travelio_v2', travelioV2Map);
handleMap.set('tourvisio', tourVisioMap);
handleMap.set('tourvisio_v2', tourVisioV2Map);
handleMap.set('alladyn_old', alladynOld);
handleMap.set('goglobal', goglobalMap);
handleMap.set('megatec', megatecMap);
handleMap.set('goglobal_v2', goglobalV2Map);
handleMap.set('sejour', sejourMap);
handleMap.set('sansejour', sansejourMap);
handleMap.set('teztour_v2', teztourv2Map);
handleMap.set('calypso', calypsoMap);
handleMap.set('samo', samoMap);
handleMap.set('tbo', tboMap);
handleMap.set('sphinx', sphinxMap);
handleMap.set('hotelcon', hotelconMap);
handleMap.set('etg', etgMap);
handleMap.set('irix', irixMap);
handleMap.set('anex', anexMap);
// --------------------------------------------------

function populateUrlAndCredentials(system, handle) {
    const url = systems[system][handle];
    //console.log(url);
    $apiUrl.html('<option value="' + url + '">' + url + '</option>');

    const credentials = credentialsJson[handle];
    let username = '';
    let password = '';
    let context = '';
    let code = '';
    let bookingUsername = '';
    let bookingPassword = '';
    let bookingUrl = '';

    if (credentials) {
        username = credentials.apiUsername;
        password = credentials.apiPassword;
        context = credentials.apiContext;
        code = credentials.apiCode;
        bookingUsername = credentials.bookingUsername;
        bookingPassword = credentials.bookingPassword;
        bookingUrl = credentials.bookingUrl;
    }
    if (username !== '') {
        $apiUsernameInput.val(username);
    }
    if (password !== '') {
        $apiPasswordInput.val(password);
    }
    if (context !== '') {
        $apiContextInput.val(context);
    }
    if (code !== '') {
        $apiCodeInput.val(code);
    }

    if ($bookingUsernameInput.length) {
        $bookingUsernameInput.val(bookingUsername);
        $bookingPasswordInput.val(bookingPassword);
        $bookingUrlInput.val(bookingUrl);
    }
}

// changes on software change
$softwareSelect.change(function() {

    const firstHandles = systems[this.value];

    let option = '';
    for (let value in firstHandles) {
        option += '<option value="' + value + '">' + value + '</option>';
    }
    $handleSelect.html(option);

    populateUrlAndCredentials(this.value, Object.keys(firstHandles)[0]);
});

$('.q-expand-full-json').click(function($ev) {
    $($ev.target).next('pre').toggle();
    $($ev.target).siblings('table').first().toggle();
});

//$softwareSelect.val(systemGet).change();*/