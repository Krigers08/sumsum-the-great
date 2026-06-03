<?php
require_once 'db.php';

$page = $_GET['page'] ?? 'dashboard';
$search = trim($_GET['q'] ?? '');
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = 25;

function paginate_links(string $page, int $offset, int $limit, int $total, array $extra = []): string {
    $params = array_merge(['page' => $page, 'offset' => $offset - $limit], $extra);
    $prev = $offset > 0 ? '<a href="?'.http_build_query($params).'">&#8592; Prev</a>' : '<span class="disabled">&#8592; Prev</span>';
    $params['offset'] = $offset + $limit;
    $next = ($offset + $limit < $total) ? '<a href="?'.http_build_query($params).'">Next &#8594;</a>' : '<span class="disabled">Next &#8594;</span>';
    $from = $total ? $offset + 1 : 0;
    $to = min($offset + $limit, $total);
    return "<div class='pagination'>$prev <span>$from&ndash;$to of $total</span> $next</div>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shop Data Manager</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: system-ui, sans-serif; background: #f4f5f7; color: #1a1a2e; }
nav { background: #1a1a2e; padding: 12px 24px; display: flex; gap: 16px; align-items: center; }
nav a { color: #a0aec0; text-decoration: none; font-size: 14px; padding: 6px 12px; border-radius: 4px; }
nav a:hover, nav a.active { background: #2d3748; color: #fff; }
nav .brand { color: #fff; font-weight: 700; font-size: 16px; margin-right: 16px; }
.container { max-width: 1200px; margin: 24px auto; padding: 0 24px; }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 16px; margin-bottom: 32px; }
.card { background: #fff; border-radius: 8px; padding: 20px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
.card .num { font-size: 2rem; font-weight: 700; color: #4f46e5; }
.card .label { font-size: 13px; color: #6b7280; margin-top: 4px; }
h2 { font-size: 18px; margin-bottom: 16px; color: #1a1a2e; }
.search-bar { display: flex; gap: 8px; margin-bottom: 16px; }
.search-bar input { flex: 1; padding: 8px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; }
.search-bar button { padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; }
table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
th { background: #f9fafb; text-align: left; padding: 10px 14px; font-size: 12px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; border-bottom: 1px solid #e5e7eb; }
td { padding: 10px 14px; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #f9fafb; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 12px; font-size: 12px; font-weight: 500; }
.badge-green { background: #d1fae5; color: #065f46; }
.badge-red { background: #fee2e2; color: #991b1b; }
.pagination { display: flex; gap: 12px; align-items: center; margin-top: 16px; font-size: 14px; color: #6b7280; }
.pagination a { color: #4f46e5; text-decoration: none; }
.pagination .disabled { color: #d1d5db; }
.section { background: #fff; border-radius: 8px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.1); margin-bottom: 24px; }
</style>
</head>
<body>
<nav>
  <span class="brand">Northwind</span>
  <a href="?page=dashboard" class="<?= $page==='dashboard'?'active':'' ?>">Dashboard</a>
  <a href="?page=customers" class="<?= $page==='customers'?'active':'' ?>">Customers</a>
  <a href="?page=products" class="<?= $page==='products'?'active':'' ?>">Products</a>
  <a href="?page=orders" class="<?= $page==='orders'?'active':'' ?>">Orders</a>
  <a href="?page=employees" class="<?= $page==='employees'?'active':'' ?>">Employees</a>
  <a href="?page=suppliers" class="<?= $page==='suppliers'?'active':'' ?>">Suppliers</a>
</nav>
<div class="container">
<?php if ($page === 'dashboard'): ?>
  <h2>Dashboard</h2>
  <?php
    $counts = [
      'Customers'  => 'SELECT COUNT(*) FROM customers',
      'Products'   => 'SELECT COUNT(*) FROM products',
      'Orders'     => 'SELECT COUNT(*) FROM orders',
      'Employees'  => 'SELECT COUNT(*) FROM employees',
      'Suppliers'  => 'SELECT COUNT(*) FROM suppliers',
      'Categories' => 'SELECT COUNT(*) FROM categories',
    ];
  ?>
  <div class="cards">
  <?php foreach ($counts as $label => $sql): ?>
    <?php $n = $pdo->query($sql)->fetchColumn(); ?>
    <div class="card"><div class="num"><?= number_format($n) ?></div><div class="label"><?= $label ?></div></div>
  <?php endforeach; ?>
  </div>

  <div class="section">
    <h2>Top 10 Products by Revenue</h2>
    <table>
      <tr><th>Product</th><th>Category</th><th>Revenue</th></tr>
      <?php
        $rows = $pdo->query("
          SELECT p.product_name, c.category_name,
                 SUM(od.unit_price * od.quantity * (1 - od.discount))::numeric(12,2) AS revenue
          FROM order_details od
          JOIN products p ON p.product_id = od.product_id
          JOIN categories c ON c.category_id = p.category_id
          GROUP BY p.product_name, c.category_name
          ORDER BY revenue DESC LIMIT 10
        ")->fetchAll();
        foreach ($rows as $r): ?>
      <tr><td><?= htmlspecialchars($r['product_name']) ?></td>
          <td><?= htmlspecialchars($r['category_name']) ?></td>
          <td>$<?= number_format($r['revenue'], 2) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

  <div class="section">
    <h2>Top 10 Customers by Orders</h2>
    <table>
      <tr><th>Company</th><th>Country</th><th>Orders</th></tr>
      <?php
        $rows = $pdo->query("
          SELECT c.company_name, c.country, COUNT(o.order_id) AS order_count
          FROM customers c JOIN orders o ON o.customer_id = c.customer_id
          GROUP BY c.company_name, c.country ORDER BY order_count DESC LIMIT 10
        ")->fetchAll();
        foreach ($rows as $r): ?>
      <tr><td><?= htmlspecialchars($r['company_name']) ?></td>
          <td><?= htmlspecialchars($r['country']) ?></td>
          <td><?= $r['order_count'] ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>

<?php elseif ($page === 'customers'): ?>
  <h2>Customers</h2>
  <form class="search-bar" method="get">
    <input type="hidden" name="page" value="customers">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by company or country...">
    <button type="submit">Search</button>
  </form>
  <?php
    $where = $search ? "WHERE company_name ILIKE :q OR country ILIKE :q2" : "";
    $params = $search ? [':q' => "%$search%", ':q2' => "%$search%"] : [];
    $total = $pdo->prepare("SELECT COUNT(*) FROM customers $where");
    $total->execute($params);
    $total = (int)$total->fetchColumn();
    $stmt = $pdo->prepare("SELECT customer_id, company_name, contact_name, city, country, phone FROM customers $where ORDER BY company_name LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
  ?>
  <table>
    <tr><th>ID</th><th>Company</th><th>Contact</th><th>City</th><th>Country</th><th>Phone</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= htmlspecialchars($r['customer_id']) ?></td>
        <td><?= htmlspecialchars($r['company_name']) ?></td>
        <td><?= htmlspecialchars($r['contact_name']) ?></td>
        <td><?= htmlspecialchars($r['city']) ?></td>
        <td><?= htmlspecialchars($r['country']) ?></td>
        <td><?= htmlspecialchars($r['phone']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?= paginate_links('customers', $offset, $limit, $total, $search ? ['q' => $search] : []) ?>

<?php elseif ($page === 'products'): ?>
  <h2>Products</h2>
  <form class="search-bar" method="get">
    <input type="hidden" name="page" value="products">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by product name...">
    <button type="submit">Search</button>
  </form>
  <?php
    $where = $search ? "WHERE p.product_name ILIKE :q" : "";
    $params = $search ? [':q' => "%$search%"] : [];
    $total = $pdo->prepare("SELECT COUNT(*) FROM products p $where");
    $total->execute($params);
    $total = (int)$total->fetchColumn();
    $stmt = $pdo->prepare("
      SELECT p.product_id, p.product_name, c.category_name, s.company_name AS supplier,
             p.unit_price, p.units_in_stock, p.discontinued
      FROM products p
      LEFT JOIN categories c ON c.category_id = p.category_id
      LEFT JOIN suppliers s ON s.supplier_id = p.supplier_id
      $where ORDER BY p.product_name LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
  ?>
  <table>
    <tr><th>#</th><th>Name</th><th>Category</th><th>Supplier</th><th>Price</th><th>In Stock</th><th>Status</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['product_id'] ?></td>
        <td><?= htmlspecialchars($r['product_name']) ?></td>
        <td><?= htmlspecialchars($r['category_name']) ?></td>
        <td><?= htmlspecialchars($r['supplier']) ?></td>
        <td>$<?= number_format($r['unit_price'], 2) ?></td>
        <td><?= $r['units_in_stock'] ?></td>
        <td><?= $r['discontinued'] ? '<span class="badge badge-red">Discontinued</span>' : '<span class="badge badge-green">Active</span>' ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?= paginate_links('products', $offset, $limit, $total, $search ? ['q' => $search] : []) ?>

<?php elseif ($page === 'orders'): ?>
  <h2>Orders</h2>
  <form class="search-bar" method="get">
    <input type="hidden" name="page" value="orders">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by customer ID or ship country...">
    <button type="submit">Search</button>
  </form>
  <?php
    $where = $search ? "WHERE o.customer_id ILIKE :q OR o.ship_country ILIKE :q2" : "";
    $params = $search ? [':q' => "%$search%", ':q2' => "%$search%"] : [];
    $total = $pdo->prepare("SELECT COUNT(*) FROM orders o $where");
    $total->execute($params);
    $total = (int)$total->fetchColumn();
    $stmt = $pdo->prepare("
      SELECT o.order_id, o.customer_id, o.order_date, o.shipped_date,
             o.ship_country, o.freight,
             CONCAT(e.first_name, ' ', e.last_name) AS employee
      FROM orders o
      LEFT JOIN employees e ON e.employee_id = o.employee_id
      $where ORDER BY o.order_date DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
  ?>
  <table>
    <tr><th>Order #</th><th>Customer</th><th>Employee</th><th>Order Date</th><th>Shipped</th><th>Country</th><th>Freight</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['order_id'] ?></td>
        <td><?= htmlspecialchars($r['customer_id']) ?></td>
        <td><?= htmlspecialchars($r['employee']) ?></td>
        <td><?= $r['order_date'] ?></td>
        <td><?= $r['shipped_date'] ?: '<span class="badge badge-red">Pending</span>' ?></td>
        <td><?= htmlspecialchars($r['ship_country']) ?></td>
        <td>$<?= number_format($r['freight'], 2) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?= paginate_links('orders', $offset, $limit, $total, $search ? ['q' => $search] : []) ?>

<?php elseif ($page === 'employees'): ?>
  <h2>Employees</h2>
  <?php
    $rows = $pdo->query("
      SELECT e.employee_id, e.first_name, e.last_name, e.title, e.city, e.country,
             CONCAT(m.first_name, ' ', m.last_name) AS manager
      FROM employees e
      LEFT JOIN employees m ON m.employee_id = e.reports_to
      ORDER BY e.last_name")->fetchAll();
  ?>
  <table>
    <tr><th>#</th><th>Name</th><th>Title</th><th>City</th><th>Country</th><th>Reports To</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['employee_id'] ?></td>
        <td><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></td>
        <td><?= htmlspecialchars($r['title']) ?></td>
        <td><?= htmlspecialchars($r['city']) ?></td>
        <td><?= htmlspecialchars($r['country']) ?></td>
        <td><?= htmlspecialchars($r['manager'] ?? 'N/A') ?></td></tr>
    <?php endforeach; ?>
  </table>

<?php elseif ($page === 'suppliers'): ?>
  <h2>Suppliers</h2>
  <form class="search-bar" method="get">
    <input type="hidden" name="page" value="suppliers">
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search by company or country...">
    <button type="submit">Search</button>
  </form>
  <?php
    $where = $search ? "WHERE company_name ILIKE :q OR country ILIKE :q2" : "";
    $params = $search ? [':q' => "%$search%", ':q2' => "%$search%"] : [];
    $total = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
    $total->execute($params);
    $total = (int)$total->fetchColumn();
    $stmt = $pdo->prepare("SELECT supplier_id, company_name, contact_name, city, country, phone FROM suppliers $where ORDER BY company_name LIMIT $limit OFFSET $offset");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
  ?>
  <table>
    <tr><th>#</th><th>Company</th><th>Contact</th><th>City</th><th>Country</th><th>Phone</th></tr>
    <?php foreach ($rows as $r): ?>
    <tr><td><?= $r['supplier_id'] ?></td>
        <td><?= htmlspecialchars($r['company_name']) ?></td>
        <td><?= htmlspecialchars($r['contact_name']) ?></td>
        <td><?= htmlspecialchars($r['city']) ?></td>
        <td><?= htmlspecialchars($r['country']) ?></td>
        <td><?= htmlspecialchars($r['phone']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?= paginate_links('suppliers', $offset, $limit, $total, $search ? ['q' => $search] : []) ?>

<?php endif; ?>
</div>
</body>
</html>
