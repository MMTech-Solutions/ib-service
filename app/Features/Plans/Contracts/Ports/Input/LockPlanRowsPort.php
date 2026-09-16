<?php

declare(strict_types=1);

namespace App\Features\Plans\Contracts\Ports\Input;

interface LockPlanRowsPort
{
    /**
     * Locks distinct plan rows in ascending UUID order within the current transaction.
     *
     * @param  list<string>  $planIds
     */
    public function lockAscending(array $planIds): void;
}
