--TEST--
Azonmedia\LockManager: basic
--FILE--
<?php
namespace Azonmedia\Lock;
require_once('./include/bootstrap.php');
$Backend = new Backends\NullBackend();
$LM = new LockManager($Backend);
$Lock = $LM->acquire_lock('res',Interfaces\LockInterface::LOCK_PR);
print $Lock->get_lock_level();
--EXPECT--
8