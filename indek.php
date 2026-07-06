<?php
session_start();
error_reporting(0);
ini_set('display_errors', '0');
date_default_timezone_set('Asia/Jakarta');

$env_file = __DIR__ . '/.env';
$is_installed = file_exists($env_file);

// Jika belum di-install dan tidak ada request API, tampilkan halaman instalasi
if (!$is_installed && empty($_GET['api'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
        $db_host = $_POST['db_host'];
        $db_name = $_POST['db_name'];
        $db_user = $_POST['db_user'];
        $db_pass = $_POST['db_pass'];
        $owner_pin = $_POST['owner_pin'];
        
        try {
            $pdo = new PDO("mysql:host=$db_host;charset=utf8mb4", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db_name`");
            $pdo->exec("USE `$db_name`");
            
            // Auto Install Tables
            $sql = "
            CREATE TABLE IF NOT EXISTS `stores` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `name` VARCHAR(150) NOT NULL,
              `address` VARCHAR(255) DEFAULT NULL,
              `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS `products` (
              `id` VARCHAR(64) PRIMARY KEY,
              `store_id` INT DEFAULT 1,
              `name` VARCHAR(190) NOT NULL,
              `category` VARCHAR(100),
              `barcode` VARCHAR(100),
              `cost` DECIMAL(14,2) DEFAULT 0,
              `price` DECIMAL(14,2) DEFAULT 0,
              `stock` INT DEFAULT 0
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS `transactions` (
              `id` VARCHAR(64) PRIMARY KEY,
              `store_id` INT DEFAULT 1,
              `timestamp` BIGINT NOT NULL,
              `subtotal` DECIMAL(14,2) DEFAULT 0,
              `discount` DECIMAL(14,2) DEFAULT 0,
              `total` DECIMAL(14,2) DEFAULT 0,
              `paid` DECIMAL(14,2) DEFAULT 0,
              `change_amount` DECIMAL(14,2) DEFAULT 0,
              `payment_method` VARCHAR(20) DEFAULT 'tunai',
              `kasir_name` VARCHAR(100)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS `transaction_items` (
              `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
              `transaction_id` VARCHAR(64),
              `name` VARCHAR(190),
              `price` DECIMAL(14,2),
              `qty` INT,
              `cost` DECIMAL(14,2),
              `subtotal` DECIMAL(14,2)
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS `settings` (
              `id` TINYINT PRIMARY KEY DEFAULT 1,
              `shop_name` VARCHAR(190) DEFAULT 'Toko Utama',
              `print_paper_width` VARCHAR(10) DEFAULT '80'
            ) ENGINE=InnoDB;

            CREATE TABLE IF NOT EXISTS `owner_account` (
              `id` TINYINT PRIMARY KEY DEFAULT 1,
              `pin_hash` VARCHAR(128)
            ) ENGINE=InnoDB;
            ";
            $pdo->exec($sql);
            
            // Insert Default Store & Owner
            $pdo->exec("INSERT IGNORE INTO stores (id, name, address) VALUES (1, 'Toko Utama', 'Alamat Utama')");
            $pdo->exec("INSERT IGNORE INTO settings (id, shop_name) VALUES (1, 'Toko Utama')");
            
            $stmt = $pdo->prepare("INSERT INTO owner_account (id, pin_hash) VALUES (1, ?) ON DUPLICATE KEY UPDATE pin_hash=VALUES(pin_hash)");
            $stmt->execute([hash('sha256', $owner_pin)]);

            // Generate ENV file
            $api_key = 'KSRK_' . bin2hex(random_bytes(16));
            $env_content = "DB_HOST=$db_host\nDB_NAME=$db_name\nDB_USER=$db_user\nDB_PASS=$db_pass\nAPI_KEY=$api_key\n";
            file_put_contents($env_file, $env_content);
            
            header("Location: index.php?success=1");
            exit;
        } catch (PDOException $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="id">
    <head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Instalasi KasirKu</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <div class="bg-white p-8 rounded-xl shadow-xl w-full max-w-md">
            <h2 class="text-2xl font-bold text-green-700 mb-2 text-center">Aktivasi Owner KasirKu</h2>
            <p class="text-sm text-gray-500 mb-6 text-center">Konfigurasi Database & Keamanan Awal</p>
            <?php if(isset($error)) echo "<div class='bg-red-100 text-red-700 p-3 rounded mb-4'>$error</div>"; ?>
            <form method="POST">
                <input type="hidden" name="install" value="1">
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1">DB Host</label>
                    <input type="text" name="db_host" value="localhost" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1">DB Name</label>
                    <input type="text" name="db_name" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1">DB User</label>
                    <input type="text" name="db_user" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500" required>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-semibold mb-1">DB Password</label>
                    <input type="password" name="db_pass" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-semibold mb-1">PIN Owner (6 Digit)</label>
                    <input type="password" name="owner_pin" pattern="\d{6}" maxlength="6" class="w-full border p-2 rounded focus:ring-2 focus:ring-green-500" placeholder="123456" required>
                </div>
                <button type="submit" class="w-full bg-green-600 text-white font-bold py-3 rounded hover:bg-green-700">Install & Aktifkan</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if ($is_installed) {
    $env = parse_ini_file($env_file);
    define('DB_HOST', $env['DB_HOST']);
    define('DB_NAME', $env['DB_NAME']);
    define('DB_USER', $env['DB_USER']);
    define('DB_PASS', $env['DB_PASS']);
    define('API_KEY', $env['API_KEY']);
    
    function db() {
        static $pdo = null;
        if ($pdo !== null) return $pdo;
        $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        return $pdo;
    }

    // Handle API requests
    if (isset($_GET['api'])) {
        header('Content-Type: application/json');
        
        if (empty($_SERVER['HTTP_X_API_KEY']) || $_SERVER['HTTP_X_API_KEY'] !== API_KEY) {
            echo json_encode(['error' => 'Invalid API Key']); exit;
        }

        $pdo = db();
        $action = $_GET['api'];
        
        if ($action === 'pull') {
            $data = [];
            $data['products'] = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
            $data['stores'] = $pdo->query("SELECT * FROM stores")->fetchAll(PDO::FETCH_ASSOC);
            
            // Omset calculations
            $today = strtotime("today") * 1000;
            $data['omset_harian'] = $pdo->query("SELECT store_id, SUM(total) as omset FROM transactions WHERE timestamp >= $today GROUP BY store_id")->fetchAll(PDO::FETCH_ASSOC);
            $data['omset_bersih'] = $pdo->query("SELECT SUM(t.total - (SELECT SUM(qty * cost) FROM transaction_items WHERE transaction_id = t.id)) as profit FROM transactions t")->fetch(PDO::FETCH_ASSOC)['profit'];
            
            echo json_encode(['ok' => true, 'data' => $data]); exit;
        }
        
        if ($action === 'push') {
            $input = json_decode(file_get_contents('php://input'), true);
            $pdo->beginTransaction();
            try {
                if (isset($input['stores'])) {
                    $stmt = $pdo->prepare("INSERT INTO stores (id, name, address) VALUES (?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), address=VALUES(address)");
                    foreach($input['stores'] as $st) $stmt->execute([$st['id'], $st['name'], $st['address']]);
                }
                if (isset($input['products'])) {
                    $stmt = $pdo->prepare("INSERT INTO products (id, store_id, name, cost, price, stock) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), cost=VALUES(cost), price=VALUES(price), stock=VALUES(stock)");
                    foreach($input['products'] as $p) $stmt->execute([$p['id'], $p['store_id'] ?? 1, $p['name'], $p['cost'], $p['price'], $p['stock']]);
                }
                if (isset($input['transactions'])) {
                    $stmtT = $pdo->prepare("INSERT IGNORE INTO transactions (id, store_id, timestamp, subtotal, total, paid, change_amount) VALUES (?,?,?,?,?,?,?)");
                    $stmtI = $pdo->prepare("INSERT IGNORE INTO transaction_items (transaction_id, name, price, qty, cost, subtotal) VALUES (?,?,?,?,?,?)");
                    foreach($input['transactions'] as $t) {
                        $stmtT->execute([$t['id'], $t['store_id'] ?? 1, $t['timestamp'], $t['subtotal'], $t['total'], $t['paid'], $t['change']]);
                        foreach($t['items'] as $i) $stmtI->execute([$t['id'], $i['name'], $i['price'], $i['qty'], $i['cost'], $i['subtotal']]);
                    }
                }
                $pdo->commit();
                echo json_encode(['ok' => true]);
            } catch(Exception $e) {
                $pdo->rollBack();
                echo json_encode(['error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>KasirKu — Multi Toko</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
    body { font-family: 'Inter', sans-serif; background-color: #F4F2EA; color: #152420; padding-bottom: 70px; }
    .mono { font-family: 'Space Mono', monospace; }
    
    /* Header (Top Bar) */
    .topbar { background: linear-gradient(120deg, #083F0E 0%, #0A5C14 55%, #0F7A1D 100%); color: #EFE9D8; position: sticky; top: 0; z-index: 20; box-shadow: 0 2px 14px rgba(6,40,10,0.28); border-bottom: 2px solid rgba(220,178,92,0.35); }
    .mark { background: linear-gradient(155deg, #EFCB7A, #AD7F2C); color: #083F0E; box-shadow: 0 3px 8px rgba(0,0,0,0.25), inset 0 0 0 2px rgba(255,255,255,0.25); }
    
    /* Bottom Navigation */
    .bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; background: #ffffff; box-shadow: 0 -2px 10px rgba(0,0,0,0.1); display: flex; justify-content: space-around; padding: 10px 0; z-index: 50; }
    .nav-btn { display: flex; flex-direction: column; align-items: center; font-size: 12px; color: #6B6F68; font-weight: 600; background: transparent; border: none; cursor: pointer; transition: 0.2s; padding: 5px 15px; border-radius: 8px; }
    .nav-btn.active { color: #0A5C14; background: #E5EEF4; }
    .nav-btn svg { width: 24px; height: 24px; margin-bottom: 4px; }

    /* Product Cards */
    .pcard { background: #FFFFFF; border: 1px solid #E2D9C2; border-radius: 12px; padding: 13px; cursor: pointer; transition: transform .1s, box-shadow .1s; display: flex; flex-direction: column; gap: 5px; min-height: 112px; }
    .pcard:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(8,63,14,0.1); border-color: #1C9E2E; }
    
    /* Receipt Section */
    .receipt { background: #FBF7EE; border: 1px solid #E2D9C2; box-shadow: 0 2px 10px rgba(21,55,49,0.10); position: relative; }
    .receipt::after { content:""; display:block; height:14px; background: linear-gradient(-45deg, #F4F2EA 6px, transparent 0) 0 6px, linear-gradient(45deg, #F4F2EA 6px, #FBF7EE 0) 0 6px; background-size:14px 14px; background-repeat:repeat-x; }

    /* Hidden sections */
    .view-section { display: none; }
    .view-section.active { display: block; }
</style>
</head>
<body>

<header class="topbar px-5 py-3 flex items-center justify-between">
    <div class="flex items-center gap-3">
        <div class="mark w-10 h-10 rounded-xl flex items-center justify-center font-bold text-xl">K</div>
        <div>
            <h1 class="text-lg font-bold text-white tracking-wide truncate max-w-[200px]" id="headerShopName">KasirKu</h1>
            <div class="text-xs text-green-200" id="headerKasirName">Toko: Utama</div>
        </div>
    </div>
    <div class="text-right">
        <div class="mono text-xs text-green-200" id="clockDisplay">00:00:00</div>
        <div class="text-xs text-white bg-white/20 px-2 py-1 rounded mt-1 font-bold">Owner Mode</div>
    </div>
</header>

<main class="p-4 max-w-6xl mx-auto">
    <!-- View: KASIR -->
    <section id="view-kasir" class="view-section active">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="md:col-span-2">
                <div class="flex gap-2 mb-4">
                    <input type="text" id="searchProduct" placeholder="Cari produk / barcode..." class="w-full p-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-green-500 outline-none">
                </div>
                <div id="productGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                    <!-- Products injected by JS -->
                </div>
            </div>
            
            <div class="md:col-span-1">
                <div class="receipt rounded-t-xl p-4">
                    <div class="text-center border-b border-dashed border-gray-300 pb-3 mb-3">
                        <h2 class="font-bold text-lg">Keranjang</h2>
                        <div class="mono text-xs text-gray-500" id="cartDate"></div>
                    </div>
                    <div id="cartItems" class="max-h-60 overflow-y-auto mb-2 space-y-2">
                        <div class="text-center text-gray-400 text-sm py-4">Belum ada barang</div>
                    </div>
                    <div class="border-t border-dashed border-gray-300 pt-3 space-y-1">
                        <div class="flex justify-between text-sm"><span>Subtotal</span><span class="mono font-bold" id="cartSubtotal">Rp 0</span></div>
                        <div class="flex justify-between text-xl font-bold mt-2 text-green-700"><span>Total</span><span class="mono" id="cartTotal">Rp 0</span></div>
                    </div>
                    <button onclick="processPayment()" class="w-full bg-green-600 text-white font-bold py-3 rounded-xl mt-4 hover:bg-green-700 shadow-lg">BAYAR (Tunai)</button>
                </div>
            </div>
        </div>
    </section>

    <!-- View: PRODUK -->
    <section id="view-produk" class="view-section">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-2xl font-bold">Data Produk & Stok</h2>
            <button onclick="openProductModal()" class="bg-green-600 text-white px-4 py-2 rounded-lg font-bold">+ Tambah Produk</button>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-100 text-sm text-gray-600">
                        <th class="p-3 border-b">Nama Produk</th>
                        <th class="p-3 border-b">Harga Beli</th>
                        <th class="p-3 border-b">Harga Jual</th>
                        <th class="p-3 border-b">Stok</th>
                        <th class="p-3 border-b">Aksi</th>
                    </tr>
                </thead>
                <tbody id="productTableBody"></tbody>
            </table>
        </div>
    </section>

    <!-- View: PENGATURAN (Owner Only) -->
    <section id="view-pengaturan" class="view-section">
        <h2 class="text-2xl font-bold mb-4">Pengaturan & Laporan Owner</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Laporan Omset -->
            <div class="bg-white p-5 rounded-xl shadow border border-gray-200">
                <h3 class="font-bold text-lg mb-4 text-green-700 border-b pb-2">Laporan Omset</h3>
                <div class="mb-4">
                    <p class="text-sm text-gray-500">Omset Bersih Total (Seluruh Toko)</p>
                    <p class="text-3xl font-bold mono text-green-600" id="totalNetOmset">Rp 0</p>
                </div>
                <div>
                    <p class="text-sm font-semibold mb-2">Omset Harian per Toko (Hari Ini):</p>
                    <div id="omsetPerStore" class="space-y-2">
                        <!-- Injected via JS -->
                    </div>
                </div>
                <button onclick="syncData()" class="mt-4 w-full bg-blue-500 text-white px-4 py-2 rounded font-bold hover:bg-blue-600">Sinkronisasi Data Sekarang</button>
            </div>

            <!-- Manajemen Toko -->
            <div class="bg-white p-5 rounded-xl shadow border border-gray-200">
                <div class="flex justify-between items-center border-b pb-2 mb-4">
                    <h3 class="font-bold text-lg text-green-700">Manajemen Toko</h3>
                    <button onclick="addStore()" class="bg-green-100 text-green-700 px-3 py-1 rounded text-sm font-bold">+ Tambah</button>
                </div>
                <div id="storeList" class="space-y-3">
                    <!-- Injected via JS -->
                </div>
            </div>
        </div>
    </section>
</main>

<nav class="bottom-nav">
    <button class="nav-btn active" onclick="switchTab('kasir', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
        Kasir
    </button>
    <button class="nav-btn" onclick="switchTab('produk', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
        Produk & Stok
    </button>
    <button class="nav-btn" onclick="switchTab('pengaturan', this)">
        <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        Pengaturan
    </button>
</nav>

<!-- Modal Produk -->
<div id="productModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[100] p-4">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md p-6">
        <h3 class="text-xl font-bold mb-4" id="modalTitle">Tambah Produk</h3>
        <input type="hidden" id="formProdId">
        <div class="space-y-3">
            <div><label class="text-sm font-semibold">Nama Produk</label><input type="text" id="formProdName" class="w-full border p-2 rounded outline-none focus:border-green-500"></div>
            <div class="grid grid-cols-2 gap-3">
                <div><label class="text-sm font-semibold">Harga Beli (Modal)</label><input type="number" id="formProdCost" class="w-full border p-2 rounded outline-none focus:border-green-500"></div>
                <div><label class="text-sm font-semibold">Harga Jual</label><input type="number" id="formProdPrice" class="w-full border p-2 rounded outline-none focus:border-green-500"></div>
            </div>
            <div><label class="text-sm font-semibold">Stok Saat Ini</label><input type="number" id="formProdStock" class="w-full border p-2 rounded outline-none focus:border-green-500 font-bold bg-green-50"></div>
        </div>
        <div class="flex justify-end gap-3 mt-6">
            <button onclick="closeProductModal()" class="px-4 py-2 text-gray-500 font-semibold">Batal</button>
            <button onclick="saveProduct()" class="px-4 py-2 bg-green-600 text-white rounded font-bold shadow">Simpan</button>
        </div>
    </div>
</div>

<!-- Custom API Key (Injected by PHP logic dynamically if this was separate, but we simulate env here) -->
<script>
const API_KEY = "<?php echo API_KEY ?? ''; ?>";
let db = {
    products: [],
    transactions: [],
    stores: [{id: 1, name: 'Toko Utama', address: ''}],
    currentStore: 1
};
let cart = [];

const fmt = (n) => "Rp " + parseInt(n||0).toLocaleString("id-ID");
const generateId = () => Math.random().toString(36).substr(2, 9);

function initClock() {
    setInterval(() => {
        const d = new Date();
        document.getElementById('clockDisplay').innerText = d.toLocaleTimeString('id-ID');
        document.getElementById('cartDate').innerText = d.toLocaleDateString('id-ID');
    }, 1000);
}

function switchTab(tabId, btnElement) {
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    document.getElementById('view-' + tabId).classList.add('active');
    
    document.querySelectorAll('.nav-btn').forEach(btn => btn.classList.remove('active'));
    btnElement.classList.add('active');
    
    if(tabId === 'produk') renderProductTable();
    if(tabId === 'pengaturan') renderSettings();
}

// --- Data Fetch & Sync ---
async function syncData() {
    if(!API_KEY) return;
    try {
        // PULL
        const res = await fetch('?api=pull', { headers: {'X-Api-Key': API_KEY} });
        const json = await res.json();
        if(json.ok && json.data) {
            db.products = json.data.products || [];
            db.stores = json.data.stores.length ? json.data.stores : db.stores;
            
            renderProductGrid();
            renderSettings(json.data); // Pass omset data to render
        }
        
        // PUSH Local Changes (Simplified for this final version)
        await fetch('?api=push', {
            method: 'POST',
            headers: {'X-Api-Key': API_KEY, 'Content-Type': 'application/json'},
            body: JSON.stringify({
                products: db.products,
                stores: db.stores,
                transactions: db.transactions
            })
        });
        db.transactions = []; // clear local after push
        
    } catch (e) { console.error("Sync failed", e); }
}

// --- Kasir Functions ---
function renderProductGrid() {
    const grid = document.getElementById('productGrid');
    grid.innerHTML = db.products.map(p => `
        <div class="pcard" onclick="addToCart('${p.id}')">
            <div class="font-semibold text-sm leading-tight">${p.name}</div>
            <div class="text-xs text-gray-500 mt-1">Stok: <span class="${p.stock < 5 ? 'text-red-500 font-bold' : 'text-green-600'}">${p.stock}</span></div>
            <div class="mono text-green-700 font-bold mt-auto pt-2">${fmt(p.price)}</div>
        </div>
    `).join('');
}

function addToCart(id) {
    const prod = db.products.find(p => p.id === id);
    if(!prod) return;
    if(prod.stock <= 0) return alert("Stok habis!");
    
    const existing = cart.find(c => c.id === id);
    if(existing) {
        if(existing.qty >= prod.stock) return alert("Melebihi stok tersedia!");
        existing.qty++;
        existing.subtotal = existing.qty * existing.price;
    } else {
        cart.push({...prod, qty: 1, subtotal: prod.price});
    }
    renderCart();
}

function renderCart() {
    const wrap = document.getElementById('cartItems');
    if(cart.length === 0) {
        wrap.innerHTML = '<div class="text-center text-gray-400 text-sm py-4">Belum ada barang</div>';
        document.getElementById('cartSubtotal').innerText = 'Rp 0';
        document.getElementById('cartTotal').innerText = 'Rp 0';
        return;
    }
    
    wrap.innerHTML = cart.map((c, idx) => `
        <div class="flex justify-between items-center text-sm border-b border-gray-100 pb-2">
            <div>
                <div class="font-semibold">${c.name}</div>
                <div class="text-xs text-gray-500">${c.qty} x ${fmt(c.price)}</div>
            </div>
            <div class="flex items-center gap-3">
                <div class="mono font-bold">${fmt(c.subtotal)}</div>
                <button onclick="removeFromCart(${idx})" class="text-red-500 font-bold px-2 bg-red-50 rounded">X</button>
            </div>
        </div>
    `).join('');
    
    const total = cart.reduce((sum, item) => sum + item.subtotal, 0);
    document.getElementById('cartSubtotal').innerText = fmt(total);
    document.getElementById('cartTotal').innerText = fmt(total);
}

function removeFromCart(idx) {
    cart.splice(idx, 1);
    renderCart();
}

function processPayment() {
    if(cart.length === 0) return;
    const total = cart.reduce((sum, item) => sum + item.subtotal, 0);
    
    // Deduct Stock
    cart.forEach(c => {
        let p = db.products.find(x => x.id === c.id);
        if(p) p.stock -= c.qty;
    });

    // Save Transaction
    db.transactions.push({
        id: generateId(),
        store_id: db.currentStore,
        timestamp: Date.now(),
        subtotal: total,
        total: total,
        paid: total,
        change: 0,
        items: [...cart]
    });

    cart = [];
    renderCart();
    renderProductGrid();
    syncData(); // Auto sync
    alert("Pembayaran Berhasil!");
}

function renderProductTable() {
    const tbody = document.getElementById('productTableBody');
    tbody.innerHTML = db.products.map(p => `
        <tr class="border-b hover:bg-gray-50">
            <td class="p-3 font-semibold">${p.name}</td>
            <td class="p-3 mono">${fmt(p.cost)}</td>
            <td class="p-3 mono font-bold text-green-700">${fmt(p.price)}</td>
            <td class="p-3"><span class="px-2 py-1 rounded text-xs font-bold ${p.stock < 5 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'}">${p.stock}</span></td>
            <td class="p-3">
                <button onclick="editProduct('${p.id}')" class="bg-blue-100 text-blue-700 px-3 py-1 rounded text-xs font-bold">Edit</button>
            </td>
        </tr>
    `).join('');
}

function openProductModal(id = null) {
    const modal = document.getElementById('productModal');
    if(id) {
        const p = db.products.find(x => x.id === id);
        document.getElementById('modalTitle').innerText = 'Edit Produk & Stok';
        document.getElementById('formProdId').value = p.id;
        document.getElementById('formProdName').value = p.name;
        document.getElementById('formProdCost').value = p.cost;
        document.getElementById('formProdPrice').value = p.price;
        document.getElementById('formProdStock').value = p.stock;
    } else {
        document.getElementById('modalTitle').innerText = 'Tambah Produk';
        document.getElementById('formProdId').value = '';
        document.getElementById('formProdName').value = '';
        document.getElementById('formProdCost').value = '';
        document.getElementById('formProdPrice').value = '';
        document.getElementById('formProdStock').value = '0';
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeProductModal() {
    const modal = document.getElementById('productModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function saveProduct() {
    const id = document.getElementById('formProdId').value;
    const p = {
        name: document.getElementById('formProdName').value,
        cost: parseFloat(document.getElementById('formProdCost').value || 0),
        price: parseFloat(document.getElementById('formProdPrice').value || 0),
        stock: parseInt(document.getElementById('formProdStock').value || 0),
        store_id: db.currentStore
    };

    if(id) {
        const idx = db.products.findIndex(x => x.id === id);
        db.products[idx] = {...db.products[idx], ...p};
    } else {
        db.products.push({id: generateId(), ...p});
    }
    
    closeProductModal();
    renderProductTable();
    renderProductGrid();
    syncData();
}

function renderSettings(serverData = null) {
    // Render Stores
    document.getElementById('storeList').innerHTML = db.stores.map(s => `
        <div class="flex justify-between items-center p-3 bg-gray-50 border rounded-lg">
            <div>
                <div class="font-bold">${s.name}</div>
                <div class="text-xs text-gray-500">${s.address || 'Tanpa Alamat'}</div>
            </div>
            ${s.id === db.currentStore ? '<span class="bg-green-600 text-white text-xs px-2 py-1 rounded font-bold">Aktif</span>' : `<button onclick="setActiveStore(${s.id})" class="text-blue-600 text-sm font-semibold">Pilih</button>`}
        </div>
    `).join('');

    // Render Omset if data passed from server
    if(serverData) {
        document.getElementById('totalNetOmset').innerText = fmt(serverData.omset_bersih);
        
        const omsetHtml = serverData.omset_harian.map(oh => {
            let store = db.stores.find(s => s.id == oh.store_id);
            return `<div class="flex justify-between text-sm border-b border-gray-100 py-1">
                <span>${store ? store.name : 'Toko '+oh.store_id}</span>
                <span class="mono font-bold">${fmt(oh.omset)}</span>
            </div>`;
        }).join('');
        
        document.getElementById('omsetPerStore').innerHTML = omsetHtml || '<div class="text-xs text-gray-400">Belum ada transaksi hari ini</div>';
    }
}

function addStore() {
    const name = prompt("Masukkan Nama Toko Baru:");
    if(!name) return;
    const address = prompt("Masukkan Alamat Toko:");
    db.stores.push({id: Date.now(), name, address});
    renderSettings();
    syncData();
}

function setActiveStore(id) {
    db.currentStore = id;
    const store = db.stores.find(s => s.id === id);
    document.getElementById('headerKasirName').innerText = "Toko: " + store.name;
    renderSettings();
}

// Initialization
initClock();
syncData(); // Fetch initial data

</script>
</body>
</html>
