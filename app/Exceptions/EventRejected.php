<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when an event can never be processed in its current state, so retrying it automatically would not help.
 */
class EventRejected extends Exception
{
    //
}
