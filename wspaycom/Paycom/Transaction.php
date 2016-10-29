<?php

namespace Paycom;

class Transaction
{
    const STATE_INIT = 0;
    const STATE_CREATED = 1;
    const STATE_COMPLETED = 2;
    const STATE_CANCELLED = -1;
    const STATE_CANCELLED_AFTER_COMPLETE = -2;
}