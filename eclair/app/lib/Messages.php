<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Messages reçus par le formulaire de contact (aucun message perdu, même si l'e-mail échoue).
 * Un index léger (data/inbox.php) donne les compteurs sans relire chaque message, et la boîte
 * est plafonnée : au-delà de 500 messages, les plus anciens (le spam d'abord) sont supprimés.
 */
final class Messages
{
    public const PER_PAGE = 20;
    private const MAX = 500;

    public static function add(array $msg): void
    {
        Store::locked(function () use ($msg): void {
            $index = self::index();
            $id = date('YmdHis') . '-' . substr(random_id(), 0, 6);
            Store::write('messages/' . $id, $msg + ['t' => time(), 'read' => false]);
            $index[$id] = ['r' => false, 's' => !empty($msg['suspect'])];
            if (count($index) > self::MAX) {
                ksort($index);
                $spam = array_keys(array_filter($index, fn (array $e): bool => $e['s']));
                foreach (array_slice(array_unique(array_merge($spam, array_keys($index))), 0, count($index) - self::MAX) as $old) {
                    Store::delete('messages/' . $old);
                    unset($index[$old]);
                }
            }
            Store::write('inbox', $index);
        });
    }

    /** Une page de messages, du plus récent au plus ancien. */
    public static function page(int $page): array
    {
        $ids = array_keys(self::index());
        rsort($ids);
        $out = [];
        foreach (array_slice($ids, ($page - 1) * self::PER_PAGE, self::PER_PAGE) as $id) {
            $msg = Store::read('messages/' . $id);
            if (is_array($msg)) {
                $out[$id] = $msg;
            }
        }
        return $out;
    }

    public static function count(): int
    {
        return count(self::index());
    }

    public static function unread(): int
    {
        return count(array_filter(self::index(), fn (array $e): bool => !$e['r'] && !$e['s']));
    }

    public static function markRead(string $id): void
    {
        if (!self::valid($id)) {
            return;
        }
        Store::locked(function () use ($id): void {
            $msg = Store::read('messages/' . $id);
            $index = self::index();
            if (is_array($msg) && isset($index[$id])) {
                $msg['read'] = true;
                $index[$id]['r'] = true;
                Store::write('messages/' . $id, $msg);
                Store::write('inbox', $index);
            }
        });
    }

    public static function delete(string $id): void
    {
        if (!self::valid($id)) {
            return;
        }
        Store::locked(function () use ($id): void {
            $index = self::index();
            Store::delete('messages/' . $id);
            unset($index[$id]);
            Store::write('inbox', $index);
        });
    }

    /** Index des messages ; reconstruit depuis les fichiers s'il manque. */
    private static function index(): array
    {
        $index = Store::read('inbox');
        if (is_array($index)) {
            return $index;
        }
        $index = [];
        foreach (Store::names('messages') as $name) {
            $msg = Store::read($name);
            if (is_array($msg)) {
                $index[basename($name)] = ['r' => !empty($msg['read']), 's' => !empty($msg['suspect'])];
            }
        }
        if (Settings::installed()) {
            Store::write('inbox', $index);
        }
        return $index;
    }

    private static function valid(string $id): bool
    {
        return preg_match('/^\d{14}-[a-f0-9]{6}$/', $id) === 1;
    }
}
