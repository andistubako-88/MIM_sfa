<?php
declare(strict_types=1);
require __DIR__.'/auth.php';
$user=require_permission('masters.manage');
$method=strtoupper($_SERVER['REQUEST_METHOD']??'GET'); $resource=strtolower(trim((string)($_GET['resource']??'')));
$tables=['areas'=>'areas','sales'=>'sales','outlets'=>'outlets','products'=>'products'];
if(!isset($tables[$resource])) json_response(['success'=>false,'message'=>'Resource master tidak dikenal.'],404);
$table=$tables[$resource];
function master_input(): array { $j=json_decode(file_get_contents('php://input')?:'',true); return is_array($j)?$j:$_POST; }
function master_audit(string $action,string $entity,?int $id,?array $old,?array $new):void { global $user; $s=db()->prepare('INSERT INTO audit_logs (user_id,action,entity_type,entity_id,old_data,new_data,ip_address,user_agent) VALUES (?,?,?,?,?,?,?,?)'); $s->execute([(int)$user['id'],$action,$entity,$id,$old?json_encode($old):null,$new?json_encode($new):null,$_SERVER['REMOTE_ADDR']??null,$_SERVER['HTTP_USER_AGENT']??null]); }
if($method==='GET'){ $s=db()->query("SELECT * FROM {$table} WHERE is_active=1 ORDER BY id DESC"); json_response(['success'=>true,'resource'=>$resource,'data'=>$s->fetchAll()]); }
require_csrf(); $data=master_input();
if($method==='POST'){
 if($resource==='areas'){ $code=strtoupper(trim((string)($data['code']??'')));$name=trim((string)($data['name']??''));if($code===''||$name==='')json_response(['success'=>false,'message'=>'Kode dan nama area wajib.'],422);$s=db()->prepare('INSERT INTO areas(code,name) VALUES(?,?)');$s->execute([$code,$name]);$id=(int)db()->lastInsertId(); }
 elseif($resource==='products'){ $sku=strtoupper(trim((string)($data['sku']??'')));$name=trim((string)($data['name']??''));if($sku===''||$name==='')json_response(['success'=>false,'message'=>'SKU dan nama produk wajib.'],422);$s=db()->prepare('INSERT INTO products(sku,name,category,unit,sell_price,cost_price) VALUES(?,?,?,?,?,?)');$s->execute([$sku,$name,$data['category']??null,$data['unit']??'PCS',(float)($data['sell_price']??0),(float)($data['cost_price']??0)]);$id=(int)db()->lastInsertId(); }
 elseif($resource==='sales'){ $employee=trim((string)($data['employee_code']??''));$name=trim((string)($data['name']??''));$userId=(int)($data['user_id']??0);if($employee===''||$name===''||$userId<1)json_response(['success'=>false,'message'=>'employee_code, name, user_id wajib.'],422);$s=db()->prepare('INSERT INTO sales(user_id,employee_code,name,phone,channel) VALUES(?,?,?,?,?)');$s->execute([$userId,$employee,$name,$data['phone']??null,$data['channel']??null]);$id=(int)db()->lastInsertId(); }
 else { $code=strtoupper(trim((string)($data['code']??'')));$name=trim((string)($data['name']??''));$lat=(float)($data['latitude']??999);$lng=(float)($data['longitude']??999);if($code===''||$name===''||$lat<-90||$lat>90||$lng<-180||$lng>180)json_response(['success'=>false,'message'=>'Kode, nama, latitude dan longitude valid wajib.'],422);$s=db()->prepare('INSERT INTO outlets(code,name,address,province,city,district,village,latitude,longitude,channel,visit_route) VALUES(?,?,?,?,?,?,?,?,?,?,?)');$s->execute([$code,$name,$data['address']??null,$data['province']??null,$data['city']??null,$data['district']??null,$data['village']??null,$lat,$lng,$data['channel']??null,$data['visit_route']??'DAILY']);$id=(int)db()->lastInsertId(); }
 master_audit('CREATE',$resource,$id,null,$data);json_response(['success'=>true,'id'=>$id],201);
}
if($method==='DELETE'){ $id=(int)($_GET['id']??0);if($id<1)json_response(['success'=>false,'message'=>'ID wajib.'],422);$s=db()->prepare("SELECT * FROM {$table} WHERE id=? AND is_active=1");$s->execute([$id]);$old=$s->fetch();if(!$old)json_response(['success'=>false,'message'=>'Data tidak ditemukan.'],404);$s=db()->prepare("UPDATE {$table} SET is_active=0 WHERE id=?");$s->execute([$id]);master_audit('DEACTIVATE',$resource,$id,$old,['is_active'=>0]);json_response(['success'=>true,'id'=>$id]);}
json_response(['success'=>false,'message'=>'Method tidak didukung.'],405);
