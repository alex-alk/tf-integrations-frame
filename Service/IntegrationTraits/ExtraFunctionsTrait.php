<?php

namespace IntegrationTraits;

use App\Support\Http\CurlRequest;
use App\Support\Http\CurlResponseMutable;
use App\Support\Http\ResponseInterfaceCollection;
use App\Support\Request;

trait ExtraFunctionsTrait_old
{
    private ResponseInterfaceCollection $responses;

    public function getResponses(): ResponseInterfaceCollection
    {
        if (!isset($this->responses)) {
            $this->responses = new ResponseInterfaceCollection();
        }
        return $this->responses;
    }

    /*
    public function logData(string $label, array $data, int $keep = 30, bool $err = false)
    {
        if (!isset($this->responses)) {
            $this->responses = new ResponseInterfaceCollection();
        }
        $url = $this->getApiUrl($data['$method']);

        $curlRequest = new CurlRequest('POST', $url, ['body' => json_encode($data['\$callParams'])]);
        $request = new Request();
        if ($request->getPostParams('get-raw-data')) {
            $this->responses->add(new CurlResponseMutable($curlRequest, $data['respJSON']));
        }
        //static::DoDataLogging($this->TourOperatorRecord->Handle, $label, $data, $keep, $err);
    }

    public function markReportStartpoint($filter, $type): void
    {
//        if (!isset($filter['skip_report'])) {
//            $output = "TOP_RAW: Process for {$type} started at " . date('Y-m-d H:i:s');
//            $outputLen = strlen($output);
//            $output = "<strong>{$output}</strong>";
//            $output .= "<br/>" . str_repeat('=', $outputLen) . "<br/>";
//            echo str_repeat('>', $outputLen) . "<br/>" . $output;
//        }
    }
    
    public function markReportData($filter, $format, $values = [], $offset = 0, $error = false): void
    {
//        if (!isset($filter['skip_report']))
//            echo '<div style="padding-left: ' . $offset . 'px;' . ($error ? 'color: red;' : '')
//                . '">TOP_RAW: ' . call_user_func_array('sprintf', array_merge([$format], $values)) . "</div>";
    }
    public function markReportError($filter, $format, $values = [], $offset = 0): void
    {
        //static::markReportData($filter, $format, $values, $offset, true);
    }
    public function logError(array $data = [], \Exception $ex = null, int $keep = 1): void
    {
        //static::DoDataLoggingError($this->TourOperatorRecord->Handle, $data, $ex, null, $keep);
    }
    public function markReportEndpoint($filter, $type)
    {
//        if (!isset($filter['skip_report'])) {
//            $output = "TOP_RAW: Process for {$type} ended at " . date('Y-m-d H:i:s');
//            $outputLen = strlen($output);
//            $output = "<strong>{$output}</strong>";
//            $output .= "<br/>" . str_repeat('>', $outputLen) . "<br/><br/>";
//            echo $output;
//        }
    }

    public static function DoDataLoggingError(string $saveDir, array $data = [], \Exception $ex = null, string $label = null, int $keep = 1)
    {
//        if ($ex) {
//            $data["exception"] = [
//                "message" => $ex->getMessage(),
//                "file" => $ex->getFile(),
//                "line" => $ex->getLine(),
//                "trace" => $ex->getTraceAsString()
//            ];
//        }
//        if ($label === null)
//            $label = "req.error";
//        static::DoDataLogging($saveDir, $label, $data, $keep, true);
    }

    public function DoDataLoggingSimple(string $saveDir, string $label, array $data)
    {
        //static::DoDataLogging($saveDir, $label, $data, 0);
    }

    public static function DoDataLogging(string $saveDir, string $label, array $data, int $keep = 30, bool $err = false)
    {
//        $useLabel = $label . ((defined('USE_ONLY_LABEL_IN_DATA_LOGGING') && USE_ONLY_LABEL_IN_DATA_LOGGING) ? "" :
//            "__" . $_SERVER["REMOTE_ADDR"] . "__" . getmypid() . "__" . date("Y_m_d_H_i_s") . "." . uniqid());
//        $respXML = null;
//        $respJSON = null;
//        if ($data["respXML"]) {
//            $respXML = $data["respXML"];
//            $data["respXML"] = $useLabel . ".resp.xml";
//        }
//        if ($data["respJSON"]) {
//            $respJSON = $data["respJSON"];
//            $data["respJSON"] = $useLabel . ".resp.json";
//        }
//        $reqXML = null;
//        $reqJSON = null;
//        if ($data["reqXML"]) {
//            $reqXML = $data["reqXML"];
//            $data["reqXML"] = $useLabel . ".req.xml";
//        }
//        if ($data["reqJSON"]) {
//            $reqJSON = $data["reqJSON"];
//            $data["reqJSON"] = $useLabel . ".req.json";
//        }
//
//        #if (!($_POST['__q__in_remote_req']))
//        {
//            ob_start();
//            dump($label, $data, $_SERVER, $_GET, $_POST);
//            $str = ob_get_clean();
//        }

        /*
        else
        {
        $str = qvardump($label, $data, $_SERVER, $_GET, $_POST);
        }
        */
        /*
        $log_dir = \Omi\App::GetLogsDir(($err ? "requests_err_log" : "requests_log") . ($keep ? "_{$keep}" : "") . "/" . $saveDir);

        file_put_contents($log_dir . $useLabel . ".html", $str);
        if ($reqXML)
            file_put_contents($log_dir . $data["reqXML"], $reqXML);
        if ($respXML)
            file_put_contents($log_dir . $data["respXML"], $respXML);
        if ($respJSON)
            file_put_contents($log_dir . $data["respJSON"], $respJSON);
        if ($reqJSON)
            file_put_contents($log_dir . $data["reqJSON"], $reqJSON);
        */
    //}
}