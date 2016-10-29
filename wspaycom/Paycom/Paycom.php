<?php
namespace Paycom;

error_reporting(E_ALL);
ini_set('display_errors', 1);

class Paycom extends PaycomBase
{
    public function CheckPerformTransaction($amount, $account)
    {
        // convert amount to number
        $amount *= 1;

        // check amount value
        if (!$amount || $amount < 0) {
            return $this->error(
                self::ERROR_INVALID_AMOUNT,
                $this->message("Неверная сумма.", "Noto'ri summa.", "Incorrect amount."),
                'amount'
            );
        }

        $customer = isset($account['customer']) ? $account['customer'] : null;
        $reference = isset($account['reference']) ? $account['reference'] : null;

        // check account parameters
        if (!$customer) {
            return $this->error(
                self::ERROR_INVALID_ACCOUNT,
                $this->message("Заказчик не указан.", "Haridor ko'rsatilmagan.", "Customer not specified."),
                'customer'
            );
        }

        if (!$reference) {
            return $this->error(
                self::ERROR_INVALID_ACCOUNT,
                $this->message("Код заказа не указан.", "Harid kodi ko'rsatilmagan.", "Order code not specified."),
                'reference'
            );
        }

        // get order by reference
        $order_collection = \Order::getByReference($reference);
        $order = $order_collection->getFirst();

        if (!$order) {
            return $this->error(
                self::ERROR_INVALID_ACCOUNT,
                $this->message("Заказ не найден.", "Harid topilmadi.", "Order not found."),
                'reference'
            );
        }

        if ($order->id_customer != $customer) {
            return $this->error(
                self::ERROR_INVALID_ACCOUNT,
                $this->message(
                    "Заказ не принадлежить указанному заказчику.",
                    "Harid kodi bu haridorga tegishli emas.",
                    "Order code does not belong to this customer."
                ),
                'reference'
            );
        }

        if (Helper::toSom($amount) != $order->total_paid) {
            return $this->error(
                self::ERROR_INVALID_AMOUNT,
                $this->message("Неверная сумма.", "Noto'ri summa.", "Incorrect amount."),
                'amount'
            );
        }

        return $this->respond(['allow' => true]);
    }
}