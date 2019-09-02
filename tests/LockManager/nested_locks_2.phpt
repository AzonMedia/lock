--TEST--
Azonmedia\LockManager: nested locks - the nested one is of higher priority
--FILE--
<?php
namespace Azonmedia\Lock;
require_once('./include/bootstrap.php');
$Backend = new Backends\NullBackend();
$LM = new LockManager($Backend);
$Lock = $LM->acquire_lock('res',Interfaces\LockInterface::LOCK_PW);//16
$Lock = $LM->acquire_lock('res',Interfaces\LockInterface::LOCK_PR);//8
print $Lock->get_lock_level();
--EXPECT--
16