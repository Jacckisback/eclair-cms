<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Comptes (data/users.php). Rôles : « admin » (tout) et « editor » (contenus et médias). */
final class Users
{
    public const ROLES = ['admin', 'editor'];

    public static function all(): array
    {
        $users = Store::read('users', []);
        return is_array($users) ? $users : [];
    }

    public static function find(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::all() as $user) {
            if (mb_strtolower((string) $user['email']) === $email) {
                return $user;
            }
        }
        return null;
    }

    public static function findBySlug(string $slug): ?array
    {
        foreach (self::all() as $user) {
            if (($user['slug'] ?? '') === $slug) {
                return $user;
            }
        }
        return null;
    }

    /** Crée un compte (identifiant généré). */
    public static function create(array $user): array
    {
        return Store::locked(function () use ($user): array {
            $users = Store::read('users', []);
            $users = is_array($users) ? $users : [];
            // Les valeurs fournies l'emportent sur les valeurs par défaut (l'identifiant est toujours généré).
            $user = ['id' => random_id()] + array_diff_key($user, ['id' => true]) + [
                'role' => 'editor',
                'ver' => 1,
                'created' => time(),
                'bio' => '',
                'job' => '',
                'avatar' => '',
                'links' => [],
                'lang' => 'fr',
                'must_change' => false,
                'totp' => ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []],
            ];
            $user['slug'] = self::uniqueSlug(slugify((string) $user['name']) ?: 'auteur', $user['id'], $users);
            $users[$user['id']] = $user;
            Store::write('users', $users);
            return $user;
        });
    }

    /**
     * Modifie un compte à partir de sa version enregistrée la plus récente, sous verrou :
     * aucune copie périmée ne peut écraser un changement concurrent (mot de passe, révocation, 2FA…).
     * Le rappel reçoit le compte et renvoie le compte modifié, ou null pour ne rien changer.
     * Renvoie le compte tel qu'enregistré, ou null s'il n'existe plus.
     */
    public static function update(string $id, callable $change): ?array
    {
        return Store::locked(function () use ($id, $change): ?array {
            $users = Store::read('users', []);
            if (!is_array($users) || !isset($users[$id])) {
                return null;
            }
            $user = $change($users[$id]);
            if (!is_array($user)) {
                return $users[$id];
            }
            $user['id'] = $id;
            $user['slug'] = self::uniqueSlug(slugify((string) $user['name']) ?: 'auteur', $id, $users);
            $users[$id] = $user;
            Store::write('users', $users);
            return $user;
        });
    }

    public static function delete(string $id): void
    {
        Store::locked(function () use ($id): void {
            $users = Store::read('users', []);
            unset($users[$id]);
            Store::write('users', $users);
        });
    }

    public static function admins(): array
    {
        return array_filter(self::all(), fn (array $u): bool => $u['role'] === 'admin');
    }

    /** Données publiques d'un auteur (page auteur, schéma Person, boîte E-E-A-T). */
    public static function profile(?array $user): ?array
    {
        if (!$user) {
            return null;
        }
        return [
            'id' => $user['id'],
            'name' => (string) $user['name'],
            'slug' => (string) ($user['slug'] ?? ''),
            'bio' => (string) ($user['bio'] ?? ''),
            'job' => (string) ($user['job'] ?? ''),
            'avatar' => (string) ($user['avatar'] ?? ''),
            'links' => array_values(array_filter(array_map('strval', (array) ($user['links'] ?? [])))),
        ];
    }

    private static function uniqueSlug(string $slug, string $id, array $users): string
    {
        $candidate = $slug;
        $n = 2;
        while (true) {
            $taken = false;
            foreach ($users as $u) {
                if ($u['id'] !== $id && ($u['slug'] ?? '') === $candidate) {
                    $taken = true;
                    break;
                }
            }
            if (!$taken) {
                return $candidate;
            }
            $candidate = $slug . '-' . $n++;
        }
    }
}
