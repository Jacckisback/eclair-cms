<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Messages reçus par le formulaire de contact (aucun message perdu, même si l'e-mail échoue). */
final class Messages
{
    public static function add(array $msg): void
    {
        Store::write('messages/' . date('YmdHis') . '-' . substr(random_id(), 0, 6), $msg + ['t' => time(), 'read' => false]);
    }

    public static function all(): array
    {
        $out = [];
        foreach (Store::names('messages') as $name) {
            $msg = Store::read($name);
            if (is_array($msg)) {
                $out[basename($name)] = $msg;
            }
        }
        krsort($out);
        return $out;
    }

    public static function unread(): int
    {
        return count(array_filter(self::all(), fn ($m) => empty($m['read']) && empty($m['suspect'])));
    }

    public static function markRead(string $id): void
    {
        if (preg_match('/^\d{14}-[a-f0-9]{6}$/', $id) && ($msg = Store::read('messages/' . $id))) {
            $msg['read'] = true;
            Store::write('messages/' . $id, $msg);
        }
    }

    public static function delete(string $id): void
    {
        if (preg_match('/^\d{14}-[a-f0-9]{6}$/', $id)) {
            Store::delete('messages/' . $id);
        }
    }
}
