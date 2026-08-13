<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';
$user=require_permission('audit.view');
$limit=min(max((int)($_GET['limit']??100),1),500);
$entity=trim((string)($_GET['entity_type']??''));
$sql='SELECT al.id,al.action,al.entity_type,al.entity_id,al.user_id,u.full_name,al.ip_address,al.created_at FROM audit_logs al LEFT JOIN users u ON u.id=al.user_id';
$params=[];
if($entity!==''){$sql.=' WHERE al.entity_type=?';$params[]=$entity;}
$sql.=' ORDER BY al.created_at DESC LIMIT '. $limit;
$q=db()->prepare($sql);$q->execute($params);
json_response(['success'=>true,'logs'=>$q->fetchAll()]);
