<?php
declare(strict_types=1);
require __DIR__.'/auth.php';
$u=require_permission('dashboard.view');
$s=db()->prepare('SELECT p.code,p.name,p.module FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.module,p.code');
$s->execute([(int)$u['role_id']]);
json_response(['success'=>true,'role'=>$u['role_code'],'permissions'=>$s->fetchAll()]);
