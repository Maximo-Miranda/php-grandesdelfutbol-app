<?php

namespace App\Exceptions;

use RuntimeException;

class MatchFullException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('El cupo del partido está lleno.');
    }
}
