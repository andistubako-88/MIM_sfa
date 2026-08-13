<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user=require_permission('finance.approve');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);$id=(int)($input['settlement_id']??0);$approve=(bool)($input['approve']??false);$notes=trim((string)($input['notes']??''));
if($id<1)json_response(['success'=>false,'message'=>'settlement_id wajib diisi.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,status FROM settlement_documents WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$id]);$s=$q->fetch();if(!$s||$s['status']!=='SUBMITTED')throw new RuntimeException('Settlement harus SUBMITTED.',409);
 $status=$approve?'APPROVED':'REJECTED';$pdo->prepare('UPDATE settlement_documents SET status=?,approved_by=?,approved_at=NOW(),notes=CASE WHEN ?<>\'\' THEN ? ELSE notes END WHERE id=?')->execute([$status,$user['id'],$notes,$notes,$id]);
 $pdo->commit();json_response(['success'=>true,'settlement_id'=>$id,'status'=>$status],200);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$code=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$code);}
