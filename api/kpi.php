<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('reports.view');
if ($user['role_code'] !== 'OWNER') {
    json_response(['success'=>false,'message'=>'Report Center hanya dapat diakses Owner.'],403);
}

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$validDate = static function(string $value): bool {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $d !== false && $d->format('Y-m-d') === $value;
};
if (!$validDate((string)$from) || !$validDate((string)$to) || $from > $to) {
    json_response(['success'=>false,'message'=>'Periode tidak valid.'],422);
}

$pdo = db();
$params = [$from, $to];
$stats = [];

// Actual Call = every visit recorded in the selected period.
$q=$pdo->prepare("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ?");
$q->execute($params);
$stats['actual_calls']=(int)$q->fetchColumn();
$stats['total_visits']=$stats['actual_calls'];

$q=$pdo->prepare("SELECT COUNT(*) FROM visits WHERE visit_date BETWEEN ? AND ? AND status='COMPLETED'");
$q->execute($params);
$stats['completed_visits']=(int)$q->fetchColumn();

// Effective Call = visits that generated at least one non-cancelled order.
$q=$pdo->prepare("SELECT COUNT(DISTINCT v.id) FROM visits v JOIN orders o ON o.visit_id=v.id WHERE v.visit_date BETWEEN ? AND ? AND o.status <> 'CANCELLED'");
$q->execute($params);
$stats['effective_calls']=(int)$q->fetchColumn();

$q=$pdo->prepare("SELECT COUNT(*) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ?");
$q->execute($params);
$stats['total_orders']=(int)$q->fetchColumn();

$q=$pdo->prepare("SELECT COALESCE(SUM(grand_total),0) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ? AND o.status <> 'CANCELLED'");
$q->execute($params);
$stats['order_value']=(float)$q->fetchColumn();

$q=$pdo->prepare("SELECT COUNT(DISTINCT o.outlet_id) FROM orders o JOIN visits v ON v.id=o.visit_id WHERE v.visit_date BETWEEN ? AND ? AND o.status <> 'CANCELLED'");
$q->execute($params);
$stats['productive_outlets']=(int)$q->fetchColumn();

$q=$pdo->prepare("SELECT COALESCE(SUM(i.grand_total),0), COALESCE(SUM(i.paid_total),0) FROM invoices i WHERE i.invoice_date BETWEEN ? AND ? AND i.status <> 'VOID'");
$q->execute($params);
[$invoiceValue,$paidValue]=$q->fetch(PDO::FETCH_NUM);
$stats['invoice_value']=(float)$invoiceValue;
$stats['collected_value']=(float)$paidValue;
$stats['outstanding']=round((float)$invoiceValue-(float)$paidValue,2);

$q=$pdo->prepare("SELECT COALESCE(SUM(ri.qty),0) FROM return_items ri JOIN return_documents rd ON rd.id=ri.return_id WHERE rd.created_at >= CONCAT(?, ' 00:00:00') AND rd.created_at <= CONCAT(?, ' 23:59:59') AND rd.status='POSTED'");
$q->execute($params);
$stats['return_qty']=(float)$q->fetchColumn();

$stats['strike_rate']=$stats['actual_calls']>0
    ? round(($stats['effective_calls']/$stats['actual_calls'])*100,2)
    : 0;
$stats['visit_completion_rate']=$stats['actual_calls']>0
    ? round(($stats['completed_visits']/$stats['actual_calls'])*100,2)
    : 0;

// Coverage here is the current master-data distribution coverage, not historical sales coverage.
$q=$pdo->query("SELECT COUNT(DISTINCT so.outlet_id) FROM sales_outlet so JOIN outlets o ON o.id=so.outlet_id WHERE so.is_active=1 AND o.is_active=1");
$assignedOutlets=(int)$q->fetchColumn();
$q=$pdo->query("SELECT COUNT(DISTINCT op.outlet_id) FROM outlet_products op JOIN outlets o ON o.id=op.outlet_id WHERE op.is_active=1 AND o.is_active=1");
$productCoveredOutlets=(int)$q->fetchColumn();
$stats['assigned_outlets']=$assignedOutlets;
$stats['product_covered_outlets']=$productCoveredOutlets;
$stats['product_coverage_rate']=$assignedOutlets>0
    ? round(($productCoveredOutlets/$assignedOutlets)*100,2)
    : 0;

json_response(['success'=>true,'period'=>['from'=>$from,'to'=>$to],'kpi'=>$stats]);
