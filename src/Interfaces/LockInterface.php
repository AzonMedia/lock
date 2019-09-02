<?php
declare(strict_types=1);

namespace Azonmedia\Lock\Interfaces;


interface LockInterface
{
    public const LOCK_NL = 1;//Null
    public const LOCK_CR = 2;//ConcurrentRead
    public const LOCK_CW = 4;//ConcurrentWrite
    public const LOCK_PR = 8;//ProtectedRead - This is the traditional share lock
    public const LOCK_PW = 16;//ProtectedWrite - This is the traditional update lock
    public const LOCK_EX = 32;//Exclusive - This is the traditional exclusive lock

    public const LOCK_MAP = [
        self::LOCK_NL   => 'NL',
        self::LOCK_CR   => 'CR',
        self::LOCK_CW   => 'CW',
        self::LOCK_PR   => 'PR',
        self::LOCK_PW   => 'PW',
        self::LOCK_EX   => 'EX',
    ];

    public const LOCK_COMPATIBILITY_MATRIX = [
        self::LOCK_NL => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => TRUE,
            self::LOCK_CW   => TRUE,
            self::LOCK_PR   => TRUE,
            self::LOCK_PW   => TRUE,
            self::LOCK_EX   => TRUE,
        ],
        self::LOCK_CR => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => TRUE,
            self::LOCK_CW   => TRUE,
            self::LOCK_PR   => TRUE,
            self::LOCK_PW   => TRUE,
            self::LOCK_EX   => FALSE,
        ],
        self::LOCK_CW => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => TRUE,
            self::LOCK_CW   => TRUE,
            self::LOCK_PR   => FALSE,
            self::LOCK_PW   => FALSE,
            self::LOCK_EX   => FALSE,
        ],
        self::LOCK_PR => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => TRUE,
            self::LOCK_CW   => FALSE,
            self::LOCK_PR   => TRUE,
            self::LOCK_PW   => FALSE,
            self::LOCK_EX   => FALSE,
        ],
        self::LOCK_PW => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => TRUE,
            self::LOCK_CW   => FALSE,
            self::LOCK_PR   => FALSE,
            self::LOCK_PW   => FALSE,
            self::LOCK_EX   => FALSE,
        ],
        self::LOCK_EX => [
            self::LOCK_NL   => TRUE,
            self::LOCK_CR   => FALSE,
            self::LOCK_CW   => FALSE,
            self::LOCK_PR   => FALSE,
            self::LOCK_PW   => FALSE,
            self::LOCK_EX   => FALSE,
        ],
    ];

    //aliases
    public const READ_LOCK = self::LOCK_PR;
    public const WRITE_LOCK = self::LOCK_PW;

    /**
     * @param int $lock_level
     * @param int $lock_hold_microtime
     */
    public function acquire(int $lock_level, int $lock_hold_microtime) : void ;

    /**
     * Returns TRUE if the lock is acquired.
     * This doesnt necessarily mean that the lock is still valid - it may have expired (@see $lock_hold_microtime)
     * To check is the lock is still valid please see @see self::is_valid()
     * @return bool
     */
    public function is_acquired() : bool ;

    /**
     * Returns TRUE if the lock is acquired and it is still not expired.
     * @return bool
     */
    public function is_valid() : bool ;

    /**
     * @return string
     */
    public function get_resource() : string ;

    /**
     * @return int
     */
    public function get_lock_level() : int ;

    /**
     *
     */
    public function release() : void ;
}