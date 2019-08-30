<?php


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

    //aliases
    public const READ_LOCK = self::LOCK_PR;
    public const WRITE_LOCK = self::LOCK_PW;

    /**
     * @param int $lock_level
     * @param int $lock_hold_microtime
     */
    public function acquire(int $lock_level, int $lock_hold_microtime) : void ;

    /**
     * @return bool
     */
    public function is_acquired() : bool ;

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