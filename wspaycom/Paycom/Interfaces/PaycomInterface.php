<?php

namespace Paycom\Interfaces;

interface PaycomInterface
{
    public function CheckPerformTransaction($amount, $account);

    public function CreateTransaction($id, $time, $amount, $account);

    public function PerformTransaction($id);

    public function CancelTransaction($id, $reason);

    public function CheckTransaction($id);

    public function GetStatement($from, $to);

    public function ChangePassword($password);
}