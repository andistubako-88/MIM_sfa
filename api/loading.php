<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user=require_permission('inventory.manage');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);$from=(int)($input['warehouse_location_id']??0);$to=(int)($input['sales_location_id']??0);$items=$input['items']??[];$notes=trim((string)($input['notes']??''));
if($from<1||$to<1||$from===$to||!is_array($items)||!$items)json_response(['success'=>false,'message'=>'Lokasi dan item loading wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $loc=$pdo->prepare("SELECT id,location_type,sales_id FROM stock_locations WHERE id IN (?,?) AND is_active=1 FOR UPDATE");$loc->execute([$from,$to]);$locations=$loc->fetchAll();if(count($locations)!==2)throw new RuntimeException('Stock location tidak valid.',422);
 $types=[];foreach($locations as $l){$types[(int)$l['id']]=$l['location_type'];}
 if($types[$from]!=='WAREHOUSE'||$types[$to]!=='SALES')throw new RuntimeException('Loading harus dari WAREHOUSE ke SALES.',422);
 $num='LOAD-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
 $ins=$pdo->prepare("INSERT INTO loading_documents(loading_number,warehouse_location_id,sales_location_id,status,notes,created_by) VALUES(?,?,?,?,?,?)");$ins->execute([$num,$from,$to,'POSTED',$notes?:null,$user['id']]);$loadingId=(int)$pdo->lastInsertId();
 $li=$pdo->prepare('INSERT INTO loading_items(loading_id,product_id,qty) VALUES(?,?,?)');
 foreach($items as $item){$pid=(int)($item['product_id']??0);$qty=(float)($item['qty']??0);if($pid<1||$qty<=0)throw new RuntimeException('Item loading tidak valid.',422);
   $lock=$pdo->prepare('SELECT qty FROM stock_balances WHERE stock_location_id=? AND product_id=? FOR UPDATE');$lock->execute([$from,$pid]);$available=$lock->fetchColumn();if($available===false||(float)$available<$qty)throw new RuntimeException('Stok gudang tidak mencukupi.',409);
   $pdo->prepare('UPDATE stock_balances SET qty=qty-? WHERE stock_location_id=? AND product_id=?')->execute([$qty,$from,$pid]);
   $pdo->prepare('INSERT INTO stock_balances(stock_location_id,product_id,qty) VALUES(?,?,?) ON DUPLICATE KEY UPDATE qty=qty+VALUES(qty)')->execute([$to,$pid,$qty]);
   $li->execute([$loadingId,$pid,$qty]);
   $mv='MOV-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
   $pdo->prepare("INSERT INTO stock_movements(movement_number,product_id,from_location_id,to_location_id,movement_type,reference_type,reference_id,qty,notes,created_by) VALUES(?,?,?,?,'LOADING','LOADING',?,?,?,?)")->execute([$mv,$pid,$from,$to,$loadingId,$qty,'Warehouse loading to Sales stock.',$user['id']]);
 }
 $pdo->commit();json_response(['success'=>true,'loading_id'=>$loadingId,'loading_number'=>$num],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
