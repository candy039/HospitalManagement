<?php
require_once __DIR__.'/../config/database.php';
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Dhaka');
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-store');
define('BASE', rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'], defined('ROLE') ? 2 : 1)), '/'));
function url(string $path): string { return BASE.'/'.$path; }
function e($value): string { return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'); }
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function rows(string $sql,array $params=[]): array {return query($sql,$params)->fetchAll();}
function one(string $sql,array $params=[]): ?array {return query($sql,$params)->fetch() ?: null;}
function redirect(string $path): never {header('Location: '.url($path));exit;}
function csrf(): string { $_SESSION['csrf'] ??= bin2hex(random_bytes(32));return '<input type="hidden" name="csrf" value="'.e($_SESSION['csrf']).'">'; }
function verify_csrf(): void {if (!isset($_POST['csrf'],$_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], (string)$_POST['csrf'])) { http_response_code(403);exit('Invalid form token. Reload the page and try again.');}}
function user(): ?array { return isset($_SESSION['uid']) ? one('SELECT id,name,email,role,status FROM users WHERE id=? AND status=?',[$_SESSION['uid'],'active']) : null; }
function require_role(string $role): array { $u=user();if (!$u) redirect('login.php'); if($u['role']!==$role){http_response_code(403);exit('Access denied.');} return $u; }
function post(string $key,int $max=255,bool $required=true): string { $v=trim((string)($_POST[$key]??''));if (($required && $v==='') || mb_length($v)>$max) throw new RuntimeException('Please provide a valid '.str_replace('_',' ',$key).' (maximum '.$max.' characters).');return $v; }
function mb_length(string $v): int {return preg_match_all('/./us',$v) ?: strlen($v);}
function positive_id(string $key): int { $v=filter_var($_POST[$key]??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if(!$v)throw new RuntimeException('Invalid '.str_replace('_',' ',$key).'.');return $v; }
function patient_scope(array $u): array {
    if($u['role']==='patient') return ['p.user_id=?',[$u['id']]];
    if($u['role']==='doctor') return ["EXISTS (SELECT 1 FROM appointments a JOIN doctors d ON d.id=a.doctor_id WHERE a.patient_id=p.id AND d.user_id=? AND a.status <> 'Cancelled')",[$u['id']]];
    return ['1=1',[]];
}
function accessible_patient(int $id,array $u): array {[$where,$params]=patient_scope($u);$p=one("SELECT p.*,u.name,u.email FROM patients p JOIN users u ON u.id=p.user_id WHERE p.id=? AND $where",array_merge([$id],$params));if(!$p)throw new RuntimeException('Patient unavailable or access denied.');return $p;}
function badge(string $value): string {return '<span class="badge '.e(strtolower($value)).'">'.e($value).'</span>';}
