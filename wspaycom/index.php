<?php
require_once 'vendor/autoload.php';
require_once(__DIR__ . '/../config/defines.inc.php');
require_once(__DIR__ . '/../config/settings.inc.php');
require_once(_PS_CONFIG_DIR_ . 'autoload.php');
require_once _PS_CONFIG_DIR_ . 'bootstrap.php';

use Paycom\Paycom;
use Paycom\PaycomException;
use Paycom\Helper;

$data = Helper::RequestPayload();

try {

    $ws = new Paycom($data);

    if (!$data) {
        throw new PaycomException(
            $data['id'],
            'Передан неправильный JSON-RPC объект.',
            PaycomException::ERROR_INVALID_JSON_RPC_OBJECT
        );
    }

    switch ($data['method']) {
        case 'CheckPerformTransaction':
            $ws->CheckPerformTransaction();
            break;
        case 'CheckTransaction':
            $ws->CheckTransaction();
            break;
        case 'CreateTransaction':
            $ws->CreateTransaction();
            break;
        default:
            $ws->error(PaycomException::ERROR_METHOD_NOT_FOUND, 'Запрашиваемый метод не найден.', $data['method']);
            break;
    }
} catch (PaycomException $e) {
    $e->send();
}