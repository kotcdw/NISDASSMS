<?php
declare(strict_types=1);

class DashboardController extends Controller
{
    /** "/" — send each person to the right starting page. */
    public function home(): void
    {
        if (!Auth::check()) {
            redirect('login');
        }
        $role = Auth::role();
        redirect(can('staff') ? 'dashboard' : ($role === 'parent' ? 'portal' : ($role === 'pupil' ? 'learn' : (can('pickup.gate') ? 'gate' : 'calendar'))));
    }

    /** The Analytic Dashboard: one page for every staff role, cards chosen by permission and Modules on/off. */
    public function index(): void
    {
        [$terms, $term] = $this->term();
        Attention::items(true); // the Dashboard always shows fresh top-bar alerts
        $growthOk = (bool) Analytics::growthCards();
        $tab = $this->tab();
        $this->view('dashboard/analytics', ['title' => $tab === 'growth' ? 'School growth' : 'Analytics', 'terms' => $terms, 'term' => $term, 'tab' => $tab, 'growthOk' => $growthOk,
            'cards' => Analytics::render($term, $tab), 'asOf' => date('H:i')]);
    }

    /** Live refresh (every 60 s): each card's HTML, rebuilt with the same permission and module checks. */
    public function live(): void
    {
        [, $term] = $this->term();
        $cards = Analytics::render($term, $this->tab());
        if (Auth::role() !== 'super_admin' || ModuleSwitch::off()) {
            $cards = array_map('strip_forbidden', $cards); // same links and buttons as the page itself
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['asOf' => date('H:i'), 'cards' => $cards], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** One widget on its own: printable page, or its table as CSV (Excel). */
    public function widget(string $slug): void
    {
        $key = str_replace('-', '_', $slug);
        $allowed = Widgets::allowed();
        if (!isset($allowed[$key]) || $allowed[$key][2] !== 'chart') {
            $this->notFound();
            return;
        }
        [, $term] = $this->term();
        $w = Widgets::build($key, $term);
        $title = $allowed[$key][0];
        if (isset($_GET['export'])) {
            $out = csv_start(trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($title . ' ' . ($term['year_name'] ?? '') . ' ' . ($term['name'] ?? ''))), '-') . '.csv', $w['cols'] ?? []);
            foreach ($w['rows'] ?? [] as $r) {
                csv_row($out, $r);
            }
                        audit('dashboard_exported', 'terms', (int) ($term['id'] ?? 0), ['widget' => $key]);
        csv_end($out);
        }
        $this->view('dashboard/print', ['title' => $title, 'w' => $w, 'term' => $term, 'docTitle' => mb_strtoupper($title)], 'layouts/print');
    }

    /** [all terms, chosen term] — the current term unless ?term= picks another. */
    /** Dashboard tab: "today" (default) or "growth" (School growth, for those who may see it). */
    private function tab(): string
    {
        return ($_GET['tab'] ?? '') === 'growth' && Analytics::growthCards() ? 'growth' : 'today';
    }

    private function term(): array
    {
        $terms = db()->all('SELECT t.*, y.name AS year_name FROM terms t JOIN academic_years y ON y.id = t.academic_year_id ORDER BY t.start_date DESC');
        $tid = (int) ($_GET['term'] ?? 0) ?: (int) (current_term()['id'] ?? ($terms[0]['id'] ?? 0));
        foreach ($terms as $t) {
            if ((int) $t['id'] === $tid) {
                return [$terms, $t];
            }
        }
        return [$terms, null];
    }
}
