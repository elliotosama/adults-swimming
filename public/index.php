<?php
declare(strict_types=1);
// public/index.php  —  Front Controller / Router
// ── 1. Define root path ───────────────────────────────────────────────────────
define('ROOT', dirname(__DIR__));
require ROOT . '/vendor/autoload.php';
require ROOT . '/includes/auth.php';
// ── 2. Load config ────────────────────────────────────────────────────────────
require ROOT . '/config/app.php';
require ROOT . '/config/database.php';




// require branch modeland controller


require ROOT . '/app/models/BranchModel.php';
require ROOT . '/app/controllers/BranchController.php';

require ROOT . '/app/controllers/AuthController.php';


require ROOT . '/app/controllers/EmployeeController.php';
require ROOT . '/app/models/EmployeeModel.php';


require ROOT . '/app/controllers/PriceController.php';
require ROOT . '/app/models/PriceModel.php';

require ROOT . '/app/controllers/ReceiptController.php';
require ROOT . '/app/models/ReceiptModel.php';

require ROOT . '/app/controllers/TransactionController.php';
require ROOT . '/app/models/TransactionModel.php';

require ROOT . '/app/controllers/DashboardController.php';


require ROOT . '/app/controllers/CaptainController.php';
require ROOT . '/app/models/CaptainModel.php';


require ROOT . '/app/controllers/CountryController.php';
require ROOT . '/app/models/CountryModel.php';



require ROOT . '/app/controllers/ClientController.php';
require ROOT . '/app/models/ClientModel.php';
// ── 5. Bootstrap session ─────────────────────────────────────────────────────
auth_start();

// ── 6. Parse the request ─────────────────────────────────────────────────────
$method = $_SERVER['REQUEST_METHOD'];                          // GET | POST
$uri    = strtok($_SERVER['REQUEST_URI'], '?');                // strip query string
$uri    = '/' . trim(rtrim($uri, '/'), '/');                   // normalise slashes
if ($uri === '/') $uri = '/login';                             // default route

// ── 7. Route table ───────────────────────────────────────────────────────────
//
//  Each entry: [ HTTP_METHOD, URI_pattern, controller_method_or_callable ]
//
//  Patterns starting with ~ are treated as regex.
//
$auth = new AuthController();
$branch = new BranchController();
$employee = new EmployeeController();
$price = new PriceController();
$receipt = new ReceiptController();
$transaction = new TransactionController();
$dashboard = new DashboardController();
$captain = new CaptainController();
$country = new CountryController();
$client = new ClientController();

$routes = [
    // Auth
    ['GET',   '/login',                fn() => $auth->showLogin()],
    ['POST',  '/login',                fn() => $auth->handleLogin()],
    ['GET',  '/logout',               fn() => $auth->handleLogout()],
    // end login



    // Users 



    ['GET', '/admin/dashboard',     fn() => $dashboard->index()],
    ['GET', '/branch_manager/dashboard',     fn() => $dashboard->index()],
    ['GET', '/customer_service/dashboard',     fn() => $dashboard->index()],
    ['GET', '/area_manager/dashboard',     fn() => $dashboard->index()],
    ['GET', '/admin/dashboard',     fn() => $dashboard->index()],




    ['GET',  '/admin/users',        fn ()     => $employee->index()],
    ['GET',  '/admin/user/show',        fn () => $employee->show()],
    ['GET',  '/admin/user/create',   fn ()    => $employee->create()],
    ['POST', '/admin/user/create',   fn ()    => $employee->store()],
    ['GET',  '/admin/user/edit',     fn ()    => $employee->edit()],
    ['POST', '/admin/user/edit',     fn ()    => $employee->update()],
    ['POST', '/admin/user/delete',   fn ()    => $employee->destroy()],
    ['GET', '/admin/users/search', fn () => $employee->ajaxSearch()],

    // Branches
    ['GET',  '/admin/branches',        fn () => $branch->index()],
    ['GET',  '/admin/branch/show',        fn () => $branch->show()],
    ['GET',  '/admin/branch/create',   fn () => $branch->create()],
    ['POST', '/admin/branch/create',   fn () => $branch->store()],
    ['GET',  '/admin/branch/edit',     fn () => $branch->edit()],
    ['POST', '/admin/branch/edit',     fn () => $branch->update()],
    ['POST', '/admin/branch/delete',   fn () => $branch->destroy()],
    ['GET', '/admin/branches/search', fn() => $branch->ajaxSearch()],

    // Prices
    ['GET',  '/admin/prices',        fn () => $price->index()],
    ['GET',  '/admin/price/show',        fn () => $price->show()],
    ['GET',  '/admin/price/create',   fn () => $price->create()],
    ['POST', '/admin/price/create',   fn () => $price->store()],
    ['GET',  '/admin/price/edit',     fn () => $price->edit()],
    ['POST', '/admin/price/edit',     fn () => $price->update()],
    ['POST', '/admin/price/delete',   fn () => $price->destroy()],
    ['GET', '/admin/prices/search', fn () => $price->ajaxSearch()],


    ['GET',  '/receipts',        fn () => $receipt->index()],
    ['GET',  '/receipt/show',        fn () => $receipt->show()],
    ['GET',  '/receipt/create',   fn () => $receipt->create()],
    ['POST', '/receipt/create',   fn () => $receipt->store()],
    ['GET',  '/receipt/edit',     fn () => $receipt->edit()],
    ['POST', '/receipt/edit',     fn () => $receipt->update()],
    ['POST', '/receipt/delete',   fn () => $receipt->destroy()],
    ['GET', '/receipt/export',   fn () => $receipt->export()],
    ['GET',  '/receipt/preview', fn () => $receipt->preview()],
    ['GET',  '/receipt/renew',   fn() => $receipt->renew()],
    ['POST', '/receipt/renew',   fn() => $receipt->storeRenewal()],
    ['GET',  '/receipt/payment', fn() => $receipt->paymentPage()],
    ['POST', '/receipt/payment', fn() => $receipt->storePayment()],
    ['GET',  '/receipt/refund',  fn() => $receipt->refundPage()],
    ['POST', '/receipt/refund',  fn() => $receipt->storeRefund()],
    ['GET', '/receipt/pdf',      fn() => $receipt->pdf()],
    ['GET',  '/receipt/manage', fn() => $receipt->manage()],
    ['GET', '/receipts/search-json', fn () => $receipt->searchJson()],
    ['POST', '/receipt/send-email', fn () => $receipt->sendEmail()],
    ['GET', '/receipt/payment-by-id',        fn() => $receipt->paymentByReceiptId()],
    ['POST', '/receipt/payment-by-id/store', fn() => $receipt->storePaymentByReceiptId()],
    ['GET', '/receipt/view-modal', $receipt->viewModal()],
    ['GET', '/receipt/edit-modal', $receipt->editModal()],
    ['GET', '/receipt/logs-modal', $receipt->logsModal()],

    ['GET',  '/transactions',        fn () => $transaction->index()],
    ['GET',  '/transaction/show',        fn () => $transaction->show()],
    ['GET',  '/transaction/create',   fn () => $transaction->create()],
    ['POST', '/transaction/create',   fn () => $transaction->store()],
    ['GET',  '/transaction/edit',     fn () => $transaction->edit()],
    ['POST', '/transaction/edit',     fn () => $transaction->update()],
    ['POST', '/transaction/delete',   fn () => $transaction->destroy()],
    ['POST', '/transaction/remove-evidence', fn() => $transaction->removeEvidence()],
    ['GET', '/transaction/search-json', fn() => $transaction->searchJson()],


    // captains
    ['GET',  '/admin/captains',        fn () => $captain->index()],
    ['GET',  '/admin/captains/show',        fn () => $captain->show()],
    ['GET',  '/admin/captains/create',   fn () => $captain->create()],
    ['POST', '/admin/captains/create',   fn () => $captain->store()],
    ['GET',  '/admin/captains/edit',     fn () => $captain->edit()],
    ['POST', '/admin/captains/edit',     fn () => $captain->update()],
    ['POST', '/admin/captains/delete',   fn () => $captain->destroy()],
    ['GET', '/admin/captains/search', fn() => $captain->ajaxSearch()],

    // countries
    ['GET',  '/country',        fn () => $country->index()],
    ['GET',  '/country/create',   fn () => $country->create()],
    ['POST', '/country/create',   fn () => $country->store()],
    ['GET',  '/country/edit',     fn () => $country->edit()],
    ['POST', '/country/edit',     fn () => $country->update()],
    ['GET', '/country/delete',   fn () => $country->destroy()],


    // clients 
    ['GET', '/clients', fn () => $client->index()],
    ['GET', '/client/create', fn () => $client->create()],
    ['POST', '/client/create', fn () => $client->store()],
    ['GET', '/client/show', fn () => $client->show()],
    ['GET', '/client/edit', fn () => $client->edit()],
    ['POST', '/client/edit', fn () => $client->update()],
    ['POST', '/client/delete', fn () => $client->destroy()],

    ['GET', '/receipt/refund-pdf', fn () => $receipt->refundPdf()],
    


];

// ── 8. Dispatch ───────────────────────────────────────────────────────────────
$matched = false;

foreach ($routes as [$routeMethod, $pattern, $handler]) {

    // Method guard
    if ($routeMethod !== $method) continue;

    // Regex route (prefix ~)
    if (str_starts_with($pattern, '~')) {
        $regex = substr($pattern, 1);
        if (preg_match('#' . $regex . '#', $uri, $matches)) {
            $matched = true;
            $handler($matches);
            break;
        }
        continue;
    }

    // Exact route
    if ($pattern === $uri) {
        $matched = true;
        $handler([]);
        break;
    }
}

// ── 9. 404 fallback ───────────────────────────────────────────────────────────
if (!$matched) {
    http_response_code(404);
    // Simple inline 404 — replace with a real view if desired
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>404 — Black Horse Courses</title>
      <style>
        body{font-family:sans-serif;display:flex;align-items:center;justify-content:center;
             min-height:100vh;background:#f7f5f0;color:#0f1624;text-align:center}
        h1{font-size:80px;margin:0;color:#c9a84c} p{color:#8a90a0}
        a{color:#0f1624;font-weight:600}
      </style>
    </head>
    <body>
      <div>
        <h1>404</h1>
        <p>Page not found.</p>
        <a href="/login">← Go to login</a>
      </div>
    </body>
    </html>
    HTML;
}

// ── Dashboard stub helper ─────────────────────────────────────────────────────
function dashboard_stub(string $role): void {
    auth_require();           // must be logged in
    $user = auth_user();
    $name = htmlspecialchars($user['username']);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>{$role} Dashboard — Black Horse Courses</title>
      <style>
        body{font-family:sans-serif;background:#f7f5f0;color:#0f1624;
             display:flex;align-items:center;justify-content:center;min-height:100vh;text-align:center}
        .card{background:#fff;border-radius:12px;padding:48px 56px;
              box-shadow:0 2px 12px rgba(0,0,0,.07)}
        h2{font-size:28px;margin-bottom:8px} p{color:#8a90a0;margin-bottom:24px}
        a{display:inline-block;padding:11px 28px;background:#0f1624;color:#fff;
          border-radius:8px;text-decoration:none;font-size:14px}
        a:hover{background:#1a2540}
      </style>
    </head>
    <body>
      <div class="card">
        <h2>👋 Welcome, {$name}</h2>
        <p>{$role} Dashboard — coming soon</p>
        <form method="POST" action="/logout" style="display:inline">
          <input type="hidden" name="csrf_token" value="<?= csrf_generate() ?>">
          <button type="submit" style="padding:11px 28px;background:#0f1624;color:#fff;
            border:none;border-radius:8px;cursor:pointer;font-size:14px">
            Log Out
          </button>
        </form>
      </div>
    </body>
    </html>
    HTML;
}