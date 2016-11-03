<?php

namespace Paycom;

class PaycomBase implements Interfaces\PaycomInterface
{
    const ERROR_INVALID_AMOUNT = -31001;
    const ERROR_INVALID_ACCOUNT = -31050;
    const ERROR_COULD_NOT_PERFORM = -31008;
    const ERROR_TRANSACTION_NOT_FOUND = -31003;
    const ERROR_UNSUFFICIENT_PRIVILEGE = -32504;
    const TRANSACTION_TIMEOUT = 43200000; // ms = 12 hours

    protected $config;
    protected $params;
    protected $amount;

    public function __construct($params, $config)
    {
        $this->config = $config;
        $this->params = $params;
        $this->amount = $this->amount();
    }

    public function param($key)
    {
        return isset($this->params['params'][$key]) ? $this->params['params'][$key] : null;
    }

    public function account($param)
    {
        return isset($this->params['params']['account'][$param]) ? $this->params['params']['account'][$param] : null;
    }

    public function amount()
    {
        return isset($this->params['params']['amount']) ? 1 * $this->params['params']['amount'] : null;
    }

    public function respond($response, $error = null)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $resp['id'] = $this->params['id'];
        $resp['result'] = $response;
        $resp['error'] = $error;

        Logger::log_line(__METHOD__, 'response=', json_encode($resp, JSON_UNESCAPED_UNICODE));

        echo json_encode($resp);
    }

    public function error($code, $message = null, $data = null)
    {
        throw new PaycomException($this->params['id'], $message, $code, $data);
    }

    public function Authorize()
    {
        $headers = apache_request_headers();

        if (!$headers || !isset($headers['Authorization']) ||
            !preg_match('/^\s*Basic\s+(\S+)\s*$/i', $headers['Authorization'], $matches) ||
            base64_decode($matches[1]) != $this->config['login'] . ":" . $this->config['key']
        ) {
            $this->error(self::ERROR_UNSUFFICIENT_PRIVILEGE, 'Недостаточно привилегий для выполнения метода.');
        }

        return true;
    }

    public function CheckPerformTransaction()
    {
        // todo: find customer
        // todo: find order by order code and check price
        // todo: check, is order price equal to the received price
        // todo: check, is order's customer equal to the received customer
        // todo: check, order state, is it allowed to pay
        $this->respond(['allow' => true]);
    }

    public function CreateTransaction()
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

    public function CancelTransaction()
    {
        // TODO: Implement CancelTransaction() method.
    }

    public function CheckTransaction()
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