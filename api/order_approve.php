<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('orders.approve');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);
$orderId=(int)($input['order_id']??0);
if($orderId<1)json_response(['success'=>false,'message'=>'order_id wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,status FROM orders WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$orderId]);$order=$q->fetch();
 if(!$order||$order['status']!=='SUBMITTED')throw new RuntimeException('Order hanya dapat di-approve dari status SUBMITTED.',409);
 $pdo->prepare("UPDATE orders SET status='APPROVED',approved_at=NOW(),approved_by=? WHERE id=?")->execute([$user['id'],$orderId]);
 $pdo->prepare('INSERT INTO order_status_history(order_id,from_status,to_status,changed_by,notes) VALUES(?,?,?,?,?)')->execute([$orderId,'SUBMITTED','APPROVED',$user['id'],'Order disetujui oleh user berwenang.']);
 $pdo->commit();json_response(['success'=>true,'order_id'=>$orderId,'status'=>'APPROVED']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
