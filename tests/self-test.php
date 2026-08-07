<?php
declare(strict_types=1);

require dirname(__DIR__) . '/config.php';

$user = $db->query("SELECT * FROM users WHERE username = 'andi'")->fetch();
if (!$user || !password_verify('teknisi123', $user['password_hash'])) {
    fwrite(STDERR, "Login hash test failed.\n");
    exit(1);
}

if (intdiv(JOB_VALUE, 1) !== 125000 || intdiv(JOB_VALUE, 2) !== 62500) {
    fwrite(STDERR, "Income split test failed.\n");
    exit(2);
}

echo "Login hash valid; 1 teknisi=Rp125.000; 2 teknisi=Rp62.500\n";

