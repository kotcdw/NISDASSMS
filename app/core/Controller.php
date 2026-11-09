<?php
declare(strict_types=1);

class Controller
{
    /** Render a view inside the layout (or bare when $layout is null). */
    public function view(string $__template, array $__vars = [], ?string $__layout = 'layouts/app'): void
    {
        $__file = BASE_PATH . '/app/views/' . $__template . '.php';
        if (!is_file($__file)) {
            throw new RuntimeException("View not found: $__template");
        }
        if ($__layout === 'layouts/app' && !Auth::check()) {
            $__layout = 'layouts/public';
        }
        extract($__vars, EXTR_SKIP);
        ob_start();
        require $__file;
        $content = ob_get_clean();
        if ($__layout === null) {
            echo $content;
            return;
        }
        if (Auth::check() && (Auth::role() !== 'super_admin' || ModuleSwitch::off())) { // Super Admin: only links to switched-off modules go
            ob_start();
            require BASE_PATH . '/app/views/' . $__layout . '.php';
            echo strip_forbidden((string) ob_get_clean()); // least privilege: show only what this role may use
            return;
        }
        require BASE_PATH . '/app/views/' . $__layout . '.php';
    }

    /** Validate input; on failure redirect back with errors and old input. */
    protected function validate(array $input, array $rules, string $backRoute): array
    {
        $errors = Validator::check($input, $rules);
        if ($errors) {
            with_old($input, $errors);
            flash('error', 'Please correct the highlighted fields.');
            redirect($backRoute);
        }
        return $input;
    }

    /** Pick only the given keys from POST, trimmed; empty strings become null. */
    protected function only(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $v = $_POST[$k] ?? null;
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') {
                    $v = null;
                }
            }
            $out[$k] = $v;
        }
        return $out;
    }

    /** Stop with a friendly message when a table from a newer update is missing ("Update database now" not clicked yet). */
    protected function needsTable(string $table): void
    {
        static $known = [];
        $known[$table] ??= (bool) db()->value('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?', [$table]);
        if (!$known[$table]) {
            flash('warning', 'This part needs the latest database update. Click "Update database now" in the yellow banner, then try again.');
            redirect('dashboard');
        }
    }

    protected function forbidden(): void
    {
        http_response_code(403);
        $this->view('errors/403', ['title' => 'Access denied']);
        exit;
    }

    /** The term chosen with ?term= (any term), else the current term. */
    protected function chosenTerm(): ?array
    {
        $id = (int) ($_GET['term'] ?? $_POST['term'] ?? 0);
        if ($id > 0 && ($t = db()->one('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id WHERE t.id = ?', [$id]))) {
            return $t;
        }
        return current_term();
    }

    /**
     * Every term (newest first) and the one picked: $want, else ?term=, else the current term, else the newest.
     * @return array{0:?array,1:array}
     */
    protected function termParam(?int $want = null): array
    {
        $terms = db()->all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC');
        $want ??= (int) ($_GET['term'] ?? (current_term()['id'] ?? 0));
        foreach ($terms as $t) {
            if ((int) $t['id'] === $want) {
                return [$t, $terms];
            }
        }
        return [$terms[0] ?? null, $terms];
    }

    protected function notFound(): void
    {
        http_response_code(404);
        $this->view('errors/404', ['title' => 'Not found']);
        exit;
    }
}
