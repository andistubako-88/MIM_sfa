<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user = require_permission('reports.view');

$from=(string)($_GET['from']??date('Y-m-01')); $to=(string)($_GET['to']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to) json_response(['success'=>false,'message'=>'Periode laporan tidak valid.'],422);
$dimension=strtolower((string)($_GET['dimension']??'salesman'));
if(!in_array($dimension,['salesman','area','channel','sku'],true)) json_response(['success'=>false,'message'=>'Dimension tidak didukung.'],422);

$pdo=db();
if($dimension==='salesman'){
 $sql="SELECT s.id,s.employee_code,s.name,COUNT(DISTINCT v.id) visits,COUNT(DISTINCT CASE WHEN v.status='COMPLETED' THEN v.id END) completed_visits,COUNT(DISTINCT o.id) orders,COALESCE(SUM(CASE WHEN o.status<>'CANCELLED' THEN o.grand_total ELSE 0 END),0) order_value,COALESCE(SUM(i.grand_total),0) invoice_value,COALESCE(SUM(i.paid_total),0) collected_value FROM sales s LEFT JOIN visits v ON v.sales_id=s.id AND v.visit_date BETWEEN ? AND ? LEFT JOIN orders o ON o.sales_id=s.id AND DATE(o.submitted_at) BETWEEN ? AND ? LEFT JOIN invoices i ON i.sales_id=s.id AND i.invoice_date BETWEEN ? AND ? AND i.status<>'VOID' WHERE s.is_active=1 GROUP BY s.id ORDER BY order_value DESC,s.name";
 $q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to,$from,$to]);$rows=$q->fetchAll();
}elseif($dimension==='area'){
 $sql="SELECT a.id,a.code,a.name,COUNT(DISTINCT v.id) visits,COUNT(DISTINCT o.id) orders,COALESCE(SUM(CASE WHEN o.status<>'CANCELLED' THEN o.grand_total ELSE 0 END),0) order_value FROM areas a LEFT JOIN sales_area sa ON sa.area_id=a.id LEFT JOIN visits v ON v.sales_id=sa.sales_id AND v.visit_date BETWEEN ? AND ? LEFT JOIN orders o ON o.sales_id=sa.sales_id AND DATE(o.submitted_at) BETWEEN ? AND ? WHERE a.is_active=1 GROUP BY a.id ORDER BY order_value DESC,a.name";
 $q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to]);$rows=$q->fetchAll();
}elseif($dimension==='channel'){
 $sql="SELECT COALESCE(o.channel,'UNKNOWN') channel,COUNT(DISTINCT v.id) visits,COUNT(DISTINCT ord.id) orders,COALESCE(SUM(CASE WHEN ord.status<>'CANCELLED' THEN ord.grand_total ELSE 0 END),0) order_value FROM outlets o LEFT JOIN visits v ON v.outlet_id=o.id AND v.visit_date BETWEEN ? AND ? LEFT JOIN orders ord ON ord.outlet_id=o.id AND DATE(ord.submitted_at) BETWEEN ? AND ? GROUP BY o.channel ORDER BY order_value DESC";
 $q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to]);$rows=$q->fetchAll();
}else{
 $sql="SELECT p.id,p.sku,p.name,p.category,COALESCE(SUM(oi.qty),0) qty_ordered,COALESCE(SUM(oi.line_total),0) sales_value,COUNT(DISTINCT o.id) order_count FROM products p LEFT JOIN order_items oi ON oi.product_id=p.id LEFT JOIN orders o ON o.id=oi.order_id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED' WHERE p.is_active=1 GROUP BY p.id ORDER BY sales_value DESC,p.name";
 $q=$pdo->prepare($sql);$q->execute([$from,$to]);$rows=$q->fetchAll();
}
json_response(['success'=>true,'period'=>['from'=>$from,'to'=>$to],'dimension'=>$dimension,'rows'=>$rows]);
