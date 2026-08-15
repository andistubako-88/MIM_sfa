#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${MIM_E2E_BASE_URL:-http://127.0.0.1:8090}"
COOKIE_DIR="${RUNNER_TEMP:-/tmp}/mim-sfa-e2e"
SERVER_LOG="${RUNNER_TEMP:-/tmp}/mim-sfa-e2e-server.log"
mkdir -p "$COOKIE_DIR"
rm -f "$COOKIE_DIR"/*.cookie "$SERVER_LOG"

php -S 127.0.0.1:8090 -t . >"$SERVER_LOG" 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT

server_ready=false
for i in {1..30}; do
  if ! kill -0 "$SERVER_PID" 2>/dev/null; then
    echo "E2E server exited unexpectedly." >&2
    cat "$SERVER_LOG" >&2 || true
    exit 1
  fi
  if curl -fsS --max-time 2 "$BASE_URL/api/health.php" >/dev/null 2>&1; then
    server_ready=true
    break
  fi
  sleep 1
done

if [[ "$server_ready" != "true" ]]; then
  echo "E2E server did not become ready at $BASE_URL." >&2
  cat "$SERVER_LOG" >&2 || true
  exit 1
fi

fixture=$(php tests/e2e_fixture.php)
outlet_id=$(jq -r .outlet_id <<<"$fixture")
product_id=$(jq -r .product_id <<<"$fixture")
warehouse_location_id=$(jq -r .warehouse_location_id <<<"$fixture")
sales_location_id=$(jq -r .sales_location_id <<<"$fixture")
sales_id=$(jq -r .sales_id <<<"$fixture")
lat=$(jq -r .latitude <<<"$fixture")
lon=$(jq -r .longitude <<<"$fixture")
password=$(jq -r .password <<<"$fixture")

login() {
  local name="$1"
  local user="$2"
  local cookie="$COOKIE_DIR/$name.cookie"
  local csrf_json csrf login_json
  csrf_json=$(curl -fsS -c "$cookie" "$BASE_URL/api/auth.php?action=csrf")
  csrf=$(jq -r .csrf_token <<<"$csrf_json")
  login_json=$(curl -fsS -b "$cookie" -c "$cookie" -X POST \
    -H "X-CSRF-Token: $csrf" \
    --data-urlencode action=login --data-urlencode username="$user" --data-urlencode password="$password" \
    "$BASE_URL/api/auth.php")
  test "$(jq -r .success <<<"$login_json")" = "true"
  jq -r .csrf_token <<<"$login_json"
}

api_form() {
  local name="$1"
  local csrf="$2"
  local path="$3"
  shift 3
  curl -sS -b "$COOKIE_DIR/$name.cookie" -c "$COOKIE_DIR/$name.cookie" -X POST \
    -H "X-CSRF-Token: $csrf" "$@" "$BASE_URL/$path"
}

api_json() {
  local name="$1"
  local csrf="$2"
  local path="$3"
  local payload="$4"
  curl -sS -b "$COOKIE_DIR/$name.cookie" -c "$COOKIE_DIR/$name.cookie" -X POST \
    -H "Content-Type: application/json" -H "X-CSRF-Token: $csrf" \
    --data "$payload" "$BASE_URL/$path"
}

# 1) Warehouse loads 10 units into Sales stock.
wh_csrf=$(login warehouse e2e_warehouse)
loading=$(api_json warehouse "$wh_csrf" api/loading.php "$(jq -nc --argjson from "$warehouse_location_id" --argjson to "$sales_location_id" --argjson pid "$product_id" '{warehouse_location_id:$from,sales_location_id:$to,items:[{product_id:$pid,qty:10}]}')")
test "$(jq -r .success <<<"$loading")" = "true"

# 2) Sales checks in within the configured 100m radius with a mandatory photo.
sales_csrf=$(login sales e2e_sales)
checkin=$(api_form sales "$sales_csrf" "api/visit.php?action=checkin" \
  --data-urlencode "outlet_id=$outlet_id" --data-urlencode "latitude=$lat" --data-urlencode "longitude=$lon" \
  --data-urlencode "accuracy_meters=5" --data-urlencode "mock_location_detected=false" --data-urlencode "photo_path=/uploads/e2e-checkin.jpg")
test "$(jq -r .success <<<"$checkin")" = "true"
visit_id=$(jq -r .visit_id <<<"$checkin")

# 3) Sales creates an EC order during the active visit.
order=$(api_json sales "$sales_csrf" api/orders.php "$(jq -nc --argjson visit "$visit_id" --argjson pid "$product_id" '{visit_id:$visit,order_type:"EC",items:[{product_id:$pid,qty:5,discount_percent:0}],notes:"E2E transaction"}')")
test "$(jq -r .success <<<"$order")" = "true"
order_id=$(jq -r .order_id <<<"$order")

# 4) Supervisor approves order.
sup_csrf=$(login supervisor e2e_supervisor)
approve=$(api_json supervisor "$sup_csrf" api/order_approve.php "$(jq -nc --argjson id "$order_id" '{order_id:$id}')")
test "$(jq -r .success <<<"$approve")" = "true"

# 5) Sales reserves stock; Supervisor commits because commit is an approval-level operation.
reserve=$(api_json sales "$sales_csrf" api/order_reserve.php "$(jq -nc --argjson id "$order_id" --argjson loc "$sales_location_id" '{order_id:$id,stock_location_id:$loc}')")
test "$(jq -r .success <<<"$reserve")" = "true"
commit=$(api_json supervisor "$sup_csrf" api/order_commit.php "$(jq -nc --argjson id "$order_id" '{order_id:$id}')")
test "$(jq -r .success <<<"$commit")" = "true"

# 6) Delivery -> invoice -> payment -> settlement.
delivery=$(api_json supervisor "$sup_csrf" api/delivery.php "$(jq -nc --argjson id "$order_id" '{order_id:$id,recipient_name:"E2E Receiver"}')")
test "$(jq -r .success <<<"$delivery")" = "true"
delivery_id=$(jq -r .delivery_id <<<"$delivery")

invoice=$(api_json supervisor "$sup_csrf" api/invoice.php "$(jq -nc --argjson id "$delivery_id" '{delivery_id:$id}')")
test "$(jq -r .success <<<"$invoice")" = "true"
invoice_id=$(jq -r .invoice_id <<<"$invoice")
grand_total=$(jq -r .grand_total <<<"$invoice")

payment=$(api_json supervisor "$sup_csrf" api/payment.php "$(jq -nc --argjson id "$invoice_id" --argjson amount "$grand_total" '{invoice_id:$id,amount:$amount,payment_method:"CASH"}')")
test "$(jq -r .success <<<"$payment")" = "true"
test "$(jq -r .invoice_status <<<"$payment")" = "PAID"

settlement=$(api_json supervisor "$sup_csrf" api/settlement.php "$(jq -nc --argjson sid "$sales_id" --argjson cash "$grand_total" '{sales_id:$sid,settlement_date:(now|strftime("%Y-%m-%d")),submitted_cash:$cash}')")
test "$(jq -r .success <<<"$settlement")" = "true"
settlement_number=$(jq -r .settlement_number <<<"$settlement")

# Approve settlement as Owner to verify the management approval boundary.
owner_csrf=$(login owner e2e_owner)
settlement_id=$(php -r 'require "api/bootstrap.php"; $q=db()->prepare("SELECT id FROM settlement_documents WHERE settlement_number=?"); $q->execute([$argv[1]]); echo (int)$q->fetchColumn();' "$settlement_number")
settlement_approve=$(api_json owner "$owner_csrf" api/settlement_approve.php "$(jq -nc --argjson id "$settlement_id" '{settlement_id:$id,approve:true,notes:"E2E approved"}')")
test "$(jq -r .success <<<"$settlement_approve")" = "true"
test "$(jq -r .status <<<"$settlement_approve")" = "APPROVED"

# 7) Negative: duplicate delivery must fail.
duplicate_delivery=$(api_json supervisor "$sup_csrf" api/delivery.php "$(jq -nc --argjson id "$order_id" '{order_id:$id}')")
test "$(jq -r .success <<<"$duplicate_delivery")" = "false"
test "$(jq -r .message <<<"$duplicate_delivery")" = "Delivery untuk order ini sudah dibuat."

# 8) Negative: overpayment must fail and invoice remains PAID.
overpayment=$(api_json supervisor "$sup_csrf" api/payment.php "$(jq -nc --argjson id "$invoice_id" '{invoice_id:$id,amount:1,payment_method:"CASH"}')")
test "$(jq -r .success <<<"$overpayment")" = "false"
test "$(jq -r .message <<<"$overpayment")" = "Pembayaran melebihi saldo invoice."

# 9) Database assertions after the API flow.
export MIM_E2E_VISIT_ID="$visit_id" MIM_E2E_ORDER_ID="$order_id" MIM_E2E_INVOICE_ID="$invoice_id" MIM_E2E_SALES_ID="$sales_id"
php -r '
require "api/bootstrap.php";
$pdo=db();
$checks=[
  "SELECT COUNT(*) FROM visits WHERE status=\"ACTIVE\" AND id=".(int)getenv("MIM_E2E_VISIT_ID"),
  "SELECT COUNT(*) FROM orders WHERE id=".(int)getenv("MIM_E2E_ORDER_ID")." AND status=\"APPROVED\"",
  "SELECT COUNT(*) FROM order_stock_reservations WHERE order_id=".(int)getenv("MIM_E2E_ORDER_ID")." AND status=\"COMMITTED\"",
  "SELECT COUNT(*) FROM invoices WHERE id=".(int)getenv("MIM_E2E_INVOICE_ID")." AND status=\"PAID\" AND paid_total=grand_total",
  "SELECT COUNT(*) FROM payments WHERE invoice_id=".(int)getenv("MIM_E2E_INVOICE_ID")." AND status=\"POSTED\"",
  "SELECT COUNT(*) FROM settlement_documents WHERE sales_id=".(int)getenv("MIM_E2E_SALES_ID")." AND status=\"APPROVED\"",
  "SELECT COUNT(*) FROM stock_balances WHERE stock_location_id=(SELECT id FROM stock_locations WHERE code=\"E2E-SALES\") AND product_id=(SELECT id FROM products WHERE sku=\"E2E-SKU-001\") AND qty=5",
];
foreach($checks as $sql){if(!(int)$pdo->query($sql)->fetchColumn()) throw new RuntimeException("DB assertion failed: $sql");}
echo "API E2E database assertions: PASS\n";
'

echo "MIM SFA API E2E: PASS"
