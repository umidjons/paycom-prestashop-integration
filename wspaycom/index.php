<?php
require_once 'vendor/autoload.php';
require_once(__DIR__ . '/../config/defines.inc.php');
require_once(__DIR__ . '/../config/settings.inc.php');
require_once(_PS_CONFIG_DIR_ . 'autoload.php');
require_once _PS_CONFIG_DIR_ . 'bootstrap.php';

use Paycom\Paycom;
use Paycom\Helper;

$data = Helper::RequestPayload();

$ws = new Paycom($data);

if (!$data) {
    return $ws->error(-32600, 'Передан неправильный JSON-RPC объект.');
}

switch ($data['method']) {
    case 'CheckPerformTransaction':
        $ws->CheckPerformTransaction($data['params']['amount'], $data['params']['account']);
        break;
    case 'CreateTransaction':
        $ws->CreateTransaction($data['id'], $data['time'], $data['amount'], $data['account']);
        break;
    default:
        $ws->error(-32601, 'Запрашиваемый метод не найден.', $data['method']);
        break;
}
