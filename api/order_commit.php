<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('orders.approve');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);$orderId=(int)($input['order_id']??0);
if($orderId<1)json_response(['success'=>false,'message'=>'order_id wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,sales_id,status FROM orders WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$orderId]);$order=$q->fetch();
 if(!$order||$order['status']!=='APPROVED')throw new RuntimeException('Order harus APPROVED sebelum stock commit.',409);
 $reservations=$pdo->prepare("SELECT id,stock_location_id,product_id,qty FROM order_stock_reservations WHERE order_id=? AND status='RESERVED' FOR UPDATE");$reservations->execute([$orderId]);$rows=$reservations->fetchAll();
 if(!$rows)throw new RuntimeException('Order belum memiliki reservation aktif.',409);
 foreach($rows as $row){
   $balance=$pdo->prepare('SELECT qty FROM stock_balances WHERE stock_location_id=? AND product_id=? FOR UPDATE');$balance->execute([$row['stock_location_id'],$row['product_id']]);$available=(float)$balance->fetchColumn();
   if($available<(float)$row['qty'])throw new RuntimeException('Stok berubah dan tidak mencukupi saat commit.',409);
   $pdo->prepare('UPDATE stock_balances SET qty=qty-? WHERE stock_location_id=? AND product_id=?')->execute([$row['qty'],$row['stock_location_id'],$row['product_id']]);
   $movementNumber='MOV-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
   $movement=$pdo->prepare("INSERT INTO stock_movements(movement_number,product_id,from_location_id,to_location_id,movement_type,reference_type,reference_id,qty,notes,created_by) VALUES(?,?,?,NULL,'SALE','ORDER',?,?,?,?)");
   $movement->execute([$movementNumber,$row['product_id'],$row['stock_location_id'],$orderId,$row['qty'],'Stock deducted for approved order.',$user['id']]);
   $pdo->prepare("UPDATE order_stock_reservations SET status='COMMITTED' WHERE id=?")->execute([$row['id']]);
 }
 $pdo->prepare('INSERT INTO order_status_history(order_id,from_status,to_status,changed_by,notes) VALUES(?,?,?,?,?)')->execute([$orderId,'APPROVED','APPROVED',$user['id'],'Stock committed; order remains APPROVED.']);
 $pdo->commit();json_response(['success'=>true,'message'=>'Stock reservation berhasil di-commit.','order_id'=>$orderId]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
