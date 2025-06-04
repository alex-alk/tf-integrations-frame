import { credentialsJson } from '../../credentials.js';

jQuery(document).ready(function() {
    $('main').on('click', 'table a.q-result-expand-item', function($ev) {
        $($ev.target).closest('tr').next('tr').toggle();
    })

    // $('.q-result-expand-item').click(function ($ev) {
    //     $($ev.target).closest('tr').next('tr').toggle();
    // });

    $('.q-expand-full-json').click(function($ev) {
        $($ev.target).next('pre').toggle();
        $($ev.target).siblings('table').first().toggle();
    });

    const $softwareSelect = $('select[name="to[System_Software]"]');

    if ($softwareSelect[0] !== undefined) {

        const $apiUrl = $('select[name="to[ApiUrl]"]');
        const $handleSelect = $('select[name="to[Handle]"]');
        const $methodSelect = $('select[name="method"]');
        const $apiUsernameInput = $('input[name="to[ApiUsername]"]');
        const $apiPasswordInput = $('input[name="to[ApiPassword]"]');
        const $apiContextInput = $('input[name="to[ApiContext]"]');
        const $apiCodeInput = $('input[name="to[ApiCode]"]');
        const $bookingUrlInput = $('input[name="to[BookingUrl]"]');
        const $bookingUsernameInput = $('input[name="to[BookingApiUsername]"]');
        const $bookingPasswordInput = $('input[name="to[BookingApiPassword]"]');

        const methodsMap = new Map()
            .set('<?= Calls::COUNTRIES ?>', 'api_getCountries')
            .set('<?= Calls::CITIES ?>', 'api_getCities')
            .set('<?= Calls::REGIONS ?>', 'api_getRegions')
            .set('<?= Calls::HOTELS ?>', 'api_getHotels')
            .set('<?= Calls::HOTEL_DETAILS ?>', 'api_getHotelDetails')
            .set('<?= Calls::ROOM_TYPES ?>', 'api_getRoomTypes')
            .set('<?= Calls::AVAILABILITY ?>', 'api_getOffers')
            .set('<?= Calls::BOOK_HOTEL ?>', 'api_doBooking')
            .set('<?= Calls::AVAILABILITY_DATES ?>', 'api_getAvailabilityDates')
            .set('<?= Calls::CANCELLATION_FEES ?>', 'api_getOfferCancelFees')
            .set('<?= Calls::UPDATE_PRICE ?>', 'api_getOfferCancelFeesPaymentsAvailabilityAndPrice')
            .set('<?= Calls::PAYMENT_PLANS ?>', 'api_getOfferPaymentsPlan')
            .set('<?= Calls::TEST_CONNECTION ?>', 'api_testConnection')
            .set('<?= Calls::TOURS ?>', 'api_getTours')
            .set('<?= Calls::DOWNLOAD_OFFERS ?>', 'api_downloadOffers')
            .set('<?= Calls::CACHE_TOP_DATA ?>', 'cache_TOP_Data');

        const urlParams = new URLSearchParams(window.location.search);
        const call = urlParams.get('call');

        let optionMethods = '';
        for (let [methodCall, method] of methodsMap) {
            let selected = '';
            let disabled = '';
            if (methodCall === call) {
                selected = ' selected=true';
            } else {
                disabled = ' disabled';
            }
            optionMethods += '<option' + disabled + ' value="' + method + '" ' + selected + '>' + method + '</option>';
        }
        $methodSelect.html(optionMethods);


        // 3 stepts to add a TO into the form
        // STEP 1: populate system software dropdown

        const systemValues = [
            'infinitehotel',
            // 'cyberlogic',
            // 'onetourismo',
            // 'h2b_software',
            // 'alladyn-hotels',
            // 'alladyn-charters',
            // 'apitude',
            // 'eurosite',
            // 'amara',
            // 'etrip-agency',
            // 'brostravel',
            // 'odeon',
            // 'beapi',
            // 'etrip',
            // 'travelio',
            // 'travelio_v2',
            // 'tourvisio',
            // 'tourvisio_v2',
            // 'alladyn_old',
            // 'goglobal',
            // 'goglobal_v2',
            // 'megatec',
            // 'sejour',
            // 'sansejour',
            // 'teztour_v2',
            // 'calypso',
            // 'samo',
            // 'tbo',
            // 'sphinx',
            // 'hotelcon',
            // 'etg',
            // 'irix',
            // 'anex'
        ];
        let optionSystem = '';
        for (let i = 0; i < systemValues.length; i++) {
            optionSystem += '<option value="' + systemValues[i] + '">' + systemValues[i] + '</option>';
        }
        $softwareSelect.html(optionSystem);

        const isLocal = "<?php echo env('APP_ENV') === 'local'; ?>";
        const folder = "<?php echo env('APP_FOLDER'); ?>";

        // STEP 2: add handle + url to software map

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
            .set('<?php echo Handles::ANEX ?>', 'https://webapi.anextour.com.ro');


        // STEP 3: add software map to handleMap
        // the key will be the software name in the dropdown
        const handleMap = new Map();
        handleMap.set('infinitehotel', infiniteMap);
        // handleMap.set('cyberlogic', cyberlogicMap);
        // handleMap.set('onetourismo', onetourismoMap);
        // handleMap.set('h2b_software', h2bMap);
        // handleMap.set('alladyn-hotels', alladynHotelsMap);
        // handleMap.set('alladyn-charters', alladynChartersMap);
        // handleMap.set('apitude', apitudeMap);
        // handleMap.set('eurosite', euroSiteMap);
        // handleMap.set('amara', amaraMap);
        // handleMap.set('etrip-agency', etripAgencyMap);
        // handleMap.set('brostravel', brosTravelMap);
        // handleMap.set('odeon', odeonTravelMap);
        // handleMap.set('beapi', dertourMap);
        // handleMap.set('etrip', etripMap);
        // handleMap.set('travelio', travelioMap);
        // handleMap.set('travelio_v2', travelioV2Map);
        // handleMap.set('tourvisio', tourVisioMap);
        // handleMap.set('tourvisio_v2', tourVisioV2Map);
        // handleMap.set('alladyn_old', alladynOld);
        // handleMap.set('goglobal', goglobalMap);
        // handleMap.set('megatec', megatecMap);
        // handleMap.set('goglobal_v2', goglobalV2Map);
        // handleMap.set('sejour', sejourMap);
        // handleMap.set('sansejour', sansejourMap);
        // handleMap.set('teztour_v2', teztourv2Map);
        // handleMap.set('calypso', calypsoMap);
        // handleMap.set('samo', samoMap);
        // handleMap.set('tbo', tboMap);
        // handleMap.set('sphinx', sphinxMap);
        // handleMap.set('hotelcon', hotelconMap);
        // handleMap.set('etg', etgMap);
        // handleMap.set('irix', irixMap);
        // handleMap.set('anex', anexMap);

        // select system software on post
        const systemPost = "<?php echo $_POST['to']['System_Software'] ?? '' ?>";
        const handlePost = "<?php echo $_POST['to']['Handle'] ?? '' ?>";
        const systemGet = "<?php echo $_GET['system'] ?? '' ?>";
        const handleGet = "<?php echo $_GET['handle'] ?? '' ?>";

        const urlPost = "<?php echo $_POST['to']['ApiUrl'] ?? '' ?>";

        // populate handleSelect and ApiUrl and credentials on software change
        $softwareSelect.change(function() {
            console.log(handleMap);
            console.log(this.value);
            const values = handleMap.get(this.value).keys();
            let option = '';
            for (let value of values) {
                option += '<option value="' + value + '">' + value + '</option>';
            }
            $handleSelect.html(option);
            const handleValues = handleMap.get(this.value);
            const handle = $handleSelect.find(":selected").val();
            const url = handleValues.get(handle);
            $apiUrl.html('<option value="' + url + '">' + url + '</option>');
            //$apiUrl.val(url);

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
        });

        // populate ApiUrl and credentials on handle change
        $handleSelect.change(function() {
            const systemSoftware = $softwareSelect.find(":selected").val();
            const values = handleMap.get(systemSoftware);
            const url = values.get(this.value)
            $apiUrl.html('<option value="' + url + '">' + url + '</option>');
            //$apiUrl.val(url);

            const credentials = credentialsJson[this.value];
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
        });

        // populate handleSelect
        const systemSoftware = $softwareSelect.find(":selected").val();
        const handleValues = handleMap.get(systemSoftware);
        let option = '';
        for (let value of handleValues.keys()) {
            option += '<option value="' + value + '">' + value + '</option>';
        }
        $handleSelect.html(option);

        // populate ApiUrl

        const handle = $handleSelect.find(":selected").val();
        const url = handleValues.get(handle);
        $apiUrl.html('<option value="' + url + '">' + url + '</option>');

        // if method is post, change dropdown values
        if (systemPost !== '') {
            $softwareSelect.val(systemPost).change();
            $handleSelect.val(handlePost).change();
            $apiUrl.val(urlPost).change();
        } else if (systemGet !== '') {
            $softwareSelect.val(systemGet).change();
            $handleSelect.val(handleGet).change();
            //$apiUrl.val(urlPost).change();

        }
    }
});