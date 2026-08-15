<?php

declare(strict_types=1);

/**
 * Deterministic fixture for API E2E. CI must point config/config.php at a disposable DB.
 */
require dirname(__DIR__) . '/api/bootstrap.php';

$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->exec("UPDATE company_settings SET operational_start='00:00:00', operational_end='23:59:59', timezone='Asia/Jakarta', checkin_radius_meters=100, minimum_visit_minutes=5, fake_gps_block_enabled=1 WHERE id=1");

    $roles = [];
    foreach (['SALES','SUPERVISOR','WAREHOUSE','OWNER'] as $code) {
        $q = $pdo->prepare('SELECT id FROM roles WHERE code=?');
        $q->execute([$code]);
        $roles[$code] = (int)$q->fetchColumn();
    }

    $users = [];
    foreach ([
        ['e2e_sales','E2E Sales',$roles['SALES']],
        ['e2e_supervisor','E2E Supervisor',$roles['SUPERVISOR']],
        ['e2e_warehouse','E2E Warehouse',$roles['WAREHOUSE']],
        ['e2e_owner','E2E Owner',$roles['OWNER']],
    ] as [$username,$name,$roleId]) {
        $pdo->prepare('DELETE FROM users WHERE username=?')->execute([$username]);
        $q=$pdo->prepare('INSERT INTO users(role_id,username,password_hash,full_name) VALUES(?,?,?,?)');
        $q->execute([$roleId,$username,password_hash('E2E-Password-2026!',PASSWORD_DEFAULT),$name]);
        $users[$username]=(int)$pdo->lastInsertId();
    }

    $pdo->prepare("DELETE FROM products WHERE sku='E2E-SKU-001'")->execute();
    $pdo->prepare("INSERT INTO products(sku,name,category,unit,sell_price,cost_price) VALUES('E2E-SKU-001','E2E Test Product','TEST','PCS',10000,5000)")->execute();
    $productId=(int)$pdo->lastInsertId();

    $pdo->prepare("DELETE FROM outlets WHERE code='E2E-OUT-001'")->execute();
    $pdo->prepare("INSERT INTO outlets(code,name,address,city,district,latitude,longitude,channel,visit_route) VALUES('E2E-OUT-001','E2E Test Outlet','CI fixture','Cianjur','Cilaku',-6.82,107.14,'GENERAL','DAILY')")->execute();
    $outletId=(int)$pdo->lastInsertId();

    $pdo->prepare('INSERT INTO sales(user_id,employee_code,name,phone,channel) VALUES(?,?,?,?,?)')->execute([$users['e2e_sales'],'E2E-SALES-001','E2E Sales','0800000000','GENERAL']);
    $salesId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO sales_outlet(sales_id,outlet_id,is_active) VALUES(?,?,1)')->execute([$salesId,$outletId]);
    $pdo->prepare('INSERT INTO outlet_products(outlet_id,product_id,is_active) VALUES(?,?,1)')->execute([$outletId,$productId]);

    $pdo->prepare("DELETE FROM stock_locations WHERE code IN ('E2E-WH','E2E-SALES')")->execute();
    $pdo->prepare("INSERT INTO warehouses(code,name) VALUES('E2E-WH','E2E Warehouse')");
    $warehouseId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stock_locations(warehouse_id,code,name,location_type,is_active) VALUES(?, 'E2E-WH','E2E Warehouse','WAREHOUSE',1)")->execute([$warehouseId]);
    $warehouseLocationId=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO stock_locations(sales_id,code,name,location_type,is_active) VALUES(?, 'E2E-SALES','E2E Sales Stock','SALES',1)")->execute([$salesId]);
    $salesLocationId=(int)$pdo->lastInsertId();
    $pdo->prepare('INSERT INTO stock_balances(stock_location_id,product_id,qty) VALUES(?,?,?)')->execute([$warehouseLocationId,$productId,20]);

    echo json_encode([
        'sales_username'=>'e2e_sales','supervisor_username'=>'e2e_supervisor','warehouse_username'=>'e2e_warehouse','owner_username'=>'e2e_owner',
        'password'=>'E2E-Password-2026!','sales_id'=>$salesId,'outlet_id'=>$outletId,'product_id'=>$productId,
        'warehouse_location_id'=>$warehouseLocationId,'sales_location_id'=>$salesLocationId,'latitude'=>-6.82,'longitude'=>107.14,
    ], JSON_UNESCAPED_SLASHES), PHP_EOL;
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR,$e->getMessage().PHP_EOL);
    exit(1);
}
