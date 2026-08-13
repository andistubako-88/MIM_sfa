<?php

declare(strict_types=1);

require __DIR__ . '/auth.php';

$user = require_permission('masters.manage');
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$resource = strtolower(trim((string) ($_GET['resource'] ?? '')));

$allowed = ['areas', 'sales', 'outlets', 'products'];
if (!in_array($resource, $allowed, true)) {
    json_response(['success' => false, 'message' => 'Resource master tidak dikenal.'], 404);
}

function input_data(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);
    if (is_array($json)) return $json;
    return $_POST;
}

function audit(string $action, string $entity, ?int $id, ?array $old, ?array $new): void {
    global $user;
    $stmt = db()->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, old_data, new_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([(int)$user['id'], $action, $entity, $id, $old ? json_encode($old) : null, $new ? json_encode($new) : null, $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null]);
}

$tables = ['areas' => 'areas', 'sales' => 'sales', 'outlets' => 'outlets', 'products' => 'products'];
$table = $tables[$resource];

if ($method === 'GET') {
    $stmt = db()->query("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY id DESC");
    json_response(['success' => true, 'resource' => $resource, 'data' => $stmt->fetchAll()]);
}

require_csrf();
$data = input_data();

if ($method === 'POST') {
    if ($resource === 'areas') {
        $code = strtoupper(trim((string)($data['code'] ?? '')));
        $name = trim((string)($data['name'] ?? ''));
        if ($code === '' || $name === '') json_response(['success'=>false,'message'=>'Kode dan nama area wajib diisi.'],422);
        $stmt = db()->prepare('INSERT INTO areas (code,name) VALUES (?,?)'); $stmt->execute([$code,$name]); $id=(int)db()->lastInsertId();
    } elseif ($resource === 'products') {
        $sku = strtoupper(trim((string)($data['sku'] ?? ''))); $name=trim((string)($data['name'] ?? ''));
        if ($sku === '' || $name === '') json_response(['success'=>false,'message'=>'SKU dan nama produk wajib diisi.'],422);
        $stmt=db()->prepare('INSERT INTO products (sku,name,category,unit,sell_price,cost_price) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$sku,$name,$data['category']??null,$data['unit']??'PCS',(float)($data['sell_price']??0),(float)($data['cost_price']??0)]); $id=(int)db()->lastInsertId();
    } elseif ($resource === 'sales') {
        $employee=(string)($data['employee_code']??''); $name=trim((string)($data['name']??'')); $userId=(int)($data['user_id']??0);
        if ($employee === '' || $name === '' || $userId < 1) json_response(['success'=>false,'message'=>'Employee code, nama, dan user_id wajib diisi.'],422);
        $stmt=db()->prepare('INSERT INTO sales (user_id,employee_code,name,phone,channel) VALUES (?,?,?,?,?)'); $stmt->execute([$userId,$employee,$name,$data['phone']??null,$data['channel']??null]); $id=(int)db()->lastInsertId();
    } else {
        $code=strtoupper(trim((string)($data['code']??''))); $name=trim((string)($data['name']??'')); $lat=(float)($data['latitude']??0); $lng=(float)($data['longitude']??0);
        if ($code === '' || $name === '' || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) json_response(['success'=>false,'message'=>'Kode, nama, latitude dan longitude valid wajib diisi.'],422);
        $stmt=db()->prepare('INSERT INTO outlets (code,name,address,province,city,district,village,latitude,longitude,channel,visit_route) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$code,$name,$data['address']??null,$data['province']??null,$data['city']??null,$data['district']??null,$data['village']??null,$lat,$lng,$data['channel']??null,$data['visit_route']??'DAILY']); $id=(int)db()->lastInsertId();
    }
    audit('CREATE',$resource,$id,null,$data);
    json_response(['success'=>true,'id'=>$id],201);
}

if ($method === 'PUT') {
    $id=(int)($_GET['id']??0); if ($id<1) json_response(['success'=>false,'message'=>'ID wajib diisi.'],422);
    $stmt=db()->prepare("SELECT * FROM {$table} WHERE id=? AND is_active=1"); $stmt->execute([$id]); $old=$stmt->fetch(); if(!$old) json_response(['success'=>false,'message'=>'Data tidak ditemukan.'],404);
    if ($resource === 'areas') { $stmt=db()->prepare('UPDATE areas SET code=?, name=? WHERE id=?'); $stmt->execute([strtoupper(trim((string)($data['code']??$old['code']))),trim((string)($data['name']??$old['name'])),$id]); }
    elseif ($resource === 'products') { $stmt=db()->prepare('UPDATE products SET sku=?, name=?, category=?, unit=?, sell_price=?, cost_price=? WHERE id=?'); $stmt->execute([$data['sku']??$old['sku'],$data['name']??$old['name'],$data['category']??$old['category'],$data['unit']??$old['unit'],$data['sell_price']??$old['sell_price'],$data['cost_price']??$old['cost_price'],$id]); }
    elseif ($resource === 'sales') { $stmt=db()->prepare('UPDATE sales SET employee_code=?, name=?, phone=?, channel=? WHERE id=?'); $stmt->execute([$data['employee_code']??$old['employee_code'],$data['name']??$old['name'],$data['phone']??$old['phone'],$data['channel']??$old['channel'],$id]); }
    else { $stmt=db()->prepare('UPDATE outlets SET code=?, name=?, address=?, province=?, city=?, district=?, village=?, latitude=?, longitude=?, channel=?, visit_route=? WHERE id=?'); $stmt->execute([$data['code']??$old['code'],$data['name']??$old['name'],$data['address']??$old['address'],$data['province']??$old['province'],$data['city']??$old['city'],$data['district']??$old['district'],$data['village']??$old['village'],$data['latitude']??$old['latitude'],$data['longitude']??$old['longitude'],$data['channel']??$old['channel'],$data['visit_route']??$old['visit_route'],$id]); }
    audit('UPDATE',$resource,$id,$old,$data); json_response(['success'=>true,'id'=>$id]);
}

if ($method === 'DELETE') {
    $id=(int)($_GET['id']??0); if($id<1) json_response(['success'=>false,'message'=>'ID wajib diisi.'],422);
    $stmt=db()->prepare("SELECT * FROM {$table} WHERE id=? AND is_active=1"); $stmt->execute([$id]); $old=$stmt->fetch(); if(!$old) json_response(['success'=>false,'message'=>'Data tidak ditemukan.'],404);
    $stmt=db()->prepare("UPDATE {$table} SET is_active=0 WHERE id=?"); $stmt->execute([$id]); audit('DEACTIVATE',$resource,$id,$old,['is_active'=>0]); json_response(['success'=>true,'id'=>$id]);
}

json_response(['success'=>false,'message'=>'Method tidak didukung.'],405);
