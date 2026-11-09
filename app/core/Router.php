<?php
declare(strict_types=1);

final class Router
{
    private array $routes = [];

    /** The router of this request (set in index.php), so pages can ask "may this person open that link?". */
    public static ?Router $current = null;

    public function __construct()
    {
        self::$current = $this;
    }

    /**
     * The permission a route needs: null = public, 'auth' = any signed-in person, a key, or false when no route matches.
     */
    public function permissionFor(string $method, string $route): string|null|false
    {
        $method = strtoupper($method);
        foreach ($this->routes as [$m, $regex, , $perm]) {
            if ($m === $method && preg_match($regex, $route)) {
                return $perm;
            }
        }
        return false;
    }

    /**
     * @param string      $pattern    e.g. "admission/{id}/stage"
     * @param string      $action     "Controller@method"
     * @param string|null $permission null = public, "auth" = any logged-in user, else a permission key
     */
    public function add(string $method, string $pattern, string $action, ?string $permission = 'auth'): void
    {
        // {token} = random public key (hex); any other {name} = numeric id
        $regex = trim($pattern, '/');
        $regex = str_replace('{token}', '(?P<token>[a-f0-9]{24})', $regex);
        $regex = str_replace('{slug}', '(?P<slug>[a-z][a-z0-9-]{0,40})', $regex); // short text key, e.g. a module name
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[0-9]+)', $regex) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $action, $permission];
    }

    public function get(string $p, string $a, ?string $perm = 'auth'): void
    {
        $this->add('GET', $p, $a, $perm);
    }

    public function post(string $p, string $a, ?string $perm = 'auth'): void
    {
        $this->add('POST', $p, $a, $perm);
    }

    /** Pages where the password must have been confirmed in the last few minutes (step-up). */
    private const STEP_UP = ['security', 'users', 'backups', 'setup/privacy', 'practice'];

    private static function needsStepUp(string $route): bool
    {
        foreach (self::STEP_UP as $p) {
            if ($route === $p || str_starts_with($route, $p . '/')) {
                return true;
            }
        }
        return false;
    }

    public function dispatch(string $method, string $route): void
    {
        $method = strtoupper($method);
        $allowed = false;

        foreach ($this->routes as [$m, $regex, $action, $perm]) {
            if (!preg_match($regex, $route, $matches)) {
                continue;
            }
            $allowed = true;
            if ($m !== $method) {
                continue;
            }

            // "webhook" routes are called server-to-server by payment gateways: public, no CSRF token
            // (they are authenticated by signature / by re-checking the payment with the gateway)
            if ($method === 'POST' && $perm !== 'webhook') {
                Csrf::verifyOrFail();
                unset($_SESSION['attention']); // something may have changed: rebuild the top-bar alerts on the next page
            }

            if ($perm !== null && $perm !== 'webhook') {
                if (!Auth::check()) {
                    $_SESSION['_intended'] = $route;
                    redirect('login');
                }
                // Signed-in pages must not be kept by the browser or a shared computer's cache
                header('Cache-Control: no-store, private');
                if ((int) (Auth::user()['must_change_password'] ?? 0) && !in_array($route, ['account/password', 'logout'], true)) {
                    flash('warning', !empty($_SESSION['pw_expired']) ? 'Your password has expired. Please choose a new one.' : 'Please set a new password before continuing.');
                    redirect('account/password');
                }
                // Roles that require 2-step sign-in: set it up before anything else
                if ((!empty($_SESSION['must_enrol_2fa']) || Security::mustEnrol(Auth::user())) && !in_array($route, ['account/2fa', 'logout', 'setup/migrate'], true)) {
                    flash('warning', 'Your role requires 2-step sign-in. Set it up now with an authenticator app on your phone.');
                    redirect('account/2fa');
                }
                if ($perm !== 'auth' && !Auth::can($perm)) {
                    $key = 'denied:' . $route;
                    if (empty($_SESSION[$key])) { // log once per page per session
                        $_SESSION[$key] = 1;
                        audit('access_denied', 'routes', null, ['route' => $method . ' ' . $route, 'permission' => $perm, 'ip' => Security::clientIp()]);
                    }
                    http_response_code(403);
                    (new Controller())->view('errors/403', ['title' => 'Access denied']);
                    return;
                }
                // Read-only roles (External Auditor): every page can be read, nothing can be saved
                if ($method === 'POST' && Auth::readOnly() && !in_array($route, ['logout', 'account/password', 'account/2fa', 'account/confirm', 'export/table'], true) && !str_starts_with($route, 'account/')) {
                    audit('read_only_blocked', 'routes', null, ['route' => $route]);
                    http_response_code(403);
                    (new Controller())->view('errors/403', ['title' => 'Read-only access', 'readOnly' => true]);
                    return;
                }
                // Risky administration: confirm the password again if it was not typed in the last few minutes
                if (self::needsStepUp($route) && !Auth::recentlyConfirmed() && !is_demo()) {
                    if ($method === 'GET') {
                        $q = $_GET ? '?' . http_build_query($_GET) : '';
                        Auth::requireConfirmed($route . $q);
                    }
                    flash('warning', 'For safety, confirm your password again, then repeat the action.');
                    Auth::requireConfirmed(explode('/', $route)[0]);
                }
            }

            // Modules on/off (School setup): pages of a switched-off module are closed to everyone.
            // Payment-gateway callbacks ("webhook") still arrive so money already paid is never lost.
            if ($perm !== 'webhook' && ($off = ModuleSwitch::blocked($route)) !== null) {
                http_response_code(403);
                (new Controller())->view('errors/module_off', ['title' => 'Module switched off', 'module' => ModuleSwitch::label($off)]);
                return;
            }

            [$class, $fn] = explode('@', $action);
            $params = [];
            foreach ($matches as $k => $v) {
                if (is_string($k)) {
                    $params[] = in_array($k, ['token', 'slug'], true) ? $v : (int) $v;
                }
            }
            $controller = new $class();
            $controller->$fn(...$params);
            return;
        }

        http_response_code($allowed ? 405 : 404);
        (new Controller())->view('errors/404', ['title' => 'Page not found']);
    }
}
