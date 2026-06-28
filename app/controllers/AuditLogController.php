<?php
// app/controllers/AuditLogController.php

class AuditLogController {

    private AuditLogModel $logs;

    public function __construct() {
        $this->logs = new AuditLogModel();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function redirect(string $path): void {
        header('Location: ' . APP_URL . $path);
        exit;
    }

    private function renderView(string $view, array $data = []): void {
        extract($data);
        require ROOT . "/views/admin/audit-log/{$view}.php";
    }

    private function flash(string $key, string $msg): void {
        $_SESSION[$key] = $msg;
    }

    // ════════════════════════════════════════════════════════════════════════
    // INDEX  —  GET /admin/audit-log
    // ════════════════════════════════════════════════════════════════════════

    public function index(): void {
        auth_require(['admin']);

        $logs    = $this->logs->findAll();
        $actions = $this->logs->getDistinctActions();

        $this->renderView('index', [
            'pageTitle'  => 'Audit Log',
            'breadcrumb' => 'Admin · Audit Log',
            'logs'       => $logs,
            'actions'    => $actions,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // SHOW  —  GET /admin/audit-log/show?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function show(): void {
        auth_require(['admin']);

        $id  = (int) ($_GET['id'] ?? 0);
        $log = $this->logs->findById($id);

        if (!$log) {
            $this->flash('flash_error', 'Log entry not found.');
            $this->redirect('/admin/audit-log');
            return;
        }

        $this->renderView('show', [
            'pageTitle'  => 'Log Entry #' . $id,
            'breadcrumb' => 'Admin · Audit Log · Entry #' . $id,
            'log'        => $log,
        ]);
    }

    // ════════════════════════════════════════════════════════════════════════
    // DESTROY  —  POST /admin/audit-log/delete?id=x
    // ════════════════════════════════════════════════════════════════════════

    public function destroy(): void {
        auth_require(['admin']);

        $id  = (int) ($_GET['id'] ?? 0);
        $log = $this->logs->findById($id);

        if (!$log) {
            $this->flash('flash_error', 'Log entry not found.');
            $this->redirect('/admin/audit-log');
            return;
        }

        $this->logs->delete($id);
        log_action('deleted_audit_log', "id: {$id}, action: {$log['action']}", auth_user()['id']);

        $this->flash('flash_success', 'Log entry #' . $id . ' deleted successfully.');
        $this->redirect('/admin/audit-log');
    }

    // ════════════════════════════════════════════════════════════════════════
    // CLEAR  —  POST /admin/audit-log/clear
    // Deletes ALL log entries — admin only
    // ════════════════════════════════════════════════════════════════════════

    public function clear(): void {
        auth_require(['admin']);

        $count = $this->logs->clearAll();
        log_action('cleared_audit_log', "Removed {$count} entries", auth_user()['id']);

        $this->flash('flash_success', "Audit log cleared — {$count} entries removed.");
        $this->redirect('/admin/audit-log');
    }
}