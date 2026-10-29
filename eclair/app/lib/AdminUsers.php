<?php
declare(strict_types=1);

namespace Eclair;

defined('ECLAIR') || exit;

/**
 * Comptes : profil personnel (mot de passe, double authentification) et gestion de l'équipe.
 * Chaque modification part de la version enregistrée la plus récente du compte (Users::update).
 */
final class AdminUsers
{
    private const NO_TOTP = ['on' => false, 'secret' => '', 'last' => 0, 'recovery' => []];

    public static function list(array $user): never
    {
        Admin::view('users', ['users' => Users::all()], t('Utilisateurs'));
    }

    public static function create(array $user): never
    {
        if (!Auth::confirmed($user)) {
            Admin::back('users', t('Mot de passe incorrect.'), 'bad');
        }
        $email = Sanitize::email(Req::input('email'));
        $name = Sanitize::text(Req::input('name'), 80);
        if ($name === '' || $email === '') {
            Admin::back('users', t('Indique un nom et une adresse e-mail valide.'), 'bad');
        }
        if (Users::findByEmail($email)) {
            Admin::back('users', t('Cette adresse e-mail est déjà utilisée.'), 'bad');
        }
        $password = Security::randomPassword();
        Users::create([
            'name' => $name,
            'email' => $email,
            'pass' => Security::hashPassword($password),
            'role' => Req::input('role') === 'admin' ? 'admin' : 'editor',
            'lang' => I18n::lang(),
            'must_change' => true,
        ]);
        Log::security('user_created', ['target' => $email]);
        Session::flash('secret', t('Compte créé. Transmets ce mot de passe provisoire à {name} (il ne sera plus affiché) : {pass}', ['name' => $name, 'pass' => $password]));
        Http::redirect(admin_url('users'));
    }

    public static function edit(array $user, string $id): never
    {
        $target = Users::find($id);
        if (!$target) {
            Admin::back('users', t('Compte introuvable.'), 'bad');
        }
        Admin::view('user-edit', ['target' => $target], $target['name']);
    }

    public static function update(array $user, string $id): never
    {
        $target = Users::find($id);
        if (!$target) {
            Admin::back('users', t('Compte introuvable.'), 'bad');
        }
        if (!Auth::confirmed($user)) {
            Admin::back('users/' . $id, t('Mot de passe incorrect.'), 'bad');
        }
        $isSelf = $target['id'] === $user['id'];
        $lastAdmin = $target['role'] === 'admin' && count(Users::admins()) <= 1;
        switch (Req::input('action')) {
            case 'delete':
                if ($isSelf || $lastAdmin) {
                    Admin::back('users/' . $id, t('Impossible de supprimer ce compte.'), 'bad');
                }
                Users::delete($id);
                Log::security('user_deleted', ['target' => $target['email']]);
                Admin::back('users', t('Compte supprimé.'));
            case 'reset':
                $password = Security::randomPassword();
                $hash = Security::hashPassword($password);
                $target = Users::update($id, fn (array $u): array => [
                    'pass' => $hash,
                    'ver' => (int) ($u['ver'] ?? 1) + 1,
                    'must_change' => true,
                ] + $u) ?? Admin::back('users', t('Compte introuvable.'), 'bad');
                Log::security('password_reset', ['target' => $target['email']]);
                Session::flash('secret', t('Nouveau mot de passe provisoire pour {name} : {pass}', ['name' => $target['name'], 'pass' => $password]));
                Http::redirect(admin_url('users/' . $id));
            case 'disable2fa':
                Users::update($id, fn (array $u): array => ['totp' => self::NO_TOTP] + $u);
                Log::security('2fa_reset', ['target' => $target['email']]);
                Admin::back('users/' . $id, t('Double authentification désactivée pour ce compte.'));
            default:
                $email = Sanitize::email(Req::input('email'));
                $other = $email !== '' ? Users::findByEmail($email) : null;
                if ($email === '' || ($other && $other['id'] !== $id)) {
                    Admin::back('users/' . $id, t('Adresse e-mail invalide ou déjà utilisée.'), 'bad');
                }
                $name = Sanitize::text(Req::input('name'), 80);
                $role = Req::input('role') === 'admin' ? 'admin' : 'editor';
                Users::update($id, function (array $u) use ($name, $email, $role, $isSelf, $lastAdmin): array {
                    $u['name'] = $name !== '' ? $name : $u['name'];
                    $u['email'] = $email;
                    if (!$lastAdmin && !$isSelf) {
                        $u['role'] = $role;
                    }
                    return $u;
                });
                Cache::purge();
                Admin::back('users/' . $id, t('Compte mis à jour.'));
        }
    }

    public static function profile(array $user): never
    {
        $secret = $_SESSION['totp_setup'] ?? '';
        $qr = '';
        if (is_string($secret) && $secret !== '') {
            $qr = Qr::svg(Totp::uri($secret, $user['email'], (string) Settings::get('name')), 5, 2);
        }
        // Les codes de secours ne s'affichent qu'une seule fois.
        $recovery = $_SESSION['recovery_show'] ?? [];
        unset($_SESSION['recovery_show']);
        $avatar = Media::get((string) ($user['avatar'] ?? ''));
        Admin::view('profile', [
            'me' => $user,
            'setup' => $secret,
            'qr' => $qr,
            'recovery' => is_array($recovery) ? $recovery : [],
            'avatar' => $avatar ? AdminMedia::export($avatar) : null,
        ], t('Mon profil'));
    }

    public static function saveProfile(array $user): never
    {
        $id = (string) $user['id'];
        switch (Req::input('action')) {
            case 'password':
                if (!password_verify(Req::input('current'), (string) $user['pass'])) {
                    Admin::back('profile#mot-de-passe', t('Mot de passe actuel incorrect.'), 'bad');
                }
                $new = Req::input('password');
                $issues = Security::passwordIssues($new, [$user['name'], explode('@', $user['email'])[0]]);
                if ($issues) {
                    Admin::back('profile#mot-de-passe', implode(' ', $issues), 'bad');
                }
                $hash = Security::hashPassword($new);
                $user = Users::update($id, fn (array $u): array => [
                    'pass' => $hash,
                    'ver' => (int) ($u['ver'] ?? 1) + 1,
                    'must_change' => false,
                ] + $u);
                if (!$user || !Auth::login($user)) {
                    Admin::back('profile', t('Session expirée : réessaie.'), 'bad');
                }
                Log::security('password_changed');
                Admin::back('profile', t('Mot de passe modifié. Les autres appareils ont été déconnectés.'));
            case '2fa-start':
                $_SESSION['totp_setup'] = Totp::secret();
                Http::redirect(admin_url('profile#securite'));
            case '2fa-confirm':
                $secret = (string) ($_SESSION['totp_setup'] ?? '');
                $step = $secret !== '' ? Totp::verify($secret, Req::input('code')) : null;
                if ($step === null) {
                    Admin::back('profile#securite', t('Code incorrect : vérifie l’heure de ton téléphone et réessaie.'), 'bad');
                }
                $codes = Totp::recoveryCodes();
                $totp = ['on' => true, 'secret' => $secret, 'last' => $step, 'recovery' => array_map([Totp::class, 'hashRecovery'], $codes)];
                Users::update($id, fn (array $u): array => ['totp' => $totp] + $u);
                unset($_SESSION['totp_setup']);
                $_SESSION['recovery_show'] = $codes;
                Log::security('2fa_enabled');
                Session::flash('ok', t('Double authentification activée 🎉 Garde précieusement tes codes de secours.'));
                Http::redirect(admin_url('profile#securite'));
            case '2fa-disable':
                if (!password_verify(Req::input('current'), (string) $user['pass'])) {
                    Admin::back('profile#securite', t('Mot de passe incorrect.'), 'bad');
                }
                Users::update($id, fn (array $u): array => ['totp' => self::NO_TOTP] + $u);
                Log::security('2fa_disabled');
                Admin::back('profile#securite', t('Double authentification désactivée.'), 'warn');
            case 'recovery':
                if (empty($user['totp']['on']) || !password_verify(Req::input('current'), (string) $user['pass'])) {
                    Admin::back('profile#securite', t('Mot de passe incorrect.'), 'bad');
                }
                $codes = Totp::recoveryCodes();
                $hashes = array_map([Totp::class, 'hashRecovery'], $codes);
                Users::update($id, function (array $u) use ($hashes): array {
                    $u['totp']['recovery'] = $hashes;
                    return $u;
                });
                $_SESSION['recovery_show'] = $codes;
                Http::redirect(admin_url('profile#securite'));
            case 'revoke':
                $user = Auth::revokeAll($id);
                if (!$user || !Auth::login($user)) {
                    Admin::back('profile', t('Session expirée : réessaie.'), 'bad');
                }
                Admin::back('profile#securite', t('Tous tes autres appareils ont été déconnectés.'));
            default:
                $email = Sanitize::email(Req::input('email'));
                $other = $email !== '' ? Users::findByEmail($email) : null;
                if ($email === '' || ($other && $other['id'] !== $id)) {
                    Admin::back('profile', t('Adresse e-mail invalide ou déjà utilisée.'), 'bad');
                }
                if (mb_strtolower($email) !== mb_strtolower((string) $user['email']) && !Auth::confirmed($user)) {
                    Admin::back('profile', t('Confirme ton mot de passe pour changer ton e-mail de connexion.'), 'bad');
                }
                $links = [];
                foreach (preg_split('/\R/', Req::input('links')) ?: [] as $line) {
                    $url = Sanitize::url(trim($line));
                    if (preg_match('~^https?://~', $url)) {
                        $links[] = $url;
                    }
                }
                $lang = isset(I18n::LANGS[Req::input('lang')]) ? Req::input('lang') : 'fr';
                $changes = [
                    'email' => $email,
                    'job' => Sanitize::text(Req::input('job'), 120),
                    'bio' => Sanitize::multiline(Req::input('bio'), 1200),
                    'avatar' => Media::get(Req::input('avatar')) ? Req::input('avatar') : '',
                    'links' => array_slice($links, 0, 8),
                    'lang' => $lang,
                ];
                $name = Sanitize::text(Req::input('name'), 80);
                if ($name !== '') {
                    $changes['name'] = $name;
                }
                Users::update($id, fn (array $u): array => $changes + $u);
                Cache::purge();
                I18n::setLang($lang);
                Admin::back('profile', t('Profil enregistré.'));
        }
    }
}
