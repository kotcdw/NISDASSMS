<?php
declare(strict_types=1);

/** Payment plans on a bill (Bursar). */
class InstalmentController extends Controller
{
    public function save(int $id): void
    {
        $inv = db()->one('SELECT * FROM invoices WHERE id = ?', [$id]) ?? $this->notFound();
        if (!empty($_POST['remove'])) {
            db()->run('DELETE FROM invoice_instalments WHERE invoice_id = ?', [$id]);
            audit('instalments_removed', 'invoices', $id);
            flash('success', 'Payment plan removed.');
            redirect('fees/invoices/' . $id);
        }
        $parts = [];
        foreach ((array) ($_POST['due'] ?? []) as $k => $date) {
            $amt = trim(str_replace(',', '', (string) ($_POST['amount'][$k] ?? '')));
            if (trim((string) $date) === '' && $amt === '') {
                continue;
            }
            $parts[] = [trim((string) $date), is_numeric($amt) ? round((float) $amt, 2) : 0.0];
        }
        if ($err = Instalments::save($inv, $parts)) {
            flash('error', $err);
            redirect('fees/invoices/' . $id);
        }
        audit('instalments_saved', 'invoices', $id, ['parts' => $parts]);
        flash('success', 'Payment plan saved: ' . count($parts) . ' instalments. The parent sees it on the bill and gets a reminder before each date.');
        redirect('fees/invoices/' . $id);
    }
}
