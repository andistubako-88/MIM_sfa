<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$u = require_permission('reports.view');
if (strtoupper((string)$u['role_code']) !== 'OWNER') {
    json_response(['success'=>false,'message'=>'Report Center hanya dapat diakses oleh Owner.'],403);
}
$from=(string)($_GET['from']??date('Y-m-01'));$to=(string)($_GET['to']??date('Y-m-d'));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)||$from>$to)json_response(['success'=>false,'message'=>'Periode laporan tidak valid.'],422);
$dimension=strtolower((string)($_GET['dimension']??'salesman'));if(!in_array($dimension,['salesman','area','channel','sku'],true))json_response(['success'=>false,'message'=>'Dimension tidak didukung.'],422);
$pdo=db();
if($dimension==='salesman'){
$sql="SELECT s.id,s.employee_code,s.name,(SELECT COUNT(*) FROM visits v WHERE v.sales_id=s.id AND v.visit_date BETWEEN ? AND ?) visits,(SELECT COUNT(*) FROM visits v WHERE v.sales_id=s.id AND v.visit_date BETWEEN ? AND ? AND v.status='COMPLETED') completed_visits,(SELECT COUNT(*) FROM orders o WHERE o.sales_id=s.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') orders,(SELECT COALESCE(SUM(o.grand_total),0) FROM orders o WHERE o.sales_id=s.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') order_value,(SELECT COALESCE(SUM(i.grand_total),0) FROM invoices i WHERE i.sales_id=s.id AND i.invoice_date BETWEEN ? AND ? AND i.status<>'VOID') invoice_value,(SELECT COALESCE(SUM(i.paid_total),0) FROM invoices i WHERE i.sales_id=s.id AND i.invoice_date BETWEEN ? AND ? AND i.status<>'VOID') collected_value FROM sales s WHERE s.is_active=1 ORDER BY order_value DESC,s.name";
$q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to,$from,$to,$from,$to,$from,$to,$from,$to]);$rows=$q->fetchAll();
}elseif($dimension==='area'){
$sql="SELECT a.id,a.code,a.name,(SELECT COUNT(*) FROM visits v JOIN sales_area x ON x.sales_id=v.sales_id WHERE x.area_id=a.id AND v.visit_date BETWEEN ? AND ?) visits,(SELECT COUNT(*) FROM orders o JOIN sales_area x ON x.sales_id=o.sales_id WHERE x.area_id=a.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') orders,(SELECT COALESCE(SUM(o.grand_total),0) FROM orders o JOIN sales_area x ON x.sales_id=o.sales_id WHERE x.area_id=a.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') order_value FROM areas a WHERE a.is_active=1 ORDER BY order_value DESC,a.name";
$q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to,$from,$to]);$rows=$q->fetchAll();
}elseif($dimension==='channel'){
$sql="SELECT COALESCE(o.channel,'UNKNOWN') channel,(SELECT COUNT(*) FROM visits v JOIN outlets x ON x.id=v.outlet_id WHERE COALESCE(x.channel,'UNKNOWN')=COALESCE(o.channel,'UNKNOWN') AND v.visit_date BETWEEN ? AND ?) visits,(SELECT COUNT(*) FROM orders ord JOIN outlets x ON x.id=ord.outlet_id WHERE COALESCE(x.channel,'UNKNOWN')=COALESCE(o.channel,'UNKNOWN') AND DATE(ord.submitted_at) BETWEEN ? AND ? AND ord.status<>'CANCELLED') orders,(SELECT COALESCE(SUM(ord.grand_total),0) FROM orders ord JOIN outlets x ON x.id=ord.outlet_id WHERE COALESCE(x.channel,'UNKNOWN')=COALESCE(o.channel,'UNKNOWN') AND DATE(ord.submitted_at) BETWEEN ? AND ? AND ord.status<>'CANCELLED') order_value FROM outlets o WHERE o.is_active=1 GROUP BY COALESCE(o.channel,'UNKNOWN') ORDER BY order_value DESC";
$q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to,$from,$to]);$rows=$q->fetchAll();
}else{
$sql="SELECT p.id,p.sku,p.name,p.category,(SELECT COALESCE(SUM(oi.qty),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') qty_ordered,(SELECT COALESCE(SUM(oi.line_total),0) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') sales_value,(SELECT COUNT(DISTINCT o.id) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND DATE(o.submitted_at) BETWEEN ? AND ? AND o.status<>'CANCELLED') order_count FROM products p WHERE p.is_active=1 ORDER BY sales_value DESC,p.name";
$q=$pdo->prepare($sql);$q->execute([$from,$to,$from,$to,$from,$to]);$rows=$q->fetchAll();}
json_response(['success'=>true,'period'=>['from'=>$from,'to'=>$to],'dimension'=>$dimension,'rows'=>$rows]);
