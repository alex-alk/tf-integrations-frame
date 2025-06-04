<?php

namespace Service\OneTourismo;

use HttpClient\HttpClient;
use Models\Country;
use Psr\Http\Message\ServerRequestInterface;
use Service\IntegrationSupport\AbstractApiService;

class OneTourismoApiService extends AbstractApiService
{

    public function __construct(protected ServerRequestInterface $request, private HttpClient $client)
    {
        parent::__construct($request);
    }

    public function apiGetCountries(): array
    {
        //$cities = $this->apiGetCities();

        $countries = [];
        $country = new Country('1', '2', '3');
        $countries['1'] = $country;

        // /** @var City $city */
        // foreach ($cities as $city) {
        //     $countries->put($city->Country->Id, $city->Country);
        // }

        return $countries;
    }

    // public function apiGetCities(?CitiesFilter $params = null): CityCollection
    // {

    //     $options['headers'] = [
    //         'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
    //     ];

    //     $resp = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/static/destinations', $options);

    //     $content = $resp->getBody();
    //     $this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/static/destinations', $options, $content, 0);

    //     $content = json_decode($content, true);
    //     if (empty($content)) {
    //         throw new Exception('filos: empty city list');
    //     }

    //     $cities = new CityCollection();

    //     $map = CountryCodeMap::getCountryCodeMap();
    //     $map = array_flip($map);

    //     foreach ($content as $cityResp) {
    //         $country = Country::create($cityResp['country'], $cityResp['country'], $map[$cityResp['country']]);
    //         // if ($cityResp['id'] === 'OT-LOC-GEO-10176767') {
    //         //     dd($cityResp);
    //         // }

    //         $city = City::create($cityResp['id'], $cityResp['name'], $country);
    //         $cities->put($city->Id, $city);
    //     }

    //     return $cities;
    // }

    // public function apiGetRegions(): RegionCollection
    // {
    //     Validator::make()->validateUsernameAndPassword($this->post);

    //     $cities = $this->apiGetCities();
    //     $regions = new RegionCollection();

    //     foreach ($cities as $city) {
    //         $regions->put($city->County->Id, $city->County);
    //     }

    //     return $regions;
    // }

    // public function apiGetOffers(AvailabilityFilter $filter): AvailabilityCollection
    // {

    //     Validator::make()->validateIndividualOffersFilter($filter);

    //     $options['headers'] = [
    //         'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
    //         'Content-Type' => 'application/json'
    //     ];

    //     $options['body'] = json_encode([
    //         'username' => $this->username,
    //         'password' => $this->password,
    //         'start_date' => $filter->checkIn,
    //         'end_date' => $filter->checkOut,
    //         'rooms' => [
    //             [
    //                 'adults' => $filter->rooms->first()->adults,
    //                 'children' => 0,
    //                 'childrenAges' => []
    //             ]
    //         ],
    //         'destination' => $filter->cityId
    //     ]);

    //     $resp = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl . '/availability', $options);

    //     $content = $resp->getBody();

    //     $content = json_decode($content, true)['results'];
    //     //dd($content);

    //     $availabilities = new AvailabilityCollection();
    //     foreach ($content as $availability) {
    //         $availabilityObj = Availability::create($availability['id'], $filter->showHotelName, $availability['name']);

    //         $offers = new OfferCollection();
    //         foreach ($availability['rooms'] as $room) {
    //             $offer = Offer::createIndividualOffer(
    //                 $availability['id'],
    //                 $room['id'],
    //                 $room['id'],
    //                 $room['type'],
    //                 $room['mealPlan']['code'],
    //                 $room['mealPlan']['description'],
    //                 new DateTime($filter->checkIn),
    //                 new DateTime($filter->checkOut),
    //                 $filter->rooms->first()->adults,
    //                 $filter->rooms->first()->childrenAges->toArray(),
    //                 $availability['currency'],
    //                 $room['mealPlan']['price'],
    //                 $room['mealPlan']['price'],
    //                 $room['mealPlan']['price'],
    //                 0,
    //                 $room['availability'] >= 1 ? Offer::AVAILABILITY_YES : Offer::AVAILABILITY_NO
    //             );

    //             $offers->add($offer);
    //         }

    //         $availabilityObj->Offers = $offers;
    //         $availabilities->add($availabilityObj);
    //     }

    //     return $availabilities;
    // }

    // public function apiDoBooking(BookHotelFilter $filter): array
    // {
    //     Validator::make()->validateBookHotelFilter($filter);

    //     $index = 0;
    //     $adults = [];
    //     $children = [];
    //     /** @var Passenger $passenger */
    //     foreach ($filter->Items->first()->Passengers as $passenger) {
    //         if ($passenger->IsAdult) {
    //             $adult = [
    //                 'first_name' => $passenger->Firstname,
    //                 'last_name' => $passenger->Lastname,
    //                 'index' => $index,
    //                 'title' => $passenger->Gender === 'male' ? 'Mr' : 'Ms'
    //             ];
    //             $adults[] = $adult;
    //         } else {
    //             $from = new DateTime($passenger->BirthDate);
    //             $to = new DateTime($filter->Items->first()->Room_CheckinAfter);
    //             $age = $from->diff($to)->y;

    //             $child = [
    //                 'first_name' => $passenger->Firstname,
    //                 'last_name' => $passenger->Lastname,
    //                 'index' => $index,
    //                 'age' => $age,
    //                 'title' => 'Mr'
    //             ];
    //             $children[] = $child;
    //         }
    //         $index++;
    //     }

    //     $options['body'] = json_encode([
    //         'username' => $this->username,
    //         'password' => $this->password,
    //         'start_date' => $filter->Items->first()->Room_CheckinAfter,
    //         'end_date' => $filter->Items->first()->Room_CheckinBefore,
    //         'hotelID' => $filter->Items->first()->Hotel->InTourOperatorId,
    //         'providerContractID' => '',
    //         'rooms' => [
    //             [
    //                 'id' => '',
    //                 'code' => '',
    //                 'mealPlan' => '',
    //                 'specificAdults' => $adults,
    //                 'specificChildren' => $children
    //             ]
    //         ]
    //     ]);

    //     $resp = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/book', $options);
    //     $content = $resp->getBody();

    //     $this->showRequest(RequestFactory::METHOD_GET, $this->apiUrl . '/book', $options, $content, 0);

    //     $content = json_decode($content, true);

    //     dd($content);
    //     return [];
    // }

    // // prima data island
    // // apoi region, town, city


    // // hotelurile cu city de pe hotel care se potriveste perfect cu mappedLocations
    // // hotelurile cu city de pe hotel case se potrivesc partial ce ce avem in mappedlocations 
    // // hotelurile cu city de pe hotel case nu se potrivesc cu punctul 1 si 2 de mai sus
    // // hotel oras mappedlocation perfect partial(pot fi mai multe)
    // // un orase se poate potrivi cu mai multe orase?
    // public function apiGetHotels(?HotelsFilter $filter = null): HotelCollection
    // {

    //     $file = 'hotels';

    //     $cache = Utils::getFromCache($this, $file);

    //     if ($cache === null) {

    //         $options['headers'] = [
    //             'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password)
    //         ];

    //         $resp = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/static/my_properties?batches=1&include_static=true', $options);

    //         $content = $resp->getBody();

    //         $content = json_decode($content, true);

    //         $resp = $this->client->request(RequestFactory::METHOD_GET, $this->apiUrl . '/static/my_properties?batches=' . $content['count'] . '&include_static=true&include_destinations=true', $options);

    //         $content = $resp->getBody();

    //         $content = json_decode($content, true)['hotels'];

    //         // $hotels = [];
    //         // foreach ($content as $k => $hotel) {
    //         //     $hotels[$hotel['id']] = $hotel;
    //         // }
    //         // dd($hotels);

    //         /*
    //         $data = [];
    //         $header = [
    //             'hotel id',
    //             'hotel',
    //             'oras',
    //             'oras ales',
    //             //'id oras ales',
    //             'mappedLocations',
    //             //'id potrivire perfecta', 
    //             'potrivire perfecta',
    //             //'id locatie potrivire', 
    //             'protrivire partiala',
    //             //'id primul cuv', 
    //             'potrivire primul cuvant',
    //             'mapare',
    //             //'id mapare',
    //             'potrivire'
    //         ];

    //         // todo: verificat daca sunt mai multe orase in mappedcity cu acest alias
    //         $alias = [
    //             'Adamandas, Milos' => 'Adamas',
    //             'Agios Andreas' => 'Ayios Andreas',
    //             'Aigeira' => 'Egira',
    //             'Aigina' => 'Aegina',
    //             'Almyra Beach' => 'Kavala - Thassos',
    //             'Alonnisos' => 'Patitiri',
    //             'Amfiklia' => 'Amfikleia',
    //             'Amoudara, Heraklion' => 'Ammoudara',
    //             'Anavyssos' => 'Anavysos',
    //             'Angistri' => 'Agistri Island',
    //             'Arachova' => 'Delphi',
    //             'Arcadia' => 'Dimitsana',
    //             'Argolis' => 'Kandia',
    //             'Astipalaia' => 'Astipalea Island',
    //             'Astypalea' => 'Astipalea Island',
    //             'ASTYPALEA' => 'Astipalea Island',
    //             'Athenian Riviera' => 'Athens & Coast',
    //             'Athens' => 'Mykonos Island',
    //             'Attica' => 'Piraeus',
    //             'AXOS' => 'Axos',
    //             'Cephalonia' => 'Kefalonia Island',
    //             'CHANIA' => 'Chania City - Crete',
    //             'Chersonisos' => 'Chersonissos - Crete',
    //             'CORFU' => 'Lefkimi',
    //             'Corinthos' => 'Agioi Theodoroi',
    //             'Dafnitsa,Mpatsi, Andros' => 'Andros Island',
    //             'Damouchari' => 'Pelion',
    //             'Dasia, Corfu' => 'Dassia',
    //             'East Thessaloniki, Thessaloniki' => 'Agia Triada',
    //             'Elounda Crete' => 'Chersonissos - Crete',
    //             'Epirus' => 'Metsovo',
    //             'Ermoupoli, Syros' => 'Syros Island',
    //             'Ermoupolis Center-Syros' => 'Syros Island',
    //             'Ermoupolis Syros' => 'Cyclades',
    //             'Euboea' => 'Evia',
    //             'Farantaton 27, Athens' => 'Athens & Coast',
    //             'Finikounda Messinias' => 'Foinikounta',
    //             'Fira' => 'Firá',
    //             'Fira Santorini' => 'Firá',
    //             'Fira, Santorini' => 'Firá',
    //             'Fiscardo, Kefalonia' => 'Fiskardo',
    //             'Fiscardo, Kefalonia, Centrally Located' => 'Fiskardo',
    //             'Gaios' => 'Paxos Island',
    //             'Galaxidi' => 'Galaxidhion',
    //             'Halkidiki' => 'Chalkidiki',
    //             'Halkidiki,Greece' => 'Chalkidiki',
    //             'Heraclion' => 'Heraklion City - Crete',
    //             'Hermoupolis' => 'Syros Island',
    //             'Hermoupolis Syros' => 'Syros Island',
    //             'Hermoupolis, Syros' => 'Syros Island',
    //             'Hersonissos' => 'Chersonissos - Crete',
    //             'Hersonissos Crete' => 'Chersonissos - Crete',
    //             'Hersonissos, Crete' => 'Analipsi',
    //             'Hersonissos, Crete, Greece' => 'Chersonissos - Crete',
    //             'Hersonissos,Crete' => 'Chersonissos - Crete',
    //             'Ioannina' => 'Konitsa',
    //             'Irakleio' => 'Gouves',
    //             'Itilo, Lakonia' => 'Lakonia - Monemvasia -Mani',
    //             'Kalabaka' => 'Kalambaka',
    //             'Kalamaki Chania, Crete' => 'Chania Region - Crete',
    //             'Kalampaka' => 'Kalambaka',
    //             'Kalampaka, Trikala' => 'Kalambaka',
    //             'Kalavryta, Peloponnisos' => 'Kalavrita',
    //             'Kalives, Halikidiki' => 'Metamorfosi',
    //             'Karpenissi' => 'Karpenisi',
    //             'Kassandra, Halkidiki' => 'Nea Moudania',
    //             'Kastellorizo' => 'Kastelorizo',
    //             'Kastellorizo - Megisti' => 'Kastelorizo',
    //             'Kavala' => 'Asprovalta',
    //             'Kifissia' => 'Kifisia',
    //             'KILKIS' => 'Kilkis',
    //             'Killini, Peloponnisos' => 'Kyllini',
    //             'Kinetta' => 'Kineta',
    //             'Kolympia, Rhodes' => 'Kolymbia',
    //             'kos' => 'Tigaki',
    //             'Koufonisia' => 'Koufonisi Island',
    //             'Koufonissi' => 'Koufonisi Island',
    //             'Koukaki, Athens' => 'Athens & Coast',
    //             'Koutouloufari' => 'Kutulufari',
    //             'Kythera' => 'Kithira',
    //             'Kythira' => 'Kithira',
    //             'Kythira (Kythera)' => 'Kithira',
    //             'Laconia' => 'Kyparissi',
    //             'LAGANAS ZAKYNTHOS' => 'Laganas',
    //             'Lagkadia Arkadia' => 'Langadhia',
    //             'Lagonisi' => 'Lagonissi',
    //             'Lakopetra, Peloponnisos' => 'Lakkopetra',
    //             'Larissa' => 'Stomio',
    //             'Lasithi Crete' => 'Elounda - Crete',
    //             'Lasithi, Crete' => 'Lassithi Region - Crete',
    //             'LEFKADA ISLAND' => 'Lefkada Island',
    //             'Lefkas' => 'Nidri',
    //             'Lesvos' => 'Mithymna',
    //             'Lesvos Island' => 'Lesbos Island',
    //             'Léucade' => 'Nikiana',
    //             'Limenas Hersonissos' => 'Kutulufari',
    //             'Limenas Thassou' => 'Prinos',
    //             'Limenas, Thassos' => 'Thasos Island',
    //             'Loutraki, Lake Vouliagmeni' => 'Loutra Edipsou',
    //             'Magnisia, Central Greece' => 'Pelion',
    //             'Makrys Gialos, Crete' => 'Makri Gialos',
    //             'Maroussi, Athens' => 'Marousi',
    //             'Messenia' => 'Romanos',
    //             'Messinias 40 ,Athens' => 'Athens & Coast',
    //             'Messolonghi' => 'Mesolongi',
    //             'Molos' => 'Skyros Island',
    //             'Mountain Taygetos, Laconia' => 'Lakonia',
    //             'Mountainous Arcadia' => 'Arkadia',
    //             'Mt Pelion' => 'Pinakatai',
    //             'Mytilene' => 'Mytilini',
    //             'Olympus Riviera' => 'Paralia Katerini',
    //             'Olympus Riviera, Pieria' => 'Olympic Riviera - Pieria',
    //             'Olympus RIviera, Pieria' => 'Olympic Riviera - Pieria',
    //             'Orini Nafpaktia' => 'Ano Khora',
    //             'Paleros' => 'Palairos',
    //             'Palio Elatochórion Pieria' => 'Elatochori',
    //             'PANTELEIMONAS' => 'Pieria - Olympos',
    //             'Parga' => 'Perdika',
    //             'Parnassos' => 'Eptalofos',
    //             'Parnassus' => 'Eptalofos',
    //             'Paros' => 'Ormos Kardianis',
    //             'Paxi' => 'Paxos Island',
    //             'Perea (Thessaloniki)' => 'Peraia',
    //             'Philopappou' => 'Athens & Coast',
    //             'Pilio' => 'Portaria',
    //             'PIRGIOTIKA, NAFPLIO' => 'Nafplion',
    //             'Piryiotika' => 'Pyrgiotika',
    //             'Plaka, Athens' => 'Athens & Coast',
    //             'Platis Gialos' => 'Lassi',
    //             'Polychrono' => 'Polichrono',
    //             'Preveza, Ionian Coast' => 'Ammoudia',
    //             'Psyrri - Athens' => 'Athens Center',
    //             'Raa Atoll' => 'Maldives-All Destinations',
    //             'Rethimnon, Crete' => 'Myrthios',
    //             'Rethymno' => 'Panormos',
    //             'Rethymno - Crete' => 'Anogia',
    //             'Rethymno, Crete' => 'Triopetra',
    //             'Rethymno,Crete' => 'Crete Island',
    //             'Rethymnon' => 'Rethimnon City - Crete',
    //             'Rethymnon, Crete' => 'Rethimnon City - Crete',
    //             'Rhodope' => 'Maroneia',
    //             'Riglia, Messinia' => 'Ringlia',
    //             'Seaside Road, Sithonia' => 'Psakoudia',
    //             'Sfakia, Chania' => 'Frangokastello',
    //             'Sithonia, Halkidiki' => 'Kalyves',
    //             'Sivota' => 'Syvota',
    //             'Sivota, Ionian Coast' => 'Syvota',
    //             'SKiathos' => 'Skiathos Island',
    //             'SKIATHOS' => 'Skiathos Island',
    //             'Sounion' => 'Athens & Coast',
    //             'Sparta' => 'Mystras',
    //             'Stalis, Crete' => 'Stalida',
    //             'Stalis,Crete' => 'Stalida',
    //             'Syntagma, Athens' => 'Athens & Coast',
    //             'Thassos' => 'Thasos Island',
    //             'Thassos Island' => 'Thasos Island',
    //             'Thassos Island, Northern Aegean Islands' => 'Thasos Island',
    //             'Thassos, Northern Aegean Islands' => 'Thasos Island',
    //             'Thera' => 'Firá',
    //             'Thessaloniki' => 'Asprovalta',
    //             'Thira' => 'Firá',
    //             'Thira, Santorini' => 'Firá',
    //             'Voria Kinouria' => 'Kiveri',
    //             'Vourgareli, Arta' => 'Voulgareli',
    //             'Vrachati' => 'Vrakhati',
    //             'Vrahati, Corinthia' => 'Vrakhati',
    //             'Zagori' => 'Ioannina - Zagorochoria',
    //             'Zagori, Ioannina' => 'Ioannina - Zagorochoria',
    //             'Zagorohoria' => 'Papigkon',
    //             'Χαλκιδική / Halkidiki' => 'Chalkidiki'
    //         ];

    //         $secondAlias = [
    //             'Ioannina' => 'Achladies',
    //             'Thessaloniki' => 'Lagkadas',
    //             'Laconia' => 'Limenion',
    //             'Rethymno' => 'Rethimno Region - Crete',
    //             'Rethymno - Crete' => 'Crete Island',
    //             'Rethymnon' => 'Achlades',
    //             'Attica' => 'Attiki - Argosaronikos',
    //             'Kassandra, Halkidiki' => 'Athens & Coast',
    //             'Thassos Island, Northern Aegean Islands' => 'Acharavi',
    //             'Thassos, Northern Aegean Islands' => 'Astris'
    //         ];*/

    //         //$nepotriviri = [];
    //         //$nepotriviriheader = ['index', 'id hotel', 'hotel', 'city', 'locatii', 'locatia aleasa'];

    //         /*
    //         foreach ($content as $k => $hotel) {

    //             $city = $hotel['location']['city'];

    //             $mapare = [];
    //             $mapareIds = [];

    //             $perfectMatchesIds = [];
    //             $perfectMatches = [];

    //             $matchIds = [];
    //             $match = [];

    //             $primulCuvIds = [];
    //             $primulCuv = [];

    //             $orasAles = '';
    //             $idOrasAles = '';

    //             $noMatch = 'nu';

    //             // if (isset($alias[$city])) {
    //             //     $mapare = $alias[$city];
    //             //     $noMatch = 'mapare';
    //             // } else {

    //             $mappedLocations = [];
    //             foreach ($hotel['mappedLocations'] as $location) {
    //                 $mappedLocations[] = $location['name'];

    //                 $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                 if (!is_numeric($subid)) {
    //                     throw new Exception('not a number!');
    //                 }
    //                 $subid = (int) $subid;

    //                 if ($location['name'] === $city) {
    //                     $perfectMatches[$subid] = $location['name'];
    //                     $perfectMatchesIds[$subid] = $location['id'];
    //                 } else if (str_contains($location['name'], $city) || str_contains($city, $location['name'])) {
    //                     $matchIds[$subid] = $location['id'];
    //                     $match[$subid] = $location['name'];
    //                 }
    //             }

    //             // alege pentru perfect match
    //             if (count($perfectMatches) >= 1) {
    //                 ksort($perfectMatches);
    //                 ksort($perfectMatchesIds);
    //                 $idOrasAles = $perfectMatchesIds[array_key_first($perfectMatchesIds)];
    //                 $orasAles = $perfectMatches[array_key_first($perfectMatches)];
    //             }

    //             // daca avem potrivire partiala
    //             if (empty($perfectMatches) && count($match) > 0) {

    //                 if (count($match) === 1) {
    //                     $idOrasAles = $matchIds[array_key_first($matchIds)];
    //                     $orasAles = $match[array_key_first($match)];
    //                 } elseif (count(array_unique($match)) === 1) {

    //                     $idOrasAles = $matchIds[array_key_first($matchIds)];
    //                     $orasAles = $match[array_key_first($match)];
    //                 } elseif (count($match) > 1) { // alege pentru potrivire partiala
    //                     ksort($match);
    //                     ksort($matchIds);
    //                     $hasIsland = false;
    //                     $hasRegion = false;
    //                     $hasTown = false;
    //                     $hasCity = false;
    //                     $hasCenter = false;

    //                     $islandLocation = [];
    //                     $regionLocation = [];
    //                     $townLocation = [];
    //                     $cityLocation = [];
    //                     $centerLocation = [];

    //                     foreach ($match as $mid => $matchCity) {
    //                         // daca contine island
    //                         if (str_contains(strtolower($matchCity), 'island') && count($islandLocation) === 0) {
    //                             $islandLocation = [$matchIds[$mid], $match[$mid]];
    //                             $hasIsland = true;
    //                         }

    //                         // daca contine region
    //                         if (str_contains(strtolower($matchCity), 'region') && count($regionLocation) === 0) {
    //                             $regionLocation = [$matchIds[$mid], $match[$mid]];
    //                             $hasRegion = true;
    //                         }

    //                         // daca contine
    //                         if (str_contains(strtolower($matchCity), 'town') && count($townLocation) === 0) {
    //                             $townLocation = [$matchIds[$mid], $match[$mid]];
    //                             $hasTown = true;
    //                         }

    //                         // daca contine
    //                         if (str_contains(strtolower($matchCity), 'city') && count($cityLocation) === 0) {
    //                             $cityLocation = [$matchIds[$mid], $match[$mid]];
    //                             $hasCity = true;
    //                         }

    //                         if (str_contains(strtolower($matchCity), 'center') && count($centerLocation) === 0) {
    //                             $centerLocation = [$matchIds[$mid], $match[$mid]];
    //                             $hasCenter = true;
    //                         }
    //                     }

    //                     if ($hasIsland) {
    //                         $idOrasAles = $islandLocation[0];
    //                         $orasAles = $islandLocation[1];
    //                     } elseif ($hasRegion) {
    //                         $idOrasAles = $regionLocation[0];
    //                         $orasAles = $regionLocation[1];
    //                     } elseif ($hasTown) {
    //                         $idOrasAles = $townLocation[0];
    //                         $orasAles = $townLocation[1];
    //                     } elseif ($hasCity) {
    //                         $idOrasAles = $cityLocation[0];
    //                         $orasAles = $cityLocation[1];
    //                     } elseif ($hasCenter) {
    //                         $idOrasAles = $centerLocation[0];
    //                         $orasAles = $centerLocation[1];
    //                     } else {
    //                         $orasAles = $match[array_key_first($match)];
    //                     }
    //                 }
    //             }


    //             sort($mappedLocations);

    //             if (empty($match) && empty($perfectMatches)) {

    //                 // cauta in primul cuv
    //                 $length1 = strpos($city, ',') ?: 1000;

    //                 $length2 = strpos($city, '-') ?: 1000;

    //                 $length3 = strpos($city, ' ') ?: 1000;

    //                 $length = min([$length1, $length2, $length3]);

    //                 if ($length) {
    //                     $first = substr($city, 0, $length);
    //                 } else {
    //                     $first = $city;
    //                 }
    //                 $first = trim($first);


    //                 if ($first) {
    //                     foreach ($hotel['mappedLocations'] as $location) {

    //                         if (str_contains($location['name'], $first)) {
    //                             $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                             if (!is_numeric($subid)) {
    //                                 throw new Exception('not a number!');
    //                             }
    //                             $subid = (int) $subid;
    //                             $primulCuv[$subid] = $location['name'];
    //                             $primulCuvIds[$subid] = $location['id'];
    //                         }
    //                     }

    //                     if (!empty($primulCuv)) {
    //                         $noMatch = 'primul cuvant';
    //                     }

    //                     // if (empty($primulCuv) && !empty($locations)) {
    //                     //     $nepotriviri[$city] = [$k,  $hotel['id'], $hotel['name'], $city, implode("\n", $locations), ''];
    //                     // }
    //                 }
    //             }

    //             // alege pentru primul cuvant
    //             if (count($primulCuv) > 1) {
    //                 ksort($primulCuv);
    //                 ksort($primulCuvIds);
    //                 $hasIsland = false;
    //                 $hasRegion = false;
    //                 $hasTown = false;
    //                 $hasCity = false;
    //                 $hasCenter = false;

    //                 $islandLocation = [];
    //                 $regionLocation = [];
    //                 $townLocation = [];
    //                 $cityLocation = [];
    //                 $centerLocation = [];

    //                 foreach ($primulCuv as $mid => $matchCity) {
    //                     // daca contine island
    //                     if (str_contains(strtolower($matchCity), 'island') && count($islandLocation) === 0) {
    //                         $islandLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                         $hasIsland = true;
    //                     }

    //                     // daca contine region
    //                     if (str_contains(strtolower($matchCity), 'region') && count($regionLocation) === 0) {
    //                         $regionLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                         $hasRegion = true;
    //                     }

    //                     // daca contine region
    //                     if (str_contains(strtolower($matchCity), 'town') && count($townLocation) === 0) {
    //                         $townLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                         $hasTown = true;
    //                     }

    //                     // daca contine region
    //                     if (str_contains(strtolower($matchCity), 'city') && count($cityLocation) === 0) {
    //                         $cityLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                         $hasCity = true;
    //                     }

    //                     if (str_contains(strtolower($matchCity), 'center') && count($centerLocation) === 0) {
    //                         $centerLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                         $hasCenter = true;
    //                     }
    //                 }

    //                 if ($hasIsland) {
    //                     $idOrasAles = $islandLocation[0];
    //                     $orasAles = $islandLocation[1];
    //                 } elseif ($hasRegion) {
    //                     $idOrasAles = $regionLocation[0];
    //                     $orasAles = $regionLocation[1];
    //                 } elseif ($hasTown) {
    //                     $idOrasAles = $townLocation[0];
    //                     $orasAles = $townLocation[1];
    //                 } elseif ($hasCity) {
    //                     $idOrasAles = $cityLocation[0];
    //                     $orasAles = $cityLocation[1];
    //                 } elseif ($hasCenter) {
    //                     $idOrasAles = $centerLocation[0];
    //                     $orasAles = $centerLocation[1];
    //                 } else {
    //                     $orasAles = $primulCuv[array_key_first($primulCuv)];
    //                 }
    //             } elseif (count($primulCuv) === 1) {
    //                 $idOrasAles =  $primulCuvIds[array_key_first($primulCuvIds)];
    //                 $orasAles =  $primulCuv[array_key_first($primulCuv)];
    //             }

    //             // mapare
    //             if (empty($perfectMatches) && empty($match) && empty($primulCuv)) {

    //                 $mapat = [];
    //                 // primul set
    //                 if (isset($alias[$city])) {
    //                     $mapat = $alias[$city];

    //                     foreach ($hotel['mappedLocations'] as $location) {

    //                         $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                         if (!is_numeric($subid)) {
    //                             throw new Exception('not a number!');
    //                         }
    //                         $subid = (int) $subid;

    //                         if ($location['name'] === $mapat) {
    //                             $mapare[$subid] = $location['name'];
    //                             $mapareIds[$subid] = $location['id'];
    //                         }
    //                     }
    //                 }

    //                 // al doilea set
    //                 if (empty($mapare) && isset($secondAlias[$city])) {
    //                     $mapat = $secondAlias[$city];

    //                     foreach ($hotel['mappedLocations'] as $location) {

    //                         $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                         if (!is_numeric($subid)) {
    //                             throw new Exception('not a number!');
    //                         }
    //                         $subid = (int) $subid;

    //                         if ($location['name'] === $mapat) {
    //                             $mapare[$subid] = $location['name'];
    //                             $mapareIds[$subid] = $location['id'];
    //                         }
    //                     }
    //                 }
    //                 if (!empty($mapare)) {
    //                     ksort($mapare);
    //                     ksort($mapareIds);
    //                     $idOrasAles = $mapareIds[array_key_first($mapareIds)];
    //                     $orasAles = $mapare[array_key_first($mapare)];
    //                 }
    //             }


    //             if (!empty($perfectMatches)) {
    //                 $matchIds = [];
    //                 $match = [];

    //                 $primulCuv = [];
    //                 $primulCuvIds = [];
    //             }

    //             if (!empty($perfectMatches)) {
    //                 $noMatch = 'perfecta';
    //             }

    //             if (!empty($mapare)) {
    //                 $noMatch = 'mapare';
    //             }

    //             if (!empty($match)) {
    //                 $noMatch = 'partiala';
    //                 $primulCuv = [];
    //                 $primulCuvIds = [];
    //             }
    //             //}

    //             $data[] = [
    //                 $hotel['id'], 
    //                 $hotel['name'], 
    //                 $city,
    //                 $orasAles === '' ? 'nu' : $orasAles,
    //                 //$idOrasAles,
    //                 implode("; ", $mappedLocations),
    //                 //implode("\n", $perfectMatchesIds), 
    //                 implode("\r", $perfectMatches), 
    //                 //implode("\n", $matchIds), 
    //                 implode("\r", $match),
    //                 //implode("\n", $primulCuvIds), 
    //                 implode("\r", $primulCuv),
    //                 implode("\r", $mapare),
    //                 //implode("\r", $mapareIds),
    //                 $noMatch
    //             ];
    //         }
    //         */
    //         //dd($nepotriviri);

    //         //Utils::createCsv(__DIR__.'/potriviri.csv', $nepotriviriheader, $nepotriviri);
    //         //Utils::createCsv(__DIR__ . '/raport.csv', $header, $data);

    //         //$map = CountryCodeMap::getCountryCodeMap();
    //         // $map = array_flip($map);
    //         $cities = $this->apiGetCities();

    //         $hotels = new HotelCollection();

    //         foreach ($content as $k => $hotelResp) {

    //             $city = $cities->get($this->getCityFromHotel($hotelResp));
    //             if ($city === null) {
    //                 dd($hotelResp);
    //                 continue;
    //             }

    //             $hotelImageGalleryItemCol = new HotelImageGalleryItemCollection();

    //             foreach ($hotelResp['photos'] as $photo) {
    //                 $img = HotelImageGalleryItem::create($photo);
    //                 $hotelImageGalleryItemCol->add($img);
    //             }

    //             $hotel = Hotel::create(
    //                 $hotelResp['id'],
    //                 $hotelResp['name'],
    //                 $city,
    //                 $hotelResp['rating']['value'],
    //                 null,
    //                 $hotelResp['location']['address'] ?? null,
    //                 $hotelResp['location']['latitude'],
    //                 $hotelResp['location']['longitude'],
    //                 null,
    //                 $hotelImageGalleryItemCol
    //             );
    //             $hotels->put($hotel->Id, $hotel);
    //         }

    //         Utils::writeToCache($this, $file, json_encode($hotels));
    //         return $hotels;
    //     } else {
    //         return ResponseConverter::convertToCollection(json_decode($cache, true), HotelCollection::class);
    //     }
    // }

    // private static function getCityFromHotel(array $hotel): string
    // {
    //     $alias = [
    //         'Adamandas, Milos' => 'Adamas',
    //         'Agios Andreas' => 'Ayios Andreas',
    //         'Aigeira' => 'Egira',
    //         'Aigina' => 'Aegina',
    //         'Almyra Beach' => 'Kavala - Thassos',
    //         'Alonnisos' => 'Patitiri',
    //         'Amfiklia' => 'Amfikleia',
    //         'Amoudara, Heraklion' => 'Ammoudara',
    //         'Anavyssos' => 'Anavysos',
    //         'Angistri' => 'Agistri Island',
    //         'Arachova' => 'Delphi',
    //         'Arcadia' => 'Dimitsana',
    //         'Argolis' => 'Kandia',
    //         'Astipalaia' => 'Astipalea Island',
    //         'Astypalea' => 'Astipalea Island',
    //         'ASTYPALEA' => 'Astipalea Island',
    //         'Athenian Riviera' => 'Athens & Coast',
    //         'Athens' => 'Mykonos Island',
    //         'Attica' => 'Piraeus',
    //         'AXOS' => 'Axos',
    //         'Cephalonia' => 'Kefalonia Island',
    //         'CHANIA' => 'Chania City - Crete',
    //         'Chersonisos' => 'Chersonissos - Crete',
    //         'CORFU' => 'Lefkimi',
    //         'Corinthos' => 'Agioi Theodoroi',
    //         'Dafnitsa,Mpatsi, Andros' => 'Andros Island',
    //         'Damouchari' => 'Pelion',
    //         'Dasia, Corfu' => 'Dassia',
    //         'East Thessaloniki, Thessaloniki' => 'Agia Triada',
    //         'Elounda Crete' => 'Chersonissos - Crete',
    //         'Epirus' => 'Metsovo',
    //         'Ermoupoli, Syros' => 'Syros Island',
    //         'Ermoupolis Center-Syros' => 'Syros Island',
    //         'Ermoupolis Syros' => 'Cyclades',
    //         'Euboea' => 'Evia',
    //         'Farantaton 27, Athens' => 'Athens & Coast',
    //         'Finikounda Messinias' => 'Foinikounta',
    //         'Fira' => 'Firá',
    //         'Fira Santorini' => 'Firá',
    //         'Fira, Santorini' => 'Firá',
    //         'Fiscardo, Kefalonia' => 'Fiskardo',
    //         'Fiscardo, Kefalonia, Centrally Located' => 'Fiskardo',
    //         'Gaios' => 'Paxos Island',
    //         'Galaxidi' => 'Galaxidhion',
    //         'Halkidiki' => 'Chalkidiki',
    //         'Halkidiki,Greece' => 'Chalkidiki',
    //         'Heraclion' => 'Heraklion City - Crete',
    //         'Hermoupolis' => 'Syros Island',
    //         'Hermoupolis Syros' => 'Syros Island',
    //         'Hermoupolis, Syros' => 'Syros Island',
    //         'Hersonissos' => 'Chersonissos - Crete',
    //         'Hersonissos Crete' => 'Chersonissos - Crete',
    //         'Hersonissos, Crete' => 'Analipsi',
    //         'Hersonissos, Crete, Greece' => 'Chersonissos - Crete',
    //         'Hersonissos,Crete' => 'Chersonissos - Crete',
    //         'Ioannina' => 'Konitsa',
    //         'Irakleio' => 'Gouves',
    //         'Itilo, Lakonia' => 'Lakonia - Monemvasia -Mani',
    //         'Kalabaka' => 'Kalambaka',
    //         'Kalamaki Chania, Crete' => 'Chania Region - Crete',
    //         'Kalampaka' => 'Kalambaka',
    //         'Kalampaka, Trikala' => 'Kalambaka',
    //         'Kalavryta, Peloponnisos' => 'Kalavrita',
    //         'Kalives, Halikidiki' => 'Metamorfosi',
    //         'Karpenissi' => 'Karpenisi',
    //         'Kassandra, Halkidiki' => 'Nea Moudania',
    //         'Kastellorizo' => 'Kastelorizo',
    //         'Kastellorizo - Megisti' => 'Kastelorizo',
    //         'Kavala' => 'Asprovalta',
    //         'Kifissia' => 'Kifisia',
    //         'KILKIS' => 'Kilkis',
    //         'Killini, Peloponnisos' => 'Kyllini',
    //         'Kinetta' => 'Kineta',
    //         'Kolympia, Rhodes' => 'Kolymbia',
    //         'kos' => 'Tigaki',
    //         'Koufonisia' => 'Koufonisi Island',
    //         'Koufonissi' => 'Koufonisi Island',
    //         'Koukaki, Athens' => 'Athens & Coast',
    //         'Koutouloufari' => 'Kutulufari',
    //         'Kythera' => 'Kithira',
    //         'Kythira' => 'Kithira',
    //         'Kythira (Kythera)' => 'Kithira',
    //         'Laconia' => 'Kyparissi',
    //         'LAGANAS ZAKYNTHOS' => 'Laganas',
    //         'Lagkadia Arkadia' => 'Langadhia',
    //         'Lagonisi' => 'Lagonissi',
    //         'Lakopetra, Peloponnisos' => 'Lakkopetra',
    //         'Larissa' => 'Stomio',
    //         'Lasithi Crete' => 'Elounda - Crete',
    //         'Lasithi, Crete' => 'Lassithi Region - Crete',
    //         'LEFKADA ISLAND' => 'Lefkada Island',
    //         'Lefkas' => 'Nidri',
    //         'Lesvos' => 'Mithymna',
    //         'Lesvos Island' => 'Lesbos Island',
    //         'Léucade' => 'Nikiana',
    //         'Limenas Hersonissos' => 'Kutulufari',
    //         'Limenas Thassou' => 'Prinos',
    //         'Limenas, Thassos' => 'Thasos Island',
    //         'Loutraki, Lake Vouliagmeni' => 'Loutra Edipsou',
    //         'Magnisia, Central Greece' => 'Pelion',
    //         'Makrys Gialos, Crete' => 'Makri Gialos',
    //         'Maroussi, Athens' => 'Marousi',
    //         'Messenia' => 'Romanos',
    //         'Messinias 40 ,Athens' => 'Athens & Coast',
    //         'Messolonghi' => 'Mesolongi',
    //         'Molos' => 'Skyros Island',
    //         'Mountain Taygetos, Laconia' => 'Lakonia',
    //         'Mountainous Arcadia' => 'Arkadia',
    //         'Mt Pelion' => 'Pinakatai',
    //         'Mytilene' => 'Mytilini',
    //         'Olympus Riviera' => 'Paralia Katerini',
    //         'Olympus Riviera, Pieria' => 'Olympic Riviera - Pieria',
    //         'Olympus RIviera, Pieria' => 'Olympic Riviera - Pieria',
    //         'Orini Nafpaktia' => 'Ano Khora',
    //         'Paleros' => 'Palairos',
    //         'Palio Elatochórion Pieria' => 'Elatochori',
    //         'PANTELEIMONAS' => 'Pieria - Olympos',
    //         'Parga' => 'Perdika',
    //         'Parnassos' => 'Eptalofos',
    //         'Parnassus' => 'Eptalofos',
    //         'Paros' => 'Ormos Kardianis',
    //         'Paxi' => 'Paxos Island',
    //         'Perea (Thessaloniki)' => 'Peraia',
    //         'Philopappou' => 'Athens & Coast',
    //         'Pilio' => 'Portaria',
    //         'PIRGIOTIKA, NAFPLIO' => 'Nafplion',
    //         'Piryiotika' => 'Pyrgiotika',
    //         'Plaka, Athens' => 'Athens & Coast',
    //         'Platis Gialos' => 'Lassi',
    //         'Polychrono' => 'Polichrono',
    //         'Preveza, Ionian Coast' => 'Ammoudia',
    //         'Psyrri - Athens' => 'Athens Center',
    //         'Raa Atoll' => 'Maldives-All Destinations',
    //         'Rethimnon, Crete' => 'Myrthios',
    //         'Rethymno' => 'Panormos',
    //         'Rethymno - Crete' => 'Anogia',
    //         'Rethymno, Crete' => 'Triopetra',
    //         'Rethymno,Crete' => 'Crete Island',
    //         'Rethymnon' => 'Rethimnon City - Crete',
    //         'Rethymnon, Crete' => 'Rethimnon City - Crete',
    //         'Rhodope' => 'Maroneia',
    //         'Riglia, Messinia' => 'Ringlia',
    //         'Seaside Road, Sithonia' => 'Psakoudia',
    //         'Sfakia, Chania' => 'Frangokastello',
    //         'Sithonia, Halkidiki' => 'Kalyves',
    //         'Sivota' => 'Syvota',
    //         'Sivota, Ionian Coast' => 'Syvota',
    //         'SKiathos' => 'Skiathos Island',
    //         'SKIATHOS' => 'Skiathos Island',
    //         'Sounion' => 'Athens & Coast',
    //         'Sparta' => 'Mystras',
    //         'Stalis, Crete' => 'Stalida',
    //         'Stalis,Crete' => 'Stalida',
    //         'Syntagma, Athens' => 'Athens & Coast',
    //         'Thassos' => 'Thasos Island',
    //         'Thassos Island' => 'Thasos Island',
    //         'Thassos Island, Northern Aegean Islands' => 'Thasos Island',
    //         'Thassos, Northern Aegean Islands' => 'Thasos Island',
    //         'Thera' => 'Firá',
    //         'Thessaloniki' => 'Asprovalta',
    //         'Thira' => 'Firá',
    //         'Thira, Santorini' => 'Firá',
    //         'Voria Kinouria' => 'Kiveri',
    //         'Vourgareli, Arta' => 'Voulgareli',
    //         'Vrachati' => 'Vrakhati',
    //         'Vrahati, Corinthia' => 'Vrakhati',
    //         'Zagori' => 'Ioannina - Zagorochoria',
    //         'Zagori, Ioannina' => 'Ioannina - Zagorochoria',
    //         'Zagorohoria' => 'Papigkon',
    //         'Χαλκιδική / Halkidiki' => 'Chalkidiki'
    //     ];

    //     $secondAlias = [
    //         'Ioannina' => 'Achladies',
    //         'Thessaloniki' => 'Lagkadas',
    //         'Laconia' => 'Limenion',
    //         'Rethymno' => 'Rethimno Region - Crete',
    //         'Rethymno - Crete' => 'Crete Island',
    //         'Rethymnon' => 'Achlades',
    //         'Attica' => 'Attiki - Argosaronikos',
    //         'Kassandra, Halkidiki' => 'Athens & Coast',
    //         'Thassos Island, Northern Aegean Islands' => 'Acharavi',
    //         'Thassos, Northern Aegean Islands' => 'Astris'
    //     ];

    //     $city = $hotel['location']['city'];

    //     $mapare = [];
    //     $mapareIds = [];

    //     $perfectMatchesIds = [];
    //     $perfectMatches = [];

    //     $matchIds = [];
    //     $match = [];

    //     $primulCuvIds = [];
    //     $primulCuv = [];

    //     $orasAles = '';
    //     $idOrasAles = '';

    //     $noMatch = 'nu';

    //     // if (isset($alias[$city])) {
    //     //     $mapare = $alias[$city];
    //     //     $noMatch = 'mapare';
    //     // } else {

    //     $mappedLocations = [];
    //     foreach ($hotel['mappedLocations'] as $location) {
    //         $mappedLocations[] = $location['name'];

    //         $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //         if (!is_numeric($subid)) {
    //             throw new Exception('not a number!');
    //         }
    //         $subid = (int) $subid;

    //         if ($location['name'] === $city) {
    //             $perfectMatches[$subid] = $location['name'];
    //             $perfectMatchesIds[$subid] = $location['id'];
    //         } else if (str_contains($location['name'], $city) || str_contains($city, $location['name'])) {
    //             $matchIds[$subid] = $location['id'];
    //             $match[$subid] = $location['name'];
    //         }
    //     }

    //     // alege pentru perfect match
    //     if (count($perfectMatches) >= 1) {
    //         ksort($perfectMatches);
    //         ksort($perfectMatchesIds);
    //         $idOrasAles = $perfectMatchesIds[array_key_first($perfectMatchesIds)];
    //         $orasAles = $perfectMatches[array_key_first($perfectMatches)];
    //     }

    //     // daca avem potrivire partiala
    //     if (empty($perfectMatches) && count($match) > 0) {

    //         if (count($match) === 1) {
    //             $idOrasAles = $matchIds[array_key_first($matchIds)];
    //             $orasAles = $match[array_key_first($match)];
    //         } elseif (count(array_unique($match)) === 1) {

    //             $idOrasAles = $matchIds[array_key_first($matchIds)];
    //             $orasAles = $match[array_key_first($match)];
    //         } elseif (count($match) > 1) { // alege pentru potrivire partiala
    //             ksort($match);
    //             ksort($matchIds);
    //             $hasIsland = false;
    //             $hasRegion = false;
    //             $hasTown = false;
    //             $hasCity = false;
    //             $hasCenter = false;

    //             $islandLocation = [];
    //             $regionLocation = [];
    //             $townLocation = [];
    //             $cityLocation = [];
    //             $centerLocation = [];

    //             foreach ($match as $mid => $matchCity) {
    //                 // daca contine island
    //                 if (str_contains(strtolower($matchCity), 'island') && count($islandLocation) === 0) {
    //                     $islandLocation = [$matchIds[$mid], $match[$mid]];
    //                     $hasIsland = true;
    //                 }

    //                 // daca contine region
    //                 if (str_contains(strtolower($matchCity), 'region') && count($regionLocation) === 0) {
    //                     $regionLocation = [$matchIds[$mid], $match[$mid]];
    //                     $hasRegion = true;
    //                 }

    //                 // daca contine
    //                 if (str_contains(strtolower($matchCity), 'town') && count($townLocation) === 0) {
    //                     $townLocation = [$matchIds[$mid], $match[$mid]];
    //                     $hasTown = true;
    //                 }

    //                 // daca contine
    //                 if (str_contains(strtolower($matchCity), 'city') && count($cityLocation) === 0) {
    //                     $cityLocation = [$matchIds[$mid], $match[$mid]];
    //                     $hasCity = true;
    //                 }

    //                 if (str_contains(strtolower($matchCity), 'center') && count($centerLocation) === 0) {
    //                     $centerLocation = [$matchIds[$mid], $match[$mid]];
    //                     $hasCenter = true;
    //                 }
    //             }

    //             if ($hasIsland) {
    //                 $idOrasAles = $islandLocation[0];
    //                 $orasAles = $islandLocation[1];
    //             } elseif ($hasRegion) {
    //                 $idOrasAles = $regionLocation[0];
    //                 $orasAles = $regionLocation[1];
    //             } elseif ($hasTown) {
    //                 $idOrasAles = $townLocation[0];
    //                 $orasAles = $townLocation[1];
    //             } elseif ($hasCity) {
    //                 $idOrasAles = $cityLocation[0];
    //                 $orasAles = $cityLocation[1];
    //             } elseif ($hasCenter) {
    //                 $idOrasAles = $centerLocation[0];
    //                 $orasAles = $centerLocation[1];
    //             } else {
    //                 $orasAles = $match[array_key_first($match)];
    //             }
    //         }
    //     }


    //     sort($mappedLocations);

    //     if (empty($match) && empty($perfectMatches)) {

    //         // cauta in primul cuv
    //         $length1 = strpos($city, ',') ?: 1000;

    //         $length2 = strpos($city, '-') ?: 1000;

    //         $length3 = strpos($city, ' ') ?: 1000;

    //         $length = min([$length1, $length2, $length3]);

    //         if ($length) {
    //             $first = substr($city, 0, $length);
    //         } else {
    //             $first = $city;
    //         }
    //         $first = trim($first);


    //         if ($first) {
    //             foreach ($hotel['mappedLocations'] as $location) {

    //                 if (str_contains($location['name'], $first)) {
    //                     $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                     if (!is_numeric($subid)) {
    //                         throw new Exception('not a number!');
    //                     }
    //                     $subid = (int) $subid;
    //                     $primulCuv[$subid] = $location['name'];
    //                     $primulCuvIds[$subid] = $location['id'];
    //                 }
    //             }

    //             if (!empty($primulCuv)) {
    //                 $noMatch = 'primul cuvant';
    //             }

    //             // if (empty($primulCuv) && !empty($locations)) {
    //             //     $nepotriviri[$city] = [$k,  $hotel['id'], $hotel['name'], $city, implode("\n", $locations), ''];
    //             // }
    //         }
    //     }

    //     // alege pentru primul cuvant
    //     if (count($primulCuv) > 1) {
    //         ksort($primulCuv);
    //         ksort($primulCuvIds);
    //         $hasIsland = false;
    //         $hasRegion = false;
    //         $hasTown = false;
    //         $hasCity = false;
    //         $hasCenter = false;

    //         $islandLocation = [];
    //         $regionLocation = [];
    //         $townLocation = [];
    //         $cityLocation = [];
    //         $centerLocation = [];

    //         foreach ($primulCuv as $mid => $matchCity) {
    //             // daca contine island
    //             if (str_contains(strtolower($matchCity), 'island') && count($islandLocation) === 0) {
    //                 $islandLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                 $hasIsland = true;
    //             }

    //             // daca contine region
    //             if (str_contains(strtolower($matchCity), 'region') && count($regionLocation) === 0) {
    //                 $regionLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                 $hasRegion = true;
    //             }

    //             // daca contine region
    //             if (str_contains(strtolower($matchCity), 'town') && count($townLocation) === 0) {
    //                 $townLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                 $hasTown = true;
    //             }

    //             // daca contine region
    //             if (str_contains(strtolower($matchCity), 'city') && count($cityLocation) === 0) {
    //                 $cityLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                 $hasCity = true;
    //             }

    //             if (str_contains(strtolower($matchCity), 'center') && count($centerLocation) === 0) {
    //                 $centerLocation = [$primulCuvIds[$mid], $primulCuv[$mid]];
    //                 $hasCenter = true;
    //             }
    //         }

    //         if ($hasIsland) {
    //             $idOrasAles = $islandLocation[0];
    //             $orasAles = $islandLocation[1];
    //         } elseif ($hasRegion) {
    //             $idOrasAles = $regionLocation[0];
    //             $orasAles = $regionLocation[1];
    //         } elseif ($hasTown) {
    //             $idOrasAles = $townLocation[0];
    //             $orasAles = $townLocation[1];
    //         } elseif ($hasCity) {
    //             $idOrasAles = $cityLocation[0];
    //             $orasAles = $cityLocation[1];
    //         } elseif ($hasCenter) {
    //             $idOrasAles = $centerLocation[0];
    //             $orasAles = $centerLocation[1];
    //         } else {
    //             $orasAles = $primulCuv[array_key_first($primulCuv)];
    //         }
    //     } elseif (count($primulCuv) === 1) {
    //         $idOrasAles =  $primulCuvIds[array_key_first($primulCuvIds)];
    //         $orasAles =  $primulCuv[array_key_first($primulCuv)];
    //     }

    //     // mapare
    //     if (empty($perfectMatches) && empty($match) && empty($primulCuv)) {

    //         $mapat = [];
    //         // primul set
    //         if (isset($alias[$city])) {
    //             $mapat = $alias[$city];

    //             foreach ($hotel['mappedLocations'] as $location) {

    //                 $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                 if (!is_numeric($subid)) {
    //                     throw new Exception('not a number!');
    //                 }
    //                 $subid = (int) $subid;

    //                 if ($location['name'] === $mapat) {
    //                     $mapare[$subid] = $location['name'];
    //                     $mapareIds[$subid] = $location['id'];
    //                 }
    //             }
    //         }

    //         // al doilea set
    //         if (empty($mapare) && isset($secondAlias[$city])) {
    //             $mapat = $secondAlias[$city];

    //             foreach ($hotel['mappedLocations'] as $location) {

    //                 $subid = substr($location['id'], strrpos($location['id'], '-') + 1);
    //                 if (!is_numeric($subid)) {
    //                     throw new Exception('not a number!');
    //                 }
    //                 $subid = (int) $subid;

    //                 if ($location['name'] === $mapat) {
    //                     $mapare[$subid] = $location['name'];
    //                     $mapareIds[$subid] = $location['id'];
    //                 }
    //             }
    //         }
    //         if (!empty($mapare)) {
    //             ksort($mapare);
    //             ksort($mapareIds);
    //             $idOrasAles = $mapareIds[array_key_first($mapareIds)];
    //             $orasAles = $mapare[array_key_first($mapare)];
    //         }
    //     }
    //     return $idOrasAles;
    // }

    // public function apiGetHotelDetails(HotelDetailsFilter $filter): Hotel
    // {
    //     $hotelId = $filter->hotelId;

    //     $options['headers'] = [
    //         'Authorization' => 'Basic ' . base64_encode($this->username . ':' . $this->password),
    //         'Content-Type' => 'application/json'
    //     ];
    //     $options['body'] = json_encode([
    //         'username' => $this->username,
    //         'password' => $this->password
    //     ]);

    //     $resp = $this->client->request(RequestFactory::METHOD_POST, $this->apiUrl . '/info/' . $hotelId, $options);

    //     $content = $resp->getBody();

    //     $hotel = json_decode($content, true)['result'];
    //     $hotels = $this->apiGetHotels();

    //     $hotelFromList = $hotels->get($hotel['id']);
    //     if ($hotelFromList === null) {
    //         return new Hotel();
    //     }

    //     $hotelFromList->Content->Content = $hotel['description'];

    //     $contact = new ContactPerson();
    //     $contact->Email = $hotel['contact']['email'];
    //     $contact->Fax = $hotel['contact']['fax'];
    //     $contact->Phone = $hotel['contact']['telephone'];

    //     $hotelFromList->ContactPerson = $contact;

    //     $facilities = new FacilityCollection();

    //     foreach ($hotel['services']['Hotel Services'] ?? [] as $k => $facility) {
    //         $facilityObj = Facility::create($k, $facility);
    //         $facilities->add($facilityObj);
    //     }
    //     $hotelFromList->Facilities = $facilities;

    //     $images = new HotelImageGalleryItemCollection();
    //     foreach ($hotel['photos'] ?? [] as $photo) {
    //         $image = HotelImageGalleryItem::create($photo);
    //         $images->add($image);
    //     }
    //     $gallery = new HotelImageGallery();
    //     $gallery->Items = $images;
    //     $hotelFromList->Content->ImageGallery = $gallery;

    //     return $hotelFromList;
    // }
}
