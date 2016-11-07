<?php

namespace Paycom;

class PaycomException extends \Exception
{
    const ERROR_INTERNAL_SYSTEM = -32400;
    const ERROR_INVALID_JSON_RPC_OBJECT = -32600;
    const ERROR_METHOD_NOT_FOUND = -32601;

    public $request_id;
    public $error;
    public $data;

    public function __construct($request_id, $message, $code, $data = null)
    {
        $this->request_id = $request_id;
        $this->message = $message;
        $this->code = $code;
        $this->data = $data;
    }

    public function send()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $err = ['code' => $this->code];

        if ($this->message) {
            $err['message'] = $this->message;
        }

        if ($this->data) {
            $err['data'] = $this->data;
        }

        $resp['id'] = $this->request_id;
        $resp['result'] = null;
        $resp['error'] = $err;

        Logger::log_line(__METHOD__, 'error=', json_encode($resp, JSON_UNESCAPED_UNICODE));

        echo json_encode($resp);
    }

    public static function message($ru, $uz = '', $en = '')
    {
        return ['ru' => $ru, 'uz' => $uz, 'en' => $en];
    }
}