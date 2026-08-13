<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user=require_permission('inventory.manage');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);$deliveryId=(int)($input['delivery_id']??0);$items=$input['items']??[];$reason=trim((string)($input['reason']??''));
if($deliveryId<1||!is_array($items)||!$items)json_response(['success'=>false,'message'=>'delivery_id dan item retur wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
$q=$pdo->prepare('SELECT id,sales_id,outlet_id,status FROM delivery_documents WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$deliveryId]);$d=$q->fetch();
if(!$d||$d['status']!=='DELIVERED')throw new RuntimeException('Delivery tidak valid untuk retur.',409);
$owner=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');$owner->execute([$d['sales_id'],$user['id']]);if($user['role_code']==='SALES'&&!$owner->fetchColumn())throw new RuntimeException('Delivery bukan milik salesman ini.',403);
$loc=$pdo->prepare("SELECT id FROM stock_locations WHERE sales_id=? AND location_type='SALES' AND is_active=1 LIMIT 1");$loc->execute([$d['sales_id']]);$salesLoc=$loc->fetchColumn();if(!$salesLoc)throw new RuntimeException('Stock location Sales tidak ditemukan.',422);
$num='RET-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
$ins=$pdo->prepare("INSERT INTO return_documents(return_number,delivery_id,sales_id,outlet_id,status,reason,created_by) VALUES(?,?,?,?, 'POSTED',?,?)");$ins->execute([$num,$d['id'],$d['sales_id'],$d['outlet_id'],$reason?:null,$user['id']]);$returnId=(int)$pdo->lastInsertId();
$ri=$pdo->prepare('INSERT INTO return_items(return_id,product_id,qty) VALUES(?,?,?)');
foreach($items as $item){$pid=(int)($item['product_id']??0);$qty=(float)($item['qty']??0);if($pid<1||$qty<=0)throw new RuntimeException('Item retur tidak valid.',422);
$del=$pdo->prepare('SELECT COALESCE(SUM(qty),0) FROM delivery_items WHERE delivery_id=? AND product_id=?');$del->execute([$deliveryId,$pid]);$delivered=(float)$del->fetchColumn();
$ret=$pdo->prepare("SELECT COALESCE(SUM(ri.qty),0) FROM return_items ri JOIN return_documents rd ON rd.id=ri.return_id WHERE rd.delivery_id=? AND ri.product_id=? AND rd.status='POSTED'");$ret->execute([$deliveryId,$pid]);$returned=(float)$ret->fetchColumn();
if($delivered<=0||$qty>($delivered-$returned))throw new RuntimeException('Qty retur melebihi sisa qty delivery.',409);
$ri->execute([$returnId,$pid,$qty]);
$pdo->prepare('INSERT INTO stock_balances(stock_location_id,product_id,qty) VALUES(?,?,?) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)')->execute([$salesLoc,$pid,$qty]);
$mv='MOV-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
$pdo->prepare("INSERT INTO stock_movements(movement_number,product_id,from_location_id,to_location_id,movement_type,reference_type,reference_id,qty,notes,created_by) VALUES(?,?,NULL,?,'RETURN','RETURN',?,?,?,?)")->execute([$mv,$pid,$salesLoc,$returnId,$qty,'Stock returned to Sales location.',$user['id']]);
}
$pdo->commit();json_response(['success'=>true,'return_id'=>$returnId,'return_number'=>$num],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
