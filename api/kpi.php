<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('reports.view');

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    json_response(['success'=>false,'message'=>'Periode tidak valid.'],422);
}

$pdo = db();
$params = [$from, $to];
$stats = [];

$q=$pdo->prepare("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ?");$q->execute($params);$stats['total_visits']=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ? AND status='COMPLETED'");$q->execute($params);$stats['completed_visits']=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COUNT(*) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ?");$q->execute($params);$stats['total_orders']=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ? AND o.status <> 'CANCELLED'");$q->execute($params);$stats['order_value']=(float)$q->fetchColumn();
$q=$pdo->prepare("SELECT COUNT(DISTINCT o.outlet_id) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ? AND o.status <> 'CANCELLED'");$q->execute($params);$stats['productive_outlets']=(int)$q->fetchColumn();
$q=$pdo->prepare("SELECT COALESCE(SUM(i.grand_total),0), COALESCE(SUM(i.paid_total),0) FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? AND i.status <> 'VOID'");$q->execute($params);[$invoiceValue,$paidValue]=$q->fetch(PDO::FETCH_NUM);$stats['invoice_value']=(float)$invoiceValue;$stats['collected_value']=(float)$paidValue;$stats['outstanding']=round((float)$invoiceValue-(float)$paidValue,2);
$q=$pdo->prepare("SELECT COALESCE(SUM(ri.qty),0) FROM return_items ri JOIN return_documents rd ON rd.id=ri.return_id WHERE rd.created_at >= CONCAT(?, ' 00:00:00') AND rd.created_at <= CONCAT(?, ' 23:59:59') AND rd.status='POSTED'");$q->execute($params);$stats['return_qty']=(float)$q->fetchColumn();
$stats['strike_rate']=$stats['total_visits']>0?round(($stats['productive_outlets']/$stats['total_visits'])*100,2):0;

if ($user['role_code'] !== 'OWNER') {
    unset($stats['outstanding']);
}
json_response(['success'=>true,'period'=>['from'=>$from,'to'=>$to],'kpi'=>$stats]);
