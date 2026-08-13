<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('finance.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);
$invoiceId=(int)($input['invoice_id']??0); $amount=(float)($input['amount']??0); $method=strtoupper(trim((string)($input['payment_method']??'')));
if($invoiceId<1||$amount<=0||!in_array($method,['CASH','TRANSFER','GIRO','OTHER'],true)) json_response(['success'=>false,'message'=>'Invoice, amount, dan metode pembayaran tidak valid.'],422);
$pdo=db();$pdo->beginTransaction();
try{
 $q=$pdo->prepare('SELECT id,outlet_id,sales_id,grand_total,paid_total,status FROM invoices WHERE id=? LIMIT 1 FOR UPDATE');$q->execute([$invoiceId]);$inv=$q->fetch();
 if(!$inv||$inv['status']==='VOID')throw new RuntimeException('Invoice tidak dapat menerima pembayaran.',409);
 $remaining=round((float)$inv['grand_total']-(float)$inv['paid_total'],2);if($amount>$remaining)throw new RuntimeException('Pembayaran melebihi saldo invoice.',409);
 $owner=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');$owner->execute([$inv['sales_id'],$user['id']]);if($user['role_code']==='SALES'&&!$owner->fetchColumn())throw new RuntimeException('Invoice bukan milik salesman ini.',403);
 $num='PAY-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
 $p=$pdo->prepare("INSERT INTO payments(payment_number,invoice_id,outlet_id,sales_id,amount,payment_method,payment_date,status,created_by) VALUES(?,?,?,?,?,?,CURDATE(),'POSTED',?)");$p->execute([$num,$invoiceId,$inv['outlet_id'],$inv['sales_id'],$amount,$method,$user['id']]);
 $newPaid=round((float)$inv['paid_total']+$amount,2);$status=$newPaid>=(float)$inv['grand_total']?'PAID':'PARTIAL';
 $pdo->prepare('UPDATE invoices SET paid_total=?,status=? WHERE id=?')->execute([$newPaid,$status,$invoiceId]);
 $pdo->commit();json_response(['success'=>true,'payment_number'=>$num,'paid_total'=>$newPaid,'invoice_status'=>$status],201);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$s=$e->getCode()>=400&&$e->getCode()<600?$e->getCode():500;json_response(['success'=>false,'message'=>$e->getMessage()],$s);}
