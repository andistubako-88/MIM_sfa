#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${MIM_E2E_BASE_URL:-http://127.0.0.1:8080}"
COOKIE_DIR="${RUNNER_TEMP:-/tmp}/mim-sfa-e2e"
mkdir -p "$COOKIE_DIR"
rm -f "$COOKIE_DIR"/*.cookie "$COOKIE_DIR"/*.json

php -S 127.0.0.1:8080 >/tmp/mim-sfa-e2e-server.log 2>&1 &
SERVER_PID=$!
trap 'kill "$SERVER_PID" 2>/dev/null || true' EXIT
for i in {1..30}; do
  if curl -fsS "$BASE_URL/api/health.php" >/dev/null; then break; fi
  sleep 1
done

fixture=$(php tests/e2e_fixture.php)
outlet_id=$(jq -r .outlet_id <<<"$fixture")
product_id=$(jq -r .product_id <<<"$fixture")
warehouse_location_id=$(jq -r .warehouse_location_id <<<"$fixture")
sales_location_id=$(jq -r .sales_location_id <<<"$fixture")
lat=$(jq -r .latitude <<<"$fixture")
lon=$(jq -r .longitude <<<"$fixture")
password=$(jq -r .password <<<"$fixture")

login() {
  local name="$1" user="$2"
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
  local name="$1" csrf="$2" path="$3"; shift 3
  curl -fsS -b "$COOKIE_DIR/$name.cookie" -c "$COOKIE_DIR/$name.cookie" -X POST \
    -H "X-CSRF-Token: $csrf" "$@" "$BASE_URL/$path"
}

api_json() {
  local name="$1" csrf="$2" path="$3" payload="$4"
  curl -fsS -b "$COOKIE_DIR/$name.cookie" -c "$COOKIE_DIR/$name.cookie" -X POST \
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

auto_fail() {
  local result="$1" expected="$2"
  if [[ "$result" == "$expected" ]]; then return 0; fi
  echo "Unexpected response: $result" >&2
  return 1
}

# 4) Supervisor approves order.
sup_csrf=$(login supervisor e2e_supervisor)
approve=$(api_json supervisor "$sup_csrf" api/order_approve.php "$(jq -nc --argjson id "$order_id" '{order_id:$id}')")
test "$(jq -r .success <<<"$approve")" = "true"

# 5) Sales reserves stock; then Supervisor commits because commit is an approval-level operation.
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

settlement=$(api_json supervisor "$sup_csrf" api/settlement.php "$(jq -nc --argjson sid "$(jq -r .sales_id <<<"$fixture")" '{sales_id:$sid,settlement_date:(now|strftime("%Y-%m-%d")),submitted_cash:1000}')")
test "$(jq -r .success <<<"$settlement")" = "true"
settlement_id=$(jq -r .settlement_id <<<"$settlement")

# 7) Negative: duplicate delivery must fail.
set +e
duplicate_delivery=$(api_json supervisor "$sup_csrf" api/delivery.php "$(jq -nc --argjson id "$order_id" '{order_id:$id}')")
set -e
# curl -f is not used for this negative call; endpoint should return 409 JSON.
test "$(jq -r .success <<<"$duplicate_delivery")" = "false"

# 8) Negative: overpayment must fail and invoice remains PAID.
set +e
overpayment=$(api_json supervisor "$sup_csrf" api/payment.php "$(jq -nc --argjson id "$invoice_id" '{invoice_id:$id,amount:1,payment_method:"CASH"}')")
set -e
test "$(jq -r .success <<<"$overpayment")" = "false"

# 9) Database assertions after the API flow.
php -r '
require "api/bootstrap.php";
$pdo=db();
$checks=[
  "SELECT COUNT(*) FROM visits WHERE status=\"ACTIVE\" AND id=".(int)getenv("MIM_E2E_VISIT_ID"),
  "SELECT COUNT(*) FROM orders WHERE id=".(int)getenv("MIM_E2E_ORDER_ID")." AND status=\"APPROVED\"",
  "SELECT COUNT(*) FROM order_stock_reservations WHERE order_id=".(int)getenv("MIM_E2E_ORDER_ID")." AND status=\"COMMITTED\"",
  "SELECT COUNT(*) FROM invoices WHERE id=".(int)getenv("MIM_E2E_INVOICE_ID")." AND status=\"PAID\"",
  "SELECT COUNT(*) FROM payments WHERE invoice_id=".(int)getenv("MIM_E2E_INVOICE_ID")." AND status=\"POSTED\"",
];
foreach($checks as $sql){if(!(int)$pdo->query($sql)->fetchColumn()) throw new RuntimeException("DB assertion failed: $sql");}
echo "API E2E database assertions: PASS\n";
'

echo "MIM SFA API E2E: PASS"
