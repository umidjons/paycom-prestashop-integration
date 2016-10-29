<?php

namespace Paycom;

class PaycomBase implements Interfaces\PaycomInterface
{
    const ERROR_INVALID_AMOUNT = -31001;
    const ERROR_INVALID_ACCOUNT = -31050;

    protected $params;

    public function __construct($params)
    {
        $this->params = $params;
    }

    public function respond($response, $error = null)
    {
        header('Content-Type: text/json; charset=UTF-8');

        $resp['id'] = $this->params['id'];
        $resp['result'] = $response;
        $resp['error'] = $error;

        echo json_encode($resp);
    }

    public function error($code, $message = null, $data = null)
    {
        $error = ['code' => $code];

        if ($message) {
            $error['message'] = $message;
        }

        if ($data) {
            $error['data'] = $data;
        }

        $this->respond(null, $error);
    }

    public function message($ru, $uz = '', $en = '')
    {
        return [
            'ru' => $ru,
            'uz' => $uz,
            'en' => $en,
        ];
    }

    public function CheckPerformTransaction($amount, $account)
    {
        // todo: find customer
        // todo: find order by order code and check price
        // todo: check, is order price equal to the received price
        // todo: check, is order's customer equal to the received customer
        $this->respond(['allow' => true]);
    }

    public function CreateTransaction($id, $time, $amount, $account)
    {
        $this->respond(
            [
                'create_time' => Helper::timestamp(),
                'transaction' => '123123123',
                'state' => Transaction::STATE_CREATED,
                'receivers' => null
            ]
        );
    }

    public function PerformTransaction($id)
    {
        // TODO: Implement PerformTransaction() method.
    }

    public function CancelTransaction($id, $reason)
    {
        // TODO: Implement CancelTransaction() method.
    }

    public function CheckTransaction($id)
    {
        // TODO: Implement CheckTransaction() method.
    }

    public function GetStatement($from, $to)
    {
        // TODO: Implement GetStatement() method.
    }

    public function ChangePassword($password)
    {
        // TODO: Implement ChangePassword() method.
    }
}