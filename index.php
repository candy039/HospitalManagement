<?php
require __DIR__.'/includes/auth.php';
$u=user();redirect($u?$u['role'].'/dashboard.php':'login.php');
