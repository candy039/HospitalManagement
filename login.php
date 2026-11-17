<?php
require __DIR__.'/includes/auth.php';
if($u=user())redirect($u['role'].'/dashboard.php');
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') {
    verify_csrf();
    $email=trim((string)($_POST['email']??''));$password=(string)($_POST['password']??'');
    if(!filter_var($email,FILTER_VALIDATE_EMAIL) || !$password) $error='Enter a valid email and password.';
    else { $u=one('SELECT * FROM users WHERE email=?',[$email]);
        if($u && $u['status']==='active' && password_verify($password,$u['password'])) {session_regenerate_id(true);$_SESSION['uid']=$u['id'];unset($_SESSION['csrf']);redirect($u['role'].'/dashboard.php');}
        $error='Invalid credentials or inactive account.';
    }
}
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sign in · Carewell</title><link rel="stylesheet" href="<?=e(url('assets/css/style.css'))?>"></head><body class="login-body"><main class="login-shell"><section class="login-story"><a class="brand" href="<?=e(url('index.php'))?>"><span class="brand-icon">✚</span> Carewell<span class="brand-dot">.</span></a><span class="eyebrow">CONNECTED CARE, EVERY DAY</span><h1>A little more care.<br>A lot more possibility.</h1><p>One welcoming space for your patients, your team, and a healthier tomorrow.</p><div class="care-art"><div class="art-cross">✚</div><div class="art-note">♡ <strong>People first.</strong><small>Better care starts with connection</small></div><div class="pulse-line">⌁</div></div><div class="story-bottom"><span>✦ Thoughtful by design</span><span>Built around your care</span></div></section><section class="login-form"><div class="pill">YOUR HOSPITAL, CONNECTED</div><h2>Welcome back</h2><p class="muted">Sign in to your Carewell workspace.</p><?php if($error):?><div class="alert error"><?=e($error)?></div><?php endif;?><form method="post"><?=csrf()?><label>Email address<input type="email" name="email" autocomplete="username" placeholder="you@carewell.test" required maxlength="190"></label><label>Password<div class="password-field"><input id="password" type="password" name="password" autocomplete="current-password" required><button type="button" class="show-password" aria-label="Show password">Show</button></div></label><button class="button full" type="submit">Sign in to workspace <span>→</span></button></form><div class="login-info">New patient? Please visit reception to register your account.</div><div class="secure-note">♧ Session-protected access · All four care roles</div></section></main><script src="<?=e(url('assets/js/script.js'))?>"></script></body></html>
