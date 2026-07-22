<?php
$pageTitle = 'New Sale (POS)';
require_once __DIR__ . '/includes/functions.php';
require_login();
$pdo = db();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
$cart = &$_SESSION['cart'];

/* ---------------- Cart actions ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'add') {
        $mid = (int)post('medicine_id');
        $st = $pdo->prepare('SELECT id, name FROM medicines WHERE id=? AND status=1');
        $st->execute([$mid]);
        $m = $st->fetch();
        if ($m) {
            $stock = medicine_stock($mid);
            $price = medicine_price($mid);
            if ($stock <= 0) {
                flash($m['name'] . ' is out of stock.', 'warning');
            } elseif (isset($cart[$mid])) {
                $cart[$mid]['qty'] = min($cart[$mid]['qty'] + 1, $stock);
            } else {
                $cart[$mid] = ['name' => $m['name'], 'price' => $price, 'qty' => 1];
            }
        }
        redirect('pos.php');
    }

    if ($action === 'update') {
        foreach ((array)post('qty', []) as $mid => $q) {
            $mid = (int)$mid; $q = max(0, (int)$q);
            if (!isset($cart[$mid])) continue;
            $stock = medicine_stock($mid);
            if ($q <= 0) { unset($cart[$mid]); }
            else { $cart[$mid]['qty'] = min($q, $stock); }
            if (isset($_POST['price'][$mid])) {
                $cart[$mid]['price'] = max(0, (float)$_POST['price'][$mid]);
            }
        }
        flash('Cart updated.', 'info');
        redirect('pos.php');
    }

    if ($action === 'remove') {
        unset($cart[(int)post('medicine_id')]);
        redirect('pos.php');
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        redirect('pos.php');
    }

    if ($action === 'checkout') {
        if (!$cart) { flash('Cart is empty.', 'warning'); redirect('pos.php'); }

        $customerId   = (int)post('customer_id') ?: null;
        $discount     = max(0, (float)post('discount'));
        $discountType = post('discount_type') === 'percent' ? 'percent' : 'flat';
        $paymentMethod= in_array(post('payment_method'), ['cash','card','upi','other']) ? post('payment_method') : 'cash';
        $paid         = max(0, (float)post('paid'));
        $doctor       = trim(post('doctor_name'));

        $subTotal = 0;
        foreach ($cart as $mid => $it) {
            // final stock re-validation
            if ($it['qty'] > medicine_stock((int)$mid)) {
                flash('Stock changed for ' . $it['name'] . '. Please review the cart.', 'warning');
                redirect('pos.php');
            }
            $subTotal += $it['price'] * $it['qty'];
        }

        $discountAmt = $discountType === 'percent' ? $subTotal * ($discount / 100) : $discount;
        $discountAmt = min($discountAmt, $subTotal);
        $taxRate = (float)(settings()['tax_rate'] ?? 0);
        $taxable = $subTotal - $discountAmt;
        $taxAmt  = $taxable * ($taxRate / 100);
        $total   = $taxable + $taxAmt;
        if ($paid <= 0) $paid = $total;
        $change  = max(0, $paid - $total);

        try {
            $pdo->beginTransaction();
            $invoiceNo = next_invoice_no();

            $st = $pdo->prepare(
              'INSERT INTO sales
               (invoice_no, customer_id, user_id, sub_total, discount, discount_type, tax, total, paid, change_amount, profit, payment_method, doctor_name)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $st->execute([$invoiceNo, $customerId, current_user()['id'], $subTotal, $discountAmt,
                $discountType, $taxAmt, $total, $paid, $change, 0, $paymentMethod, $doctor ?: null]);
            $saleId = (int)$pdo->lastInsertId();

            $totalProfit = 0;
            $itemStmt = $pdo->prepare(
              'INSERT INTO sale_items (sale_id, medicine_id, batch_id, quantity, price, cost, subtotal)
               VALUES (?,?,?,?,?,?,?)'
            );

            foreach ($cart as $mid => $it) {
                $mid = (int)$mid;
                $lines = deduct_stock_fefo($mid, $it['qty'], current_user()['id'], $invoiceNo);
                // one sale_item row per batch consumed (accurate cost/profit)
                foreach ($lines as $ln) {
                    $sub = $it['price'] * $ln['qty'];
                    $itemStmt->execute([$saleId, $mid, $ln['batch_id'], $ln['qty'], $it['price'], $ln['cost'], $sub]);
                    $totalProfit += ($it['price'] - $ln['cost']) * $ln['qty'];
                }
            }
            // profit after distributing discount proportionally
            $totalProfit -= $discountAmt;
            $pdo->prepare('UPDATE sales SET profit=? WHERE id=?')->execute([$totalProfit, $saleId]);

            $pdo->commit();
            $_SESSION['cart'] = [];
            flash('Sale completed: ' . $invoiceNo, 'success');
            redirect('invoice.php?id=' . $saleId . '&print=1');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            flash('Checkout failed: ' . $ex->getMessage(), 'danger');
            redirect('pos.php');
        }
    }
}

/* ---------------- Data for view ---------------- */
require_once __DIR__ . '/includes/header.php';

$search = trim(get('q'));
$sql = "SELECT m.id, m.name, m.generic_name, m.prescription_required,
               COALESCE(SUM(b.quantity),0) stock
        FROM medicines m LEFT JOIN batches b ON b.medicine_id=m.id
        WHERE m.status=1";
$params = [];
if ($search !== '') {
    $sql .= " AND (m.name LIKE ? OR m.generic_name LIKE ? OR m.barcode = ?)";
    $params = ["%$search%", "%$search%", $search];
}
$sql .= " GROUP BY m.id ORDER BY m.name";
$st = $pdo->prepare($sql);
$st->execute($params);
$meds = $st->fetchAll();

$customers = $pdo->query('SELECT id, name FROM customers ORDER BY name')->fetchAll();

$subTotal = 0;
foreach ($cart as $it) { $subTotal += $it['price'] * $it['qty']; }
$taxRate = (float)(settings()['tax_rate'] ?? 0);
?>

<div class="pos-grid">
  <div class="card">
    <div class="card-head">
      <h2>Products</h2>
      <input id="posSearch" type="text" placeholder="Search name / generic / barcode..." style="max-width:320px">
    </div>
    <div class="med-grid">
      <?php foreach ($meds as $m):
        $price = medicine_price((int)$m['id']);
        $out = $m['stock'] <= 0; ?>
        <form method="post" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <input type="hidden" name="medicine_id" value="<?= (int)$m['id'] ?>">
          <button class="med-tile <?= $out?'out':'' ?>" type="submit"
                  data-search="<?= e(strtolower($m['name'].' '.$m['generic_name'])) ?>">
            <div class="nm"><?= e($m['name']) ?>
              <?php if ($m['prescription_required']): ?><span class="badge badge-red" style="font-size:.6rem">Rx</span><?php endif; ?>
            </div>
            <div class="pr"><?= money($price) ?></div>
            <small class="muted">Stock: <?= (int)$m['stock'] ?></small>
          </button>
        </form>
      <?php endforeach; ?>
      <?php if (!$meds): ?><p class="muted">No products found.</p><?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Current Bill</h2>
      <?php if ($cart): ?>
      <form method="post" class="inline-form"><?= csrf_field() ?>
        <input type="hidden" name="action" value="clear">
        <button class="btn btn-sm btn-ghost" data-confirm="Clear the whole cart?">Clear</button>
      </form>
      <?php endif; ?>
    </div>

    <?php if (!$cart): ?>
      <p class="muted">Click a product to add it to the bill.</p>
    <?php else: ?>
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="action" value="update">
      <div class="table-wrap"><table>
        <thead><tr><th>Item</th><th style="width:70px">Qty</th><th style="width:90px">Price</th><th class="text-right">Total</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($cart as $mid => $it): ?>
          <tr>
            <td><?= e($it['name']) ?></td>
            <td><input type="number" name="qty[<?= (int)$mid ?>]" value="<?= (int)$it['qty'] ?>" min="0" style="padding:.3rem"></td>
            <td><input type="number" step="0.01" name="price[<?= (int)$mid ?>]" value="<?= e(number_format($it['price'],2,'.','')) ?>" style="padding:.3rem"></td>
            <td class="text-right"><?= money($it['price']*$it['qty']) ?></td>
            <td><button formaction="pos.php" class="btn btn-sm btn-ghost"
                  onclick="this.form.querySelector('[name=action]').value='remove';this.form.insertAdjacentHTML('beforeend','<input type=hidden name=medicine_id value=<?= (int)$mid ?>>')">&times;</button></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <button class="btn btn-sm btn-ghost mt" type="submit">Update quantities</button>
    </form>

    <form method="post" class="mt">
      <?= csrf_field() ?><input type="hidden" name="action" value="checkout">
      <label>Customer</label>
      <select name="customer_id">
        <?php foreach ($customers as $c): ?>
          <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <div class="form-row">
        <div><label>Discount</label><input type="number" step="0.01" name="discount" value="0"></div>
        <div><label>Type</label>
          <select name="discount_type"><option value="flat">Flat (<?= e(currency()) ?>)</option><option value="percent">Percent (%)</option></select>
        </div>
      </div>
      <div class="form-row">
        <div><label>Payment</label>
          <select name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="upi">UPI</option><option value="other">Other</option></select>
        </div>
        <div><label>Amount Paid</label><input type="number" step="0.01" name="paid" placeholder="auto = total"></div>
      </div>
      <label>Doctor (optional)</label>
      <input type="text" name="doctor_name" placeholder="Prescribing doctor">

      <div class="mt" style="border-top:1px dashed var(--line);padding-top:.8rem">
        <div style="display:flex;justify-content:space-between"><span class="muted">Sub Total</span><strong><?= money($subTotal) ?></strong></div>
        <div style="display:flex;justify-content:space-between"><span class="muted">Tax (<?= e($taxRate) ?>%)</span><span>applied at checkout</span></div>
        <div style="display:flex;justify-content:space-between;margin-top:.4rem">
          <span>Payable (approx)</span><span class="cart-total"><?= money($subTotal * (1 + $taxRate/100)) ?></span></div>
      </div>
      <button class="btn btn-green btn-block mt" type="submit">Complete Sale &amp; Print</button>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
