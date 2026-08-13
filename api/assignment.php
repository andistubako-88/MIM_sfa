<?php

declare(strict_types=1);
require __DIR__ . '/auth.php';
$user = require_permission('masters.manage');
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_response(['success'=>false,'message'=>'Method tidak didukung.'],405);
require_csrf();
$data=json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
$type=strtolower((string)($data['type']??''));
if ($type === 'sales_area') {
  $salesId=(int)($data['sales_id']??0); $areaId=(int)($data['area_id']??0);
  if($salesId<1||$areaId<1) json_response(['success'=>false,'message'=>'sales_id dan area_id wajib.'],422);
  $stmt=db()->prepare('INSERT IGNORE INTO sales_area (sales_id,area_id) VALUES (?,?)'); $stmt->execute([$salesId,$areaId]);
} elseif ($type === 'sales_outlet') {
  $salesId=(int)($data['sales_id']??0); $outletId=(int)($data['outlet_id']??0);
  if($salesId<1||$outletId<1) json_response(['success'=>false,'message'=>'sales_id dan outlet_id wajib.'],422);
  $stmt=db()->prepare('INSERT INTO sales_outlet (sales_id,outlet_id,priority,is_active) VALUES (?,?,?,1) ON DUPLICATE KEY UPDATE priority=VALUES(priority), is_active=1'); $stmt->execute([$salesId,$outletId,(int)($data['priority']??0)]);
} elseif ($type === 'outlet_product') {
  $outletId=(int)($data['outlet_id']??0); $productId=(int)($data['product_id']??0);
  if($outletId<1||$productId<1) json_response(['success'=>false,'message'=>'outlet_id dan product_id wajib.'],422);
  $stmt=db()->prepare('INSERT INTO outlet_products (outlet_id,product_id,is_active) VALUES (?,?,1) ON DUPLICATE KEY UPDATE is_active=1'); $stmt->execute([$outletId,$productId]);
} else json_response(['success'=>false,'message'=>'Assignment type tidak dikenal.'],422);
json_response(['success'=>true,'type'=>$type]);
