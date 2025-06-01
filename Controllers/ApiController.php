<?php

namespace Controllers;

use Exception;
use HttpClient\HttpClient;
use Psr\Http\Message\ServerRequestInterface;
use Router\JsonResponse;
use Service\Integrations\OneTourismo\OneTourismoApiService;
use Service\IntegrationSupport\AbstractApiService;
use Service\IntegrationSupport\AbstractApiServiceAdaptor;
use Service\Omi\TF\TOInterface;
use Throwable;

class ApiController
{
    public function __construct(private ServerRequestInterface $request)
    {
    }

    public function post(): JsonResponse
    {
        $post = $this->request->getParsedBody();

        //return new JsonResponse($post);

        // log error
        if (empty($post['method']) || empty($post['to']['Handle']) || empty($post['to']['System_Software'])) {
            $data = ['response' => $post];
            return new JsonResponse($data, JsonResponse::STATUS_BAD_REQUEST);
        }


        $handle = $post['to']['Handle'];

        try {
            $method = $post['method'];
            $filter = $post['args'] ?? [];
            $service = $this->getService();

            $methodMap = $this->methodMap();

            $method = $methodMap[$method];
 
            $data = $service->$method(...$filter);
        } catch (Throwable $ex) {
            return new JsonResponse(['error' => $ex->getMessage() . ' in ' . $ex->getFile() . ' on line ' . $ex->getLine()], JsonResponse::STATUS_INTERNAL_SERVER_ERROR);
            // $error = $ex->getMessage() . ' in '
            //     . $ex->getFile() . ' line '
            //     . $ex->getLine() . PHP_EOL
            //     //. 'Short request data: ' . json_encode($this->getShortRequest($post)) . PHP_EOL
            //     . 'Request data: ' . json_encode_pretty($post) . PHP_EOL
            //     . ' - ToRequestsAndResponses: ' . json_encode_pretty($service ? $service->getResponses() : null) . PHP_EOL
            //     . ' - Stack trace:' . PHP_EOL
            //     . $ex->getTraceAsString() . PHP_EOL;
                //. 'SERVER: ' . json_encode_pretty($_SERVER) . PHP_EOL
                //. 'headers: ' . json_encode_pretty(getallheaders()) . PHP_EOL;


        }

        // $json = new JsonResponse(['response' => $data, 'error' => $error]);
        $respArr = ['response' => $data];
        $json = new JsonResponse($respArr);
        return $json;

        $shortResp = substr($json->getJson(), 0, 300);

        if (strlen($shortResp) === 300 ) {
            $shortResp .= '...';
        }


        if ($error !== null) {
            $respArr['error'] = $error;
        }

        if (!empty($post['get-raw-data']) || $method === 'api_doBooking') {
            if ($service !== null ) {
                $toRequestResponse = $service->getResponses();
                $respArr['toRequestsAndResponses'] = $toRequestResponse;
            }
        }

        if (!empty($post['get-raw-data'])) {
            $json = new JsonResponse($respArr);
        }


        if ($error !== null) {
            http_response_code(500);

            // $respArr = ['error' => $error];

            // if ((!empty($post['get-raw-data']) || $method === 'api_doBooking') && $service !== null) {
            //     $toRequestResponse = $service->getResponses();
            //     $respArr['toRequestsAndResponses'] = $toRequestResponse;
            // }

            $json = new JsonResponse($respArr);   
        }

        return $json;
    } 

    public function getService(): AbstractApiService
    {
        $post = $this->request->getParsedBody();

        $serviceName = $post['to']['System_Software'];
        $service = null;

        $client = new HttpClient();

        switch ($serviceName) {
            // case 'h2b_software':
            //     $service = new H2B();
            //     break;
            // case 'infinitehotel':
            //     $service = new InfiniteApiService();
            //     break;
            // case 'cyberlogic':
            //     $service = new CyberlogicApiService();
            //     break;
            case 'onetourismo':
                $service = new OneTourismoApiService($this->request, $client);
                break;
            // case 'alladyn-hotels':
            //     $service = new AlladynHotelsApiService();
            //     break;
            // case 'alladyn-charters':
            //     $service = new AlladynChartersApiService();
            //     break;
            // case 'apitude':
            //     $service = new ApitudeApiService();
            //     break;
            // case 'eurosite':
            //     $service = new EuroSiteApiService();
            //     break;
            // case 'amara':
            //     $service = new AmaraApiService();
            //     break;
            // case 'etrip-agency':
            //     $service = new EtripAgencyApiService();
            //     break;
            // case 'brostravel':
            //     $service = new BrosTravelApiService();
            //     break;
            // case 'odeon':
            //     $service = new OdeonApiService();
            //     break;
            // case 'beapi':
            //     $service = new DertourApiService();
            //     break;
            // case 'etrip':
            //     $service = new EtripApiService();
            //     break;
            // case 'travelio':
            //     $service = new TravelioKarpaten();
            //     break;
            // case 'travelio_v2':
            //     $service = new TravelioApiService();
            //     break;
            // case 'tourvisio':
            //     $service = new TourVisioNew();
            //     break;
            // case 'tourvisio_v2':
            //     $service = new TourVisioApiService();
            //     break;
            // case 'alladyn_old':
            //     $service = new TezTour_old();
            //     break;
            // case 'goglobal':
            //     $service = new GoGlobal_old();
            //     break;
            // case 'megatec':
            //     $service = new MegatecApiService($client);
            //     break;
            // case 'goglobal_v2':
            //     $service = new GoGlobalApiService();
            //     break;
            // case 'sejour':
            //     $service = new Sejour();
            //     break;
            // case 'sansejour':
            //     $service = new SanSejourApiService();
            //     break;
            // case 'teztour_v2':
            //     $service = new TeztourV2ApiService();
            //     break;
            // case 'calypso':
            //     $service = new Calypso();
            //     break;
            // case 'samo':
            //     $service = new SamoApiService($client);
            //     break;
            // case 'tbo':
            //     $service = new TboApiService();
            //     break;
            // case 'sphinx':
            //     $service = new SphinxApiService();
            //     break;
            // case 'hotelcon':
            //     $service = new HotelconApiService();
            //     break;
            // case 'etg':
            //     $service = new EtgApiService();
            //     break;
            // case 'irix':
            //     $service = new IrixApiService();
            //     break;
            // case 'anex':
            //     $service = new AnexApiService($client);
        }

        // if ($service == null) {
        //     throw new Exception('Software not found');
        // }

        // if ($service instanceof TOInterface) {
        //     error_reporting(E_ERROR | E_PARSE);
        // } else {
        //     $service = new AbstractApiServiceAdaptor($service);
        // }

        return $service;
    }

    private function methodMap(): array
    {
        return [
            'api_getCountries' => 'apiGetCountries'
        ];
    }
}
