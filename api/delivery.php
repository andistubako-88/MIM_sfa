<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user = require_permission('orders.create');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);
$orderId=(int)($input['order_id']??0);$recipient=trim((string)($input['recipient_name']??''));
if($orderId<1)json_response(['success'=>false,'message'=>'order_id wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
$q=$pdo->prepare('SELECT id,sales_id,outlet_id,status FROM orders WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$orderId]);$o=$q->fetch();
if(!$o||$o['status']!=='APPROVED')throw new RuntimeException('Order belum APPROVED.',409);
$own=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');$own->execute([$o['sales_id'],$user['id']]);
if($user['role_code']==='SALES'&&!$own->fetchColumn())throw new RuntimeException('Order bukan milik salesman ini.',403);
$committed=$pdo->prepare("SELECT COUNT(*) FROM order_stock_reservations WHERE order_id=? AND status='COMMITTED'");$committed->execute([$orderId]);
$itemCount=$pdo->prepare('SELECT COUNT(*) FROM order_items WHERE order_id=?');$itemCount->execute([$orderId]);
if((int)$itemCount->fetchColumn()<1||(int)$committed->fetchColumn()<1)throw new RuntimeException('Stock order belum di-commit.',409);
$uncommitted=$pdo->prepare("SELECT COUNT(*) FROM order_stock_reservations WHERE order_id=? AND status<>'COMMITTED'");$uncommitted->execute([$orderId]);
if((int)$uncommitted->fetchColumn()>0)throw new RuntimeException('Masih ada reservation yang belum committed.',409);
$exists=$pdo->prepare("SELECT id FROM delivery_documents WHERE order_id=? AND status<>'CANCELLED' LIMIT 1");$exists->execute([$orderId]);
if($exists->fetch())throw new RuntimeException('Delivery untuk order ini sudah dibuat.',409);
$num='DLV-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
$ins=$pdo->prepare("INSERT INTO delivery_documents(delivery_number,order_id,sales_id,outlet_id,status,delivered_at,recipient_name,created_by) VALUES(?,?,?,?, 'DELIVERED',NOW(),?,?)");
$ins->execute([$num,$o['id'],$o['sales_id'],$o['outlet_id'],$recipient?:null,$user['id']]);
$deliveryId=(int)$pdo->lastInsertId();
$items=$pdo->prepare('SELECT product_id,qty FROM order_items WHERE order_id=?');$items->execute([$orderId]);
$di=$pdo->prepare('INSERT INTO delivery_items(delivery_id,product_id,qty) VALUES(?,?,?)');
foreach($items as $item)$di->execute([$deliveryId,$item['product_id'],$item['qty']]);
$pdo->commit();json_response(['success'=>true,'delivery_id'=>$deliveryId,'delivery_number'=>$num],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
