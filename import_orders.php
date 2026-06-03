<?php
require_once 'db.php';

$csv_dir = __DIR__ . '/csv';

function import_csv(PDO $pdo, string $file, string $table, array $columns, callable $transform = null): int {
    $handle = fopen($file, 'r');
    if (!$handle) throw new Exception("Cannot open $file");
    fgetcsv($handle, 0, ',', '"', '\\');
    $count = 0;
    $cols = implode(', ', $columns);
    $placeholders = implode(', ', array_map(fn($c) => ":$c", $columns));
    $stmt = $pdo->prepare("INSERT INTO $table ($cols) VALUES ($placeholders) ON CONFLICT DO NOTHING");
    while (($row = fgetcsv($handle, 0, ',', '"', '\\')) !== false) {
        if ($transform) $row = $transform($row);
        if (!$row) continue;
        $params = [];
        foreach ($columns as $i => $col) {
            $val = $row[$i] ?? null;
            $params[":$col"] = ($val === '' || $val === null) ? null : $val;
        }
        $stmt->execute($params);
        $count++;
        if ($count % 100 === 0) {
            echo "  $count rows...\n";
            flush();
        }
    }
    fclose($handle);
    return $count;
}

try {
    // Orders - no transaction, commit every 100 rows
    echo "Importing Orders...\n";
    $n = import_csv($pdo, "$csv_dir/Orders.csv", 'orders',
        ['order_id','customer_id','employee_id','order_date','required_date','shipped_date',
         'ship_via','freight','ship_name','ship_address','ship_city','ship_region','ship_postal_code','ship_country']);
    echo "Orders: $n\n";

    echo "Importing Order Details...\n";
    $n = import_csv($pdo, "$csv_dir/Order Details.csv", 'order_details',
        ['order_id','product_id','unit_price','quantity','discount']);
    echo "OrderDetails: $n\n";

    echo "\nDone.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}