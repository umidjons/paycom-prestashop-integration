<?php
namespace Paycom;

error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Class Paycom
 * @package Paycom
 */
class Paycom extends PaycomBase
{
    const ORDER_STATE_WAITING_PAY = 1;
    const ORDER_STATE_PAY_ACCEPTED = 2;
    const ORDER_STATE_CANCELLED = 6;
    const ORDER_STATE_RETURN_MONEY = 7;

    protected $order;
    protected $customer_id;
    protected $order_reference;
    protected $transaction_id;
    protected $create_time;
    protected $cancel_time;
    protected $perform_time;
    protected $pay_time;
    protected $state;

    public static function InitPrestashop()
    {
        if (!defined('__PS_BASE_URI__')) {
            define('__PS_BASE_URI__', '/');
        }

        if (!defined('_PS_PRICE_DISPLAY_PRECISION_')) {
            define('_PS_PRICE_DISPLAY_PRECISION_', 2);
        }

        // Initializing PrestaShop context
        $context = \Context::getContext();
        $context->shop = new \Shop(\Shop::CONTEXT_ALL);
        $context->shop->setContext(\Shop::CONTEXT_SHOP, $context->shop->id);

        \Language::loadLanguages();
        $context->language = new \Language(1);

        if (!defined('_PS_THEME_DIR')) {
            define('_PS_THEME_DIR_', _PS_CONFIG_DIR_ . '../themes');
        }

        require_once(__DIR__ . '/../../config/smarty.config.inc.php');
        $context->smarty = $smarty;

        if (!defined('_THEME_NAME_')) {
            define('_THEME_NAME_', $context->shop->getTheme());
        }


    }

    public function hasAmount()
    {
        // check amount value
        if (!$this->amount || $this->amount < 0) {
            $this->error(
                self::ERROR_INVALID_AMOUNT,
                PaycomException::message(
                    "Неверная сумма.",
                    "Noto'ri summa.",
                    "Incorrect amount."
                ),
                'amount'
            );
        }
    }

    public function hasCustomer()
    {
        $this->customer_id = $this->account('customer');

        // check account customer
        if (!$this->customer_id) {
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    "Заказчик не указан.",
                    "Haridor ko'rsatilmagan.",
                    "Customer not specified."
                ),
                'customer'
            );
        }
    }

    public function hasReference()
    {
        $this->order_reference = $this->account('reference');

        // check account reference
        if (!$this->order_reference) {
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    "Код заказа не указан.",
                    "Harid kodi ko'rsatilmagan.",
                    "Order code not specified."
                ),
                'reference'
            );
        }
    }

    public function findOrderByReference($reference = null)
    {
        $this->order_reference = $reference ? $reference : $this->account('reference');

        // get order by reference
        $order_collection = \Order::getByReference($this->order_reference);
        $this->order = $order_collection->getFirst();

        if (!$this->order) {
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    "Заказ не найден.",
                    "Harid topilmadi.",
                    "Order not found."
                ),
                'reference'
            );
        }
    }

    public function isOrderBelongsToCustomer()
    {
        $this->customer_id = $this->account('customer');

        if ($this->order->id_customer != $this->customer_id) {
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    "Заказ не принадлежить указанному заказчику.",
                    "Harid kodi bu haridorga tegishli emas.",
                    "Order code does not belong to this customer."
                ),
                'reference'
            );
        }
    }

    public function isAmountEqualToOrderAmount()
    {
        if (Helper::toSom($this->amount) != $this->order->total_paid) {
            $this->error(
                self::ERROR_INVALID_AMOUNT,
                PaycomException::message(
                    "Неверная сумма.",
                    "Noto'ri summa.",
                    "Incorrect amount."
                ),
                'amount'
            );
        }
    }

    public function dropTransactionsTableIfExists()
    {
        $sql = 'DROP TABLE IF EXISTS `paycom_transactions`';
        return \Db::getInstance()->execute($sql, false);
    }

    public function createTransactionsTableIfNotExists()
    {
        $sql = <<<CREATE_TABLE
CREATE TABLE IF NOT EXISTS `paycom_transactions` (
    `id`                  int NOT NULL auto_increment,
    `request_id`          int NULL,
    `paycom_transaction`  varchar(30) NULL,
    `paycom_time`         timestamp NULL,
    `paycom_time_str`     varchar(13) NULL,
    `amount`              decimal(12,2) NULL,
    `customer_id`         int NULL,
    `order_reference`     varchar(10) NULL,
    `state`               int(2) NULL,
    `reason`              int(2) NULL,
    `create_time`         timestamp NULL,
    `cancel_time`         timestamp NULL,
    `pay_time`            timestamp NULL,
    PRIMARY KEY (`id`)
);
CREATE_TABLE;

        return \Db::getInstance()->execute($sql, false);
    }

    public function saveTransaction()
    {
        $create_time = Helper::timestamp();

        $data = [
            'request_id' => $this->params['id'],
            'paycom_transaction' => $this->param('id'),
            'paycom_time' => Helper::timestampToDatetime(Helper::toTimestampShort($this->param('time'))),
            'paycom_time_str' => $this->param('time'),
            'amount' => $this->amount,
            'customer_id' => $this->customer_id,
            'order_reference' => $this->order_reference,
            'state' => Transaction::STATE_CREATED,
            'create_time' => Helper::timestampToDatetime($create_time)
        ];

        Logger::log_line(__METHOD__, 'data=', $data);

        //$this->respond($data);

        $success = \Db::getInstance()->insert('paycom_transactions', $data, false, false, \Db::INSERT, false);

        Logger::log_line(__METHOD__, 'success=', $success);

        if ($success) {
            $this->create_time = $create_time;
            $this->transaction_id = \Db::getInstance()->Insert_ID();

            Logger::log_line(__METHOD__, 'create_time=', $create_time, 'transaction_id=', $this->transaction_id);

            return $success;
        }

        return null;
    }

    public function findTransaction($justFind = false)
    {
        $sql = "select * from paycom_transactions where paycom_transaction='" . $this->param('id') . "'";
        $row = \Db::getInstance()->getRow($sql, false);

        Logger::log_line(__METHOD__, '$transaction=', $row);

        if (!$justFind && $row && $row['state'] != Transaction::STATE_CREATED) {
            $this->error(self::ERROR_COULD_NOT_PERFORM, 'Невозможно выполнить данную операцию.');
        }

        return $row;
    }

    public function _cancelTransaction($reason, $from_state = Transaction::STATE_CREATED)
    {
        $cancel_time = Helper::timestamp();

        $to_state = Transaction::STATE_CANCELLED;

        if ($from_state == Transaction::STATE_COMPLETED) {
            $to_state = Transaction::STATE_CANCELLED_AFTER_COMPLETE;
        }

        $data = [
            'cancel_time' => Helper::timestampToDatetime($cancel_time),
            'state' => $to_state,
            'reason' => $reason
        ];

        Logger::log_line(__METHOD__, 'data=', $data);

        $condition = sprintf(
            "paycom_transaction='%s' and state=%d",
            $this->param('id'),
            $from_state
        );

        Logger::log_line(__METHOD__, 'condition=', $condition);

        $success = \Db::getInstance()->update('paycom_transactions', $data, $condition, 0, false, false, false);

        Logger::log_line(__METHOD__, 'success=', $success);
        Logger::log_line(__METHOD__, 'error=', \Db::getInstance()->getMsgError());
        Logger::log_line(__METHOD__, 'cancel_time=', $cancel_time);

        $this->cancel_time = $cancel_time;
        $this->state = $to_state;

        if ($reason == Reason::REASON_CANCEL_BY_TIMEOUT) {
            $this->error(self::ERROR_COULD_NOT_PERFORM, 'Невозможно выполнить данную операцию.');
        }

        return $success;
    }

    /**
     * Checks whether transaction is timed out or not.
     * @param int|null $trans_time transaction time in timestamp, if not specified time will be gotten from request params.
     * @return bool
     */
    public function isTransactionTimedOut($trans_time = null)
    {
        $transaction_time = null;

        if (isset($trans_time)) {
            $transaction_time = 1 * $trans_time;
        } elseif ($this->param('time')) {
            $transaction_time = 1 * $this->param('time');
        } else {
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    'Время создания транзакции не указана.',
                    'Tranzaksiya yaratilgan vaqti keltirilmagan.',
                    'Transaction create time not specified.'
                ),
                'time'
            );
        }

        $now_ms = Helper::timestamp(true);

        Logger::log_line(__METHOD__, '$now_ms - $transaction_time =', $now_ms - $transaction_time);

        return ($now_ms - $transaction_time) >= self::TRANSACTION_TIMEOUT;
    }

    public function markOrderAsPayed()
    {
        // set order's current state to payed
        Logger::log_line(__METHOD__, 'Order=', $this->order->id);
        $this->order->setWsCurrentState(self::ORDER_STATE_PAY_ACCEPTED);

        $perform_time = Helper::timestamp();

        $data = [
            'pay_time' => Helper::timestampToDatetime($perform_time),
            'state' => Transaction::STATE_COMPLETED
        ];

        Logger::log_line(__METHOD__, 'data=', $data);

        $condition = sprintf("paycom_transaction='%s'", $this->param('id'));

        Logger::log_line(__METHOD__, 'condition=', $condition);

        // update transaction on db
        $success = \Db::getInstance()->update('paycom_transactions', $data, $condition, 0, false, false, false);

        Logger::log_line(__METHOD__, 'success=', $success);
        Logger::log_line(__METHOD__, 'error=', \Db::getInstance()->getMsgError());
        Logger::log_line(__METHOD__, 'perform_time=', $perform_time);

        $this->perform_time = $perform_time;
        $this->state = Transaction::STATE_COMPLETED;

        return $success;
    }

    public function CheckPerformTransaction()
    {
        $this->hasAmount();
        $this->hasCustomer();
        $this->hasReference();

        $this->findOrderByReference();

        $this->isOrderBelongsToCustomer();
        $this->isAmountEqualToOrderAmount();

        $this->respond(['allow' => true]);
    }

    public function CreateTransaction()
    {
        $this->hasAmount();
        $this->hasCustomer();
        $this->hasReference();

        $this->findOrderByReference();

        // todo: If order state=self::ORDER_STATE_PAY_ACCEPTED|self::ORDER_STATE_CANCELLED|self::ORDER_STATE_RETURN_MONEY, give error
        // todo: If order state=self::ORDER_STATE_WAITING_PAY and there is active transaction, give error

        $this->isOrderBelongsToCustomer();
        $this->isAmountEqualToOrderAmount();

        //$this->dropTransactionsTableIfExists();
        //$this->createTransactionsTableIfNotExists();

        $existing_transaction = $this->findTransaction();

        if ($existing_transaction) {
            if ($this->isTransactionTimedOut()) {
                $this->_cancelTransaction(Reason::REASON_CANCEL_BY_TIMEOUT);
            } else {
                return $this->respond(
                    [
                        'create_time' => Helper::datetimeToTimestamp($existing_transaction['create_time']),
                        'transaction' => $existing_transaction['id'],
                        'state' => 1 * $existing_transaction['state'],
                        'receivers' => null
                    ]
                );
            }
        }

        // check, is transaction timeout?
        if ($this->isTransactionTimedOut()) {
            // not existing transaction, but create time already timed out
            $this->error(
                self::ERROR_INVALID_ACCOUNT,
                PaycomException::message(
                    'С даты создания транзакции прошло ' . self::TRANSACTION_TIMEOUT . 'мс',
                    'Tranzaksiya yaratilgan sanadan ' . self::TRANSACTION_TIMEOUT . 'ms o`tgan',
                    'Since create time of the transaction passed ' . self::TRANSACTION_TIMEOUT . 'ms'
                ),
                'time'
            );
        }

        $transaction_id = $this->saveTransaction();

        // todo: Change order state to self::ORDER_STATE_WAITING_PAY

        $this->respond(
            [
                'create_time' => $this->create_time,
                'transaction' => $this->transaction_id,
                'state' => Transaction::STATE_CREATED,
                'receivers' => null
            ]
        );
    }

    public function CheckTransaction()
    {
        $transaction = $this->findTransaction(true);

        if (!$transaction) {
            $this->error(self::ERROR_TRANSACTION_NOT_FOUND, 'Транзакция не найдена');
        }

        $reason = isset($transaction['reason']) ? 1 * $transaction['reason'] : null;

        $this->respond(
            [
                'create_time' => Helper::datetimeToTimestamp($transaction['create_time']),
                'perform_time' => isset($transaction['pay_time']) ?
                    Helper::datetimeToTimestamp($transaction['pay_time']) : 0,
                'cancel_time' => isset($transaction['cancel_time']) ?
                    Helper::datetimeToTimestamp($transaction['cancel_time']) : 0,
                'transaction' => $transaction['id'],
                'state' => 1 * $transaction['state'],
                'reason' => $reason
            ]
        );
    }

    public function CancelTransaction()
    {
        $transaction = $this->findTransaction(true);

        if (!$transaction) {
            $this->error(self::ERROR_TRANSACTION_NOT_FOUND, 'Транзакция не найдена');
        }

        $transaction['state'] *= 1;

        switch ($transaction['state']) {
            // if already cancelled, just return cancel data
            case Transaction::STATE_CANCELLED:
            case Transaction::STATE_CANCELLED_AFTER_COMPLETE:
                $this->respond(
                    [
                        'transaction' => $transaction['id'],
                        'cancel_time' => Helper::datetimeToTimestamp($transaction['cancel_time']),
                        'state' => $transaction['state']
                    ]
                );
                break;
            // if active, cancel it with given result
            case Transaction::STATE_CREATED:
                $this->_cancelTransaction($this->param('reason'), $transaction['state']);
                $this->respond(
                    [
                        'transaction' => $transaction['id'],
                        'cancel_time' => $this->cancel_time,
                        'state' => $this->state
                    ]
                );
                break;
            case Transaction::STATE_COMPLETED:
                // todo: need testing
                $this->findOrderByReference($transaction['reference']);
                if ($this->order->isReturnable()) {
                    $success = $this->_cancelTransaction($this->param('reason'), $transaction['state']);
                    if ($success) {
                        $this->order->setCurrentState(self::ORDER_STATE_CANCELLED);
                    }
                }
                break;
        }
    }

    public function PerformTransaction()
    {
        $transaction = $this->findTransaction(true);

        if (!$transaction) {
            $this->error(self::ERROR_TRANSACTION_NOT_FOUND, 'Транзакция не найдена');
        }

        // transaction found, check state
        switch ($transaction['state'] * 1) {
            case Transaction::STATE_CREATED:
                if ($this->isTransactionTimedOut($transaction['paycom_time_str'])) {
                    $this->_cancelTransaction(Reason::REASON_CANCEL_BY_TIMEOUT);
                } else {
                    // get order to update state
                    $this->findOrderByReference($transaction['order_reference']);

                    // mark order as payed
                    $this->markOrderAsPayed();

                    $this->respond(
                        [
                            'transaction' => $transaction['id'],
                            'perform_time' => $this->perform_time,
                            'state' => $this->state
                        ]
                    );
                }
                break;
            case Transaction::STATE_COMPLETED:
                $this->respond(
                    [
                        'transaction' => $transaction['id'],
                        'perform_time' => Helper::datetimeToTimestamp($transaction['pay_time']),
                        'state' => 1 * $transaction['state']
                    ]
                );
                break;
            default:
                $this->error(self::ERROR_COULD_NOT_PERFORM, 'Невозможно выполнить данную операцию.');
                break;
        }
    }
}