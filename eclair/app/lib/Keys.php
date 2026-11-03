<?php
// SPDX-License-Identifier: AGPL-3.0-only
// Copyright (c) 2026 Thierry Gustin
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/** Clés secrètes générées à l'installation (jamais affichées, jamais exportées dans les réglages). */
final class Keys
{
    public static function get(string $name): string
    {
        $keys = Store::read('keys', []);
        if (is_array($keys) && !empty($keys[$name])) {
            return (string) $keys[$name];
        }
        // Clé absente : créée sous verrou, après relecture (un autre processus vient peut-être de la créer). Un fichier de
        // clés présent mais illisible n'est jamais remplacé : de nouvelles clés rendraient indéchiffrable le coffre (clés
        // d'API, connexion Google Drive) et invalideraient sessions, appareils reconnus et codes de secours.
        return (string) Store::locked(static function () use ($name): string {
            $keys = Store::read('keys', null);
            if (!is_array($keys) && Store::exists('keys')) {
                throw new \RuntimeException('Fichier des clés data/keys.php illisible : il n’a pas été remplacé. Vérifie ses droits par FTP '
                    . '(ou supprime-le pour en créer de nouvelles : clés d’API et connexion Google Drive seront alors à saisir de nouveau).');
            }
            $keys = is_array($keys) ? $keys : [];
            return (string) (empty($keys[$name]) ? self::generate($keys) : $keys)[$name];
        });
    }

    public static function generate(array $keys = []): array
    {
        $keys += [
            'secret' => bin2hex(random_bytes(32)),
            'indexnow' => bin2hex(random_bytes(16)),
            'dummy' => Security::hashPassword(bin2hex(random_bytes(12))),
        ];
        Store::write('keys', $keys);
        return $keys;
    }

    /**
     * Empreinte factice, aux mêmes paramètres que les vrais mots de passe :
     * tester un e-mail inconnu prend autant de temps qu'un vrai (aucune énumération possible).
     */
    public static function dummy(): string
    {
        $hash = self::get('dummy');
        if (!Security::needsRehash($hash)) {
            return $hash;
        }
        return Store::locked(function (): string {
            $keys = Store::read('keys', []);
            $keys = is_array($keys) ? $keys : [];
            $keys['dummy'] = Security::hashPassword(bin2hex(random_bytes(12)));
            Store::write('keys', $keys);
            return $keys['dummy'];
        });
    }

    /** Signature HMAC avec la clé du site. */
    public static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::get('secret'));
    }
}
