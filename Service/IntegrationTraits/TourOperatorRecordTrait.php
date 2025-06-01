<?php

namespace IntegrationTraits;

use App\Support\Http\RequestLog;
use App\Support\Http\RequestLogCollection;
use App\Support\Request;
use stdClass;

trait TourOperatorRecordTrait
{
    private stdClass $TourOperatorRecord;
    private $_curl_handle;
    private $_reqHeaders;
    private ?string $ApiUrl__ = null;
    private ?string $ApiUrl = null;
    private ?string $ApiUsername__ = null;
    private ?string $ApiPassword__ = null;
    private ?string $ApiUsername = null;
    private ?string $ApiPassword = null;
    private ?string $ApiContext = null;
    private ?string $ApiCode = null;


    public function __construct()
    {
        $request = new Request();
        $post = $request->getPostParams();
        if (count($post) == 0) {
            $post = $request->getInputParams();
        }
        $this->TourOperatorRecord = new stdClass();
        $this->TourOperatorRecord->ApiUsername = $post['to']['ApiUsername'];
        $this->TourOperatorRecord->ApiPassword = $post['to']['ApiPassword'];
        $this->TourOperatorRecord->Handle = $post['to']['Handle'] ?? null;
        $this->TourOperatorRecord->ApiUrl__ = $post['to']['ApiUrl'];
        $this->TourOperatorRecord->ApiContext__ = $post['to']['ApiContext'] ?? null;
        $this->TourOperatorRecord->ApiCode__ = $post['to']['ApiCode'] ?? null;

        $this->ApiUrl__ = $post['to']['ApiUrl'];
        $this->ApiUrl = $post['to']['ApiUrl'];
        $this->ApiUsername__ = $post['to']['ApiUsername'];
        $this->ApiPassword__ = $post['to']['ApiPassword'];
        $this->ApiUsername = $post['to']['ApiUsername'];
        $this->ApiPassword = $post['to']['ApiPassword'];
        $this->ApiContext = $post['to']['ApiContext'];
        $this->ApiCode = $post['to']['ApiContext'];
    }
}
