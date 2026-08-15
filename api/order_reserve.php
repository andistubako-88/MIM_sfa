<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user=require_permission('orders.create');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);$orderId=(int)($input['order_id']??0);$stockLocationId=(int)($input['stock_location_id']??0);
if($orderId<1||$stockLocationId<1)json_response(['success'=>false,'message'=>'order_id dan stock_location_id wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,sales_id,status FROM orders WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$orderId]);$order=$q->fetch();
 if(!$order||$order['status']!=='APPROVED')throw new RuntimeException('Reservation hanya dapat dibuat untuk order APPROVED.',409);
 $owner=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=? LIMIT 1');$owner->execute([$order['sales_id'],$user['id']]);
 if($user['role_code']==='SALES'&&!$owner->fetchColumn())throw new RuntimeException('Sales hanya dapat reserve order miliknya.',403);
 $location=$pdo->prepare("SELECT id,sales_id FROM stock_locations WHERE id=? AND location_type='SALES' AND is_active=1 LIMIT 1");$location->execute([$stockLocationId]);$loc=$location->fetch();
 if(!$loc)throw new RuntimeException('Stock location Sales tidak ditemukan.',422);
 if($user['role_code']==='SALES'&&(int)$loc['sales_id']!==(int)$order['sales_id'])throw new RuntimeException('Stock location bukan milik salesman pada order.',403);
 $items=$pdo->prepare('SELECT product_id,qty FROM order_items WHERE order_id=? ORDER BY id');$items->execute([$orderId]);$rows=$items->fetchAll();if(!$rows)throw new RuntimeException('Order tidak memiliki item.',422);
 foreach($rows as $row){
  $lock=$pdo->prepare('SELECT qty FROM stock_balances WHERE stock_location_id=? AND product_id=? FOR UPDATE');$lock->execute([$stockLocationId,$row['product_id']]);$available=(float)$lock->fetchColumn();
  $existing=$pdo->prepare("SELECT COALESCE(SUM(qty),0) FROM order_stock_reservations WHERE stock_location_id=? AND product_id=? AND status='RESERVED' AND order_id<>?");$existing->execute([$stockLocationId,$row['product_id'],$orderId]);$reserved=(float)$existing->fetchColumn();
  if(($available-$reserved)<(float)$row['qty'])throw new RuntimeException('Stok tersedia setelah reservation order lain tidak mencukupi.',409);
  $upsert=$pdo->prepare("INSERT INTO order_stock_reservations(order_id,stock_location_id,product_id,qty,status) VALUES(?,?,?,?, 'RESERVED') ON DUPLICATE KEY UPDATE qty=VALUES(qty),status='RESERVED'");$upsert->execute([$orderId,$stockLocationId,$row['product_id'],$row['qty']]);
 }
 $pdo->commit();json_response(['success'=>true,'message'=>'Stok berhasil di-reserve untuk order.','order_id'=>$orderId],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
