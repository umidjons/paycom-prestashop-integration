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
    protected $order;
    protected $customer_id;
    protected $order_reference;
    protected $transaction_id;
    protected $create_time;
    protected $cancel_time;
    protected $pay_time;

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

    public function findOrderByReference()
    {
        $this->order_reference = $this->account('reference');

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

    public function findTransaction()
    {
        $sql = "select * from paycom_transactions where paycom_transaction='" . $this->param('id') . "'";
        $row = \Db::getInstance()->getRow($sql, false);

        Logger::log_line(__METHOD__, '$transaction=', $row);

        if ($row && $row['state'] != Transaction::STATE_CREATED) {
            $this->error(self::ERROR_COULD_NOT_PERFORM, 'Невозможно выполнить данную операцию.');
        }

        return $row;
    }

    public function cancelTransactionByTimeout()
    {
        $cancel_time = Helper::timestamp();

        $data = [
            'cancel_time' => Helper::timestampToDatetime($cancel_time),
            'state' => Transaction::STATE_CANCELLED,
            'reason' => Reason::REASON_CANCEL_BY_TIMEOUT
        ];

        Logger::log_line(__METHOD__, 'data=', $data);

        $condition = sprintf(
            "paycom_transaction='%s' and state=%d",
            $this->param('id'),
            Transaction::STATE_CREATED
        );

        Logger::log_line(__METHOD__, 'condition=', $condition);

        $success = \Db::getInstance()->update('paycom_transactions', $data, $condition, 0, false, false, false);

        Logger::log_line(__METHOD__, 'success=', $success);
        Logger::log_line(__METHOD__, 'error=', \Db::getInstance()->getMsgError());
        Logger::log_line(__METHOD__, 'cancel_time=', $cancel_time);

        $this->cancel_time = $cancel_time;

        $this->error(self::ERROR_COULD_NOT_PERFORM, 'Невозможно выполнить данную операцию.');
    }

    public function isTransactionTimedOut()
    {
        $now_ms = Helper::timestamp(true);
        $transaction_time = 1 * $this->param('time');

        Logger::log_line(__METHOD__, '$now_ms - $transaction_time =', $now_ms - $transaction_time);

        return ($now_ms - $transaction_time) >= self::TRANSACTION_TIMEOUT;
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

        $this->isOrderBelongsToCustomer();
        $this->isAmountEqualToOrderAmount();

        //$this->dropTransactionsTableIfExists();
        //$this->createTransactionsTableIfNotExists();

        $existing_transaction = $this->findTransaction();

        if ($existing_transaction) {
            if ($this->isTransactionTimedOut()) {
                $this->cancelTransactionByTimeout();
            } else {
                return $this->respond(
                    [
                        'create_time' => strtotime($existing_transaction['create_time']),
                        'transaction' => $existing_transaction['id'],
                        'state' => 1 * $existing_transaction['state'],
                        'receivers' => null
                    ]
                );
            }
        }

        $transaction_id = $this->saveTransaction();

        $this->respond(
            [
                'create_time' => $this->create_time,
                'transaction' => $this->transaction_id,
                'state' => Transaction::STATE_CREATED,
                'receivers' => null
            ]
        );
    }
}