<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user = require_permission('finance.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);
$deliveryId=(int)($input['delivery_id']??0); $dueDate=(string)($input['due_date']??'');
if($deliveryId<1)json_response(['success'=>false,'message'=>'delivery_id wajib diisi.'],422);
if($dueDate!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$dueDate))json_response(['success'=>false,'message'=>'due_date tidak valid.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,order_id,sales_id,outlet_id,status FROM delivery_documents WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$deliveryId]);$d=$q->fetch();
 if(!$d||$d['status']!=='DELIVERED')throw new RuntimeException('Delivery belum DELIVERED.',409);
 $own=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');$own->execute([$d['sales_id'],$user['id']]);if($user['role_code']==='SALES'&&!$own->fetchColumn())throw new RuntimeException('Delivery bukan milik salesman ini.',403);
 $exists=$pdo->prepare('SELECT id FROM invoices WHERE delivery_id=? AND status<>\'VOID\' LIMIT 1');$exists->execute([$deliveryId]);if($exists->fetch())throw new RuntimeException('Invoice untuk delivery ini sudah ada.',409);
 $items=$pdo->prepare('SELECT product_id,qty FROM delivery_items WHERE delivery_id=?');$items->execute([$deliveryId]);$rows=$items->fetchAll();if(!$rows)throw new RuntimeException('Delivery tidak memiliki item.',422);
 $tot=$pdo->prepare('SELECT subtotal,discount_total,grand_total FROM orders WHERE id=? LIMIT 1');$tot->execute([$d['order_id']]);$order=$tot->fetch();if(!$order)throw new RuntimeException('Order sumber tidak ditemukan.',422);
 $num='INV-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
 $ins=$pdo->prepare("INSERT INTO invoices(invoice_number,delivery_id,outlet_id,sales_id,status,invoice_date,due_date,subtotal,discount_total,grand_total,paid_total,created_by) VALUES(?,?,?,?, 'OPEN',CURDATE(),?,?,?,?,0,?)");
 $ins->execute([$num,$deliveryId,$d['outlet_id'],$d['sales_id'],$dueDate?:null,$order['subtotal'],$order['discount_total'],$order['grand_total'],$user['id']]);
 $pdo->commit();json_response(['success'=>true,'invoice_id'=>(int)$pdo->lastInsertId(),'invoice_number'=>$num,'grand_total'=>(float)$order['grand_total']],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
