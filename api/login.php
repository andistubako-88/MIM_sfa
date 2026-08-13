<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') { header('Location: ../public/login.php'); exit; }
$email = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') { http_response_code(422); exit('Email atau password tidak valid.'); }
$pdo = db();
$q = $pdo->prepare('SELECT u.id,u.email,u.password_hash,u.is_active,r.code AS role_code FROM users u JOIN roles r ON r.id=u.role_id WHERE u.email=? LIMIT 1');
$q->execute([$email]); $row=$q->fetch();
if (!$row || !(int)$row['is_active'] || !password_verify($password,(string)$row['password_hash'])) { http_response_code(401); exit('Email atau password salah.'); }
session_regenerate_id(true);
$_SESSION['user']=['id'=>(int)$row['id'],'email'=>$row['email'],'role_code'=>$row['role_code']];
header('Location: ../public/dashboard.php'); exit;
