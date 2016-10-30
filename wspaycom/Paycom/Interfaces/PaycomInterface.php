<?php

namespace Paycom\Interfaces;

interface PaycomInterface
{
    public function CheckPerformTransaction();

    public function CreateTransaction();

    public function PerformTransaction($id);

    public function CancelTransaction();

    public function CheckTransaction();

    public function GetStatement($from, $to);

    public function ChangePassword($password);
}