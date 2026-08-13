<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('finance.manage');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') json_response(['success'=>false,'message'=>'Method not allowed.'],405);
require_csrf();
$input=json_decode((string)file_get_contents('php://input'),true);
$salesId=(int)($input['sales_id']??0);$date=(string)($input['settlement_date']??date('Y-m-d'));$submitted=(float)($input['submitted_cash']??0);$notes=trim((string)($input['notes']??''));
if($salesId<1||$submitted<0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))json_response(['success'=>false,'message'=>'Data settlement tidak valid.'],422);
$pdo=db();
$owner=$pdo->prepare('SELECT 1 FROM sales WHERE id=? AND user_id=?');$owner->execute([$salesId,$user['id']]);
if($user['role_code']==='SALES'&&!$owner->fetchColumn())json_response(['success'=>false,'message'=>'Salesman tidak valid.'],403);
$q=$pdo->prepare("SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.sales_id=? AND p.payment_date=? AND p.payment_method='CASH' AND p.status='POSTED'");$q->execute([$salesId,$date]);$expected=(float)$q->fetchColumn();
$diff=round($submitted-$expected,2);$num='SET-'.date('YmdHis').'-'.strtoupper(bin2hex(random_bytes(3)));
$ins=$pdo->prepare("INSERT INTO settlement_documents(settlement_number,sales_id,settlement_date,expected_cash,submitted_cash,difference,status,notes,created_by) VALUES(?,?,?,?,?,?, 'SUBMITTED',?,?)");$ins->execute([$num,$salesId,$date,$expected,$submitted,$diff,$notes?:null,$user['id']]);
json_response(['success'=>true,'settlement_number'=>$num,'expected_cash'=>$expected,'submitted_cash'=>$submitted,'difference'=>$diff,'status'=>'SUBMITTED'],201);
