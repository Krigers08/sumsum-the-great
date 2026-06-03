<?php
// import.php - Run this once to load CSV data into Postgres
// Usage: php import.php  (or visit /import.php in browser, protected by secret)

$secret = getenv('IMPORT_SECRET') ?: 'changeme';
if (php_sapi_name() !== 'cli') {
    $provided = $_GET['secret'] ?? '';
    if ($provided !== $secret) {
        http_response_code(403);
        die('Forbidden');
    }
}

require_once 'db.php';

function import_csv(PDO $pdo, string $file, string $table, array $columns, callable $transform = null): int {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception("Cannot open $file");
    fgetcsv($handle); // skip header
    $count = 0;
    $cols = implode(', ', $columns);
    $placeholders = implode(', ', array_map(fn($c) => ":$c", $columns));
    $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT DO NOTHING");
    while (($row = fgetcsv($handle)) !== false) {
        if ($transform) $row = $transform($row);
        if (!$row) continue;
        $params = [];
        foreach ($columns as $i => $col) {
            $val = $row[$i] ?? null;
            $params[":$col"] = ($val === '' || $val === null) ? null : $val;
        }
        $stmt->execute($params);
        $count++;
        if ($count % 50 === 0) {
            echo "  $table: $count rows\n";
            flush();
        }
    }
    fclose($handle);
    return $count;
}

$csv_dir = __DIR__ . '/csv';

try {

    // Regions
    $n = import_csv($pdo, "$csv_dir/Regions.csv", 'regions', ['region_id', 'region_description']);
    echo "Regions: $n\n";

    // Territories
    $n = import_csv($pdo, "$csv_dir/Territories.csv", 'territories', ['territory_id', 'territory_description', 'region_id']);
    echo "Territories: $n\n";

    // Categories
    $n = import_csv($pdo, "$csv_dir/Categories.csv", 'categories', ['category_id', 'category_name', 'description']);
    echo "Categories: $n\n";

    // Suppliers
    $n = import_csv($pdo, "$csv_dir/Suppliers.csv", 'suppliers',
        ['supplier_id','company_name','contact_name','contact_title','address','city','region','postal_code','country','phone','fax','home_page']);
    echo "Suppliers: $n\n";

    // Customers
    $n = import_csv($pdo, "$csv_dir/Customers.csv", 'customers',
        ['customer_id','company_name','contact_name','contact_title','address','city','region','postal_code','country','phone','fax']);
    echo "Customers: $n\n";

    // Employees - insert without reports_to first to avoid self-referencing FK violation
    $n = import_csv($pdo, "$csv_dir/Employees.csv", 'employees',
        ['employee_id','last_name','first_name','title','title_of_courtesy','birth_date','hire_date',
         'address','city','region','postal_code','country','home_phone','extension','notes','photo_path'],
        function($row) {
            // drop reports_to (index 15), keep photo_path (index 16)
            return array_merge(array_slice($row, 0, 15), [array_slice($row, 16)[0] ?? null]);
        }
    );
    // Now update reports_to separately
    $handle = fopen("$csv_dir/Employees.csv", 'r');
    fgetcsv($handle); // skip header
    $upd = $pdo->prepare("UPDATE employees SET reports_to = :rt WHERE employee_id = :id");
    while (($row = fgetcsv($handle)) !== false) {
        $rt = (isset($row[15]) && $row[15] !== '') ? (int)$row[15] : null;
        $upd->execute([':rt' => $rt, ':id' => (int)$row[0]]);
    }
    fclose($handle);
    echo "Employees: $n\n";

    // Employee Territories
    $n = import_csv($pdo, "$csv_dir/EmployeeTerritories.csv", 'employee_territories', ['employee_id', 'territory_id']);
    echo "EmployeeTerritories: $n\n";

    // Shippers
    $n = import_csv($pdo, "$csv_dir/Shippers.csv", 'shippers', ['shipper_id', 'company_name', 'phone']);
    echo "Shippers: $n\n";

    // Products
    $n = import_csv($pdo, "$csv_dir/Products.csv", 'products',
        ['product_id','product_name','supplier_id','category_id','quantity_per_unit','unit_price',
         'units_in_stock','units_on_order','reorder_level','discontinued']);
    echo "Products: $n\n";

    // Orders
    $n = import_csv($pdo, "$csv_dir/Orders.csv", 'orders',
        ['order_id','customer_id','employee_id','order_date','required_date','shipped_date',
         'ship_via','freight','ship_name','ship_address','ship_city','ship_region','ship_postal_code','ship_country']);
    echo "Orders: $n\n";

    // Order Details
    $n = import_csv($pdo, "$csv_dir/Order Details.csv", 'order_details',
        ['order_id','product_id','unit_price','quantity','discount']);
    echo "OrderDetails: $n\n";

    echo "\nImport complete.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
