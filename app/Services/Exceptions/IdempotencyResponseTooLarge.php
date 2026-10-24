<?php

namespace App\Services\Exceptions;

use RuntimeException;

final class IdempotencyResponseTooLarge extends RuntimeException {}
