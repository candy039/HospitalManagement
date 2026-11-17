<?php
require __DIR__.'/includes/auth.php';
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Use the sign out button.');}
verify_csrf();$_SESSION=[];session_destroy();redirect('login.php');
