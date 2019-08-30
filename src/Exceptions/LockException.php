<?php

namespace Azonmedia\Lock\Exceptions;

use Azonmedia\Lock\Interfaces\LockExceptionInterface;

/**
 * Class LockException
 * To be thrown when the exception could not be obtained.
 * @package Azonmedia\Lock\Exceptions
 */
class LockException extends \Exception
implements LockExceptionInterface
{

}