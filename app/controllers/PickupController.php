<?php
declare(strict_types=1);

/**
 * Gate pick-up check (School records → Gate pick-up), the parent's approval page from the SMS link,
 * and the parent portal page "Pick-up" (people allowed to collect, one-time codes, requests to answer).
 */
class PickupController extends Controller
{
    public function __construct()
    {
        if (!Pickups::ready()) {
            $this->needsTable('pickups');
        }
    }

    // ================================================================== gate (Security)

    public function gate(): void
    {
        Pickups::expireOld();
        $q = trim((string) ($_GET['q'] ?? ''));
        $pid = (int) ($_GET['pupil'] ?? 0);
        $found = $q !== '' ? Pickups::search($q) : [];
        if (!$pid && count($found) === 1) {
            $pid = (int) $found[0]['id'];
        }
        $child = $pid ? Pickups::pupil($pid) : null;
        $today = db()->all(
            "SELECT pu.*, p.first_name, p.last_name FROM pickups pu JOIN pupils p ON p.id = pu.pupil_id
             WHERE pu.created_at >= CURDATE() ORDER BY FIELD(pu.status, 'waiting', 'approved', 'refused', 'expired', 'released', 'cancelled'), pu.id DESC");
        $this->view('pickup/gate', ['title' => 'Gate pick-up', 'q' => $q, 'found' => $found, 'child' => $child,
            'guardians' => $child ? Pickups::guardians($pid) : [], 'people' => $child ? Pickups::people($pid) : [], 'today' => $today]);
    }

    /** Security records who is collecting; the child's own guardian is released at once, anyone else waits for the parent. */
    public function record(): void
    {
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        $child = Pickups::pupil($pid) ?? $this->notFound();
        $who = (string) ($_POST['who'] ?? '');
        $guardian = $person = null;
        if (str_starts_with($who, 'g')) {
            foreach (Pickups::guardians($pid) as $g) { if ('g' . $g['id'] === $who) $guardian = $g; }
        } elseif (str_starts_with($who, 'p')) {
            foreach (Pickups::people($pid) as $p) { if ('p' . $p['id'] === $who) $person = $p; }
        }
        $kind = $guardian ? 'guardian' : ($person ? 'listed' : 'other');
        $name = mb_substr(trim((string) ($guardian['name'] ?? $person['name'] ?? $_POST['collector_name'] ?? '')), 0, 120);
        $idType = (string) ($_POST['id_type'] ?? '');
        $idNo = mb_substr(trim((string) ($_POST['id_number'] ?? '')), 0, 40);
        $back = 'gate?pupil=' . $pid;
        $err = null;
        if ($name === '') {
            $err = 'Write the name of the person collecting the child.';
        } elseif (!isset(Pickups::ID_TYPES[$idType]) || $idNo === '') {
            $err = 'Check their ID card and write its type and number.';
        } elseif (($sig = Pickups::saveSignature((string) ($_POST['signature'] ?? ''))) === null) {
            $err = 'Ask the person to sign in the signature box.';
        }
        if ($err) {
            with_old($_POST);
            flash('error', $err);
            redirect($back);
        }
        [$photo, $upErr] = Upload::store($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'pickups');
        if ($upErr) {
            with_old($_POST);
            flash('error', 'Photo not saved: ' . $upErr);
            redirect($back);
        }
        if (!$photo && $kind !== 'guardian') {
            with_old($_POST);
            flash('error', 'Take a photo of the person collecting the child.');
            redirect($back);
        }
        $code = $kind !== 'guardian' ? Pickups::checkCode($pid, (string) ($_POST['code'] ?? '')) : null;
        if ($kind !== 'guardian' && trim((string) ($_POST['code'] ?? '')) !== '' && !$code) {
            with_old($_POST);
            flash('error', 'That pick-up code is wrong, already used or expired. Leave it empty to ask the parent by SMS.');
            redirect($back);
        }
        $id = db()->transaction(function (Database $d) use ($child, $guardian, $person, $kind, $name, $idType, $idNo, $sig, $photo, $code) {
            $status = $kind === 'guardian' ? 'approved' : ($code ? 'approved' : 'waiting');
            $id = $d->insert('pickups', [
                'ref_no' => Pickups::nextRef($d), 'pupil_id' => $child['id'], 'person_id' => $person['id'] ?? null, 'guardian_id' => $guardian['id'] ?? null,
                'collector_name' => $name,
                'phone' => mb_substr(preg_replace('/[^\d+ ]/', '', (string) ($guardian['phone'] ?? $person['phone'] ?? $_POST['phone'] ?? '')), 0, 20) ?: null,
                'relationship' => mb_substr(trim((string) ($guardian['relationship'] ?? $person['relationship'] ?? $_POST['relationship'] ?? '')), 0, 60) ?: null,
                'id_type' => $idType, 'id_number' => $idNo, 'signature' => $sig,
                'photo' => $photo['stored_name'] ?? null, 'photo_mime' => $photo['mime'] ?? null,
                'kind' => $kind, 'status' => $status,
                'approval_via' => $kind === 'guardian' ? 'guardian' : ($code ? 'code' : null), 'decided_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
                'token' => $status === 'waiting' ? bin2hex(random_bytes(12)) : null,
                'token_expires' => $status === 'waiting' ? date('Y-m-d H:i:s', time() + Pickups::ANSWER_MINUTES * 60) : null,
                'recorded_by' => Auth::id(),
            ]);
            if ($code) {
                $d->update('pickup_codes', ['used_at' => date('Y-m-d H:i:s'), 'used_pickup_id' => $id], 'id = ?', [$code['id']]);
            }
            return $id;
        });
        audit('pickup_recorded', 'pickups', $id, ['kind' => $kind]);
        $pu = db()->one('SELECT * FROM pickups WHERE id = ?', [$id]);
        if ($pu['status'] === 'waiting') {
            Pickups::requestApproval($pu);
            flash('info', 'The parent has been sent an SMS to approve. Keep the child inside until it says Approved.');
        }
        redirect('gate/' . $id);
    }

    public function show(int $id): void
    {
        $this->guard();
        Pickups::expireOld();
        $pu = $this->find($id);
        $this->view('pickup/show', ['title' => 'Pick-up ' . $pu['ref_no'], 'pu' => $pu, 'child' => Pickups::pupil((int) $pu['pupil_id'])]);
    }

    /** For the waiting screen: is there an answer yet? */
    public function status(int $id): void
    {
        $this->guard();
        Pickups::expireOld();
        $pu = $this->find($id);
        header('Content-Type: application/json');
        echo json_encode(['status' => $pu['status']]);
    }

    public function release(int $id): void
    {
        $pu = $this->find($id);
        if ($pu['status'] !== 'approved') {
            flash('error', 'This child may not be released: ' . (Pickups::STATUS[$pu['status']][0] ?? $pu['status']) . '.');
            redirect('gate/' . $id);
        }
        db()->update('pickups', ['status' => 'released', 'released_by' => Auth::id(), 'released_at' => date('Y-m-d H:i:s')], 'id = ?', [$id]);
        audit('pickup_released', 'pickups', $id);
        Pickups::notifyReleased($pu);
        $child = Pickups::pupil((int) $pu['pupil_id']);
        flash('success', $child['first_name'] . ' released to ' . $pu['collector_name'] . '. The parent has been told by SMS.');
        redirect('gate');
    }

    public function resend(int $id): void
    {
        $pu = $this->find($id);
        if ($pu['status'] === 'waiting') {
            Pickups::requestApproval($pu);
            flash('success', 'SMS sent to the parent again.');
        }
        redirect('gate/' . $id);
    }

    public function cancel(int $id): void
    {
        $pu = $this->find($id);
        if (in_array($pu['status'], ['waiting', 'approved'], true)) {
            db()->update('pickups', ['status' => 'cancelled'], 'id = ?', [$id]);
            audit('pickup_cancelled', 'pickups', $id);
            flash('success', 'Pick-up cancelled. The child stays in school.');
        }
        redirect('gate');
    }

    /** Office: approve or refuse after speaking to the parent on the phone. */
    public function officeDecide(int $id): void
    {
        $pu = $this->find($id);
        $note = trim((string) ($_POST['note'] ?? ''));
        if ($note === '') {
            flash('error', 'Write who you spoke to and on which number.');
            redirect('gate/' . $id);
        }
        $ok = Pickups::decide($pu, ($_POST['decision'] ?? '') === 'approve', 'office', Auth::id(), $note);
        flash($ok ? 'success' : 'error', $ok ? 'Answer recorded.' : 'This request has already been answered.');
        redirect('gate/' . $id);
    }

    /** Pick-up register for the office and the Head. */
    public function register(): void
    {
        Pickups::expireOld();
        $period = (string) ($_GET['period'] ?? 'week');
        $from = match ($period) { 'today' => date('Y-m-d'), 'month' => date('Y-m-01'), 'term' => current_term()['start_date'] ?? date('Y-m-d', strtotime('-90 days')), default => date('Y-m-d', strtotime('monday this week')) };
        $rows = db()->all(
            "SELECT pu.*, p.first_name, p.last_name, p.admission_no, u.name AS recorded_name FROM pickups pu JOIN pupils p ON p.id = pu.pupil_id
             LEFT JOIN users u ON u.id = pu.recorded_by WHERE pu.created_at >= ? ORDER BY pu.id DESC LIMIT 500", [$from]);
        $this->view('pickup/register', ['title' => 'Pick-up register', 'rows' => $rows, 'period' => $period]);
    }

    /** Office adds a person a parent has allowed (after speaking to the parent). */
    public function officeAddPerson(): void
    {
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        Pickups::pupil($pid) ?? $this->notFound();
        $id = $this->savePerson([$pid], 'office');
        if ($id) {
            flash('success', 'Added to the people allowed to collect this child.');
        }
        redirect('gate?pupil=' . $pid);
    }

    public function photo(int $id): void
    {
        $this->file($id, 'photo');
    }

    public function signature(int $id): void
    {
        $this->file($id, 'signature');
    }

    private function file(int $id, string $what): void
    {
        $this->guard();
        $pu = $this->find($id);
        $f = $what === 'signature' ? $pu['signature'] : ($what === 'photo' ? $pu['photo'] : null);
        if (!$f) {
            $this->notFound();
        }
        Upload::send($f, $what === 'signature' ? 'image/png' : (string) $pu['photo_mime'], $what);
    }

    public function personPhoto(int $id): void
    {
        $p = db()->one('SELECT * FROM pickup_people WHERE id = ?', [$id]) ?? $this->notFound();
        $mine = can('portal.parent') && db()->value('SELECT 1 FROM pickup_people_pupils WHERE person_id = ? AND pupil_id IN (' . (implode(',', Pickups::childIds((int) Auth::id())) ?: '0') . ')', [$id]);
        if (!$p['photo'] || (!can('pickup.gate') && !can('pickup.manage') && !$mine)) {
            $this->notFound();
        }
        Upload::send($p['photo'], (string) $p['photo_mime'], 'photo');
    }

    public function pupilPhoto(int $id): void
    {
        $p = Pickups::pupil($id) ?? $this->notFound();
        if (!$p['photo_path']) {
            $this->notFound();
        }
        Upload::send($p['photo_path'], str_ends_with($p['photo_path'], '.png') ? 'image/png' : 'image/jpeg', 'photo');
    }

    // ================================================================== parent's answer from the SMS link (no sign-in)

    public function answer(string $token): void
    {
        Pickups::expireOld();
        $pu = $this->byToken($token);
        $this->view('pickup/answer', ['title' => 'Pick-up request', 'pu' => $pu, 'child' => $pu ? Pickups::pupil((int) $pu['pupil_id']) : null], 'layouts/public');
    }

    public function answerSave(string $token): void
    {
        $pu = $this->byToken($token);
        if ($pu) {
            $ok = Pickups::decide($pu, ($_POST['decision'] ?? '') === 'approve', 'sms', Auth::id() ?: null);
            if (!$ok) {
                flash('error', 'This request has already been answered or has expired.');
            }
        }
        redirect('p/' . $token);
    }

    public function answerPhoto(string $token): void
    {
        $pu = $this->byToken($token);
        if (!$pu || !$pu['photo'] || $pu['status'] !== 'waiting') {
            $this->notFound();
        }
        Upload::send($pu['photo'], (string) $pu['photo_mime'], 'photo');
    }

    // ================================================================== parent portal

    public function mine(): void
    {
        Pickups::expireOld();
        $kids = Accounts::childrenOf((int) Auth::id());
        $ids = array_map(fn($k) => (int) $k['id'], $kids) ?: [0];
        $in = implode(',', $ids);
        $people = db()->all(
            "SELECT pp.*, GROUP_CONCAT(x.pupil_id) AS pupil_ids FROM pickup_people pp JOIN pickup_people_pupils x ON x.person_id = pp.id
             WHERE x.pupil_id IN ($in) AND pp.is_active = 1 GROUP BY pp.id ORDER BY pp.name");
        $requests = db()->all("SELECT pu.*, p.first_name FROM pickups pu JOIN pupils p ON p.id = pu.pupil_id WHERE pu.pupil_id IN ($in) ORDER BY pu.id DESC LIMIT 30");
        $codes = db()->all("SELECT c.*, p.first_name FROM pickup_codes c JOIN pupils p ON p.id = c.pupil_id WHERE c.pupil_id IN ($in) AND c.expires_at > NOW() - INTERVAL 1 DAY ORDER BY c.id DESC LIMIT 10");
        $this->view('pickup/mine', ['title' => 'Pick-up', 'kids' => $kids, 'people' => $people, 'requests' => $requests, 'codes' => $codes, 'newCode' => $_SESSION['pickup_code'] ?? null]);
        unset($_SESSION['pickup_code']);
    }

    public function mineAddPerson(): void
    {
        $mine = Pickups::childIds((int) Auth::id());
        $kids = array_values(array_intersect($mine, array_map('intval', (array) ($_POST['pupils'] ?? []))));
        if (!$kids) {
            with_old($_POST);
            flash('error', 'Tick the children this person may collect.');
            redirect('pickup');
        }
        if ($this->savePerson($kids, 'parent')) {
            flash('success', 'Saved. Security will see this person when they come to the gate; you will still get an SMS to approve each pick-up.');
        }
        redirect('pickup');
    }

    public function mineRemovePerson(int $id): void
    {
        $in = implode(',', Pickups::childIds((int) Auth::id())) ?: '0';
        if (db()->value("SELECT 1 FROM pickup_people_pupils WHERE person_id = ? AND pupil_id IN ($in)", [$id])) {
            db()->update('pickup_people', ['is_active' => 0], 'id = ?', [$id]);
            audit('pickup_person_removed', 'pickup_people', $id);
            flash('success', 'Removed. This person can no longer be chosen at the gate.');
        }
        redirect('pickup');
    }

    public function mineCode(): void
    {
        $pid = (int) ($_POST['pupil_id'] ?? 0);
        if (!in_array($pid, Pickups::childIds((int) Auth::id()), true)) {
            $this->notFound();
        }
        $hours = ($_POST['valid'] ?? '') === 'tomorrow' ? 36 : 12;
        $code = Pickups::makeCode($pid, mb_substr(trim((string) ($_POST['for_name'] ?? '')), 0, 120), $hours);
        audit('pickup_code_made', 'pickup_codes', null, ['pupil' => $pid]);
        $_SESSION['pickup_code'] = $code;
        redirect('pickup');
    }

    public function mineDecide(int $id): void
    {
        $pu = $this->find($id);
        if (!in_array((int) $pu['pupil_id'], Pickups::childIds((int) Auth::id()), true)) {
            $this->notFound();
        }
        $ok = Pickups::decide($pu, ($_POST['decision'] ?? '') === 'approve', 'portal', Auth::id());
        flash($ok ? 'success' : 'error', $ok ? 'Thank you. Security can see your answer now.' : 'This request has already been answered or has expired.');
        redirect('pickup');
    }

    // ================================================================== helpers

    /** Gate staff and the office only (routes that both may open are marked 'auth'). */
    private function guard(): void
    {
        if (!can('pickup.gate') && !can('pickup.manage')) {
            http_response_code(403);
            $this->view('errors/403', ['title' => 'Access denied']);
            exit;
        }
    }

    private function find(int $id): array
    {
        return db()->one('SELECT * FROM pickups WHERE id = ?', [$id]) ?? $this->notFound();
    }

    private function byToken(string $token): ?array
    {
        return preg_match('/^[a-f0-9]{24}$/', $token) ? db()->one('SELECT * FROM pickups WHERE token = ?', [$token]) : null;
    }

    private function savePerson(array $pupilIds, string $via): ?int
    {
        $name = mb_substr(trim((string) ($_POST['name'] ?? '')), 0, 120);
        $phone = mb_substr(preg_replace('/[^\d+ ]/', '', (string) ($_POST['phone'] ?? '')), 0, 20);
        if ($name === '' || $phone === '') {
            with_old($_POST);
            flash('error', 'Write the person\'s name and phone number.');
            return null;
        }
        [$photo, $upErr] = Upload::store($_FILES['photo'] ?? ['error' => UPLOAD_ERR_NO_FILE], 'pickups');
        if ($upErr) {
            with_old($_POST);
            flash('error', 'Photo not saved: ' . $upErr);
            return null;
        }
        $idType = (string) ($_POST['id_type'] ?? '');
        $id = db()->transaction(function (Database $d) use ($name, $phone, $photo, $idType, $via, $pupilIds) {
            $id = $d->insert('pickup_people', ['name' => $name, 'phone' => $phone, 'relationship' => mb_substr(trim((string) ($_POST['relationship'] ?? '')), 0, 60) ?: null,
                'id_type' => isset(Pickups::ID_TYPES[$idType]) ? $idType : null, 'id_number' => mb_substr(trim((string) ($_POST['id_number'] ?? '')), 0, 40) ?: null,
                'photo' => $photo['stored_name'] ?? null, 'photo_mime' => $photo['mime'] ?? null, 'added_by' => Auth::id(), 'added_via' => $via]);
            foreach ($pupilIds as $p) {
                $d->insert('pickup_people_pupils', ['person_id' => $id, 'pupil_id' => (int) $p]);
            }
            return $id;
        });
        audit('pickup_person_added', 'pickup_people', $id, ['via' => $via, 'pupils' => $pupilIds]);
        return $id;
    }
}
