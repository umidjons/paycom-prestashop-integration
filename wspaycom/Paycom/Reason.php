<?php

namespace Paycom;

class Reason
{
    const REASON_RECEIVERS_NOT_FOUND = 1;    // Один или несколько получателей не найдены или не активны в Paycom
    const REASON_PROCESSING_EXECUTION_FAILED = 2; // Ошибка при выполнении дебетовой операции в процессингом центре.
    const REASON_TRANSACTION_EXECUTION_FAILED = 3; // Ошибка выполнения транзакции
    const REASON_CANCEL_BY_TIMEOUT = 4; // Отменена по таймауту
    const REASON_RETURN_FUND = 5; // Возврат денег
    const REASON_UNKNOWN = 10; // Неизвестная ошибка
}