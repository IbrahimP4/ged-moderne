<?php

declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════════════
 * TESTS DE CARACTÉRISATION (Golden Master)
 * ════════════════════════════════════════════════════════════════════════════
 *
 * OBJECTIF :
 *   Capturer le comportement ACTUEL du legacy SeedDMS avant tout refactoring.
 *   Ces tests ne vérifient pas si le comportement est CORRECT, mais s'il est
 *   STABLE. Ils forment le filet de sécurité du Strangler Fig.
 *
 * UTILISATION :
 *   1. Phase 0  : Lancer en mode CAPTURE → génère le golden master
 *   2. Phase 1+ : Lancer en mode COMPARE → détecte les régressions
 *
 * Pour capturer : GOLDEN_MASTER_MODE=capture vendor/bin/pest tests/Characterization
 * Pour comparer : vendor/bin/pest tests/Characterization
 *
 * PRÉREQUIS :
 *   - Legacy SeedDMS doit tourner sur LEGACY_GED_URL (voir .env)
 *   - Un compte admin avec les credentials dans LEGACY_ADMIN_USER / LEGACY_ADMIN_PASS
 * ════════════════════════════════════════════════════════════════════════════
 */

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

$legacyUrl      = $_ENV['LEGACY_GED_URL'] ?? 'http://localhost:8080';
$captureMode    = ($_ENV['GOLDEN_MASTER_MODE'] ?? 'compare') === 'capture';
$goldenMasterDir = __DIR__ . '/golden_master';

if (! is_dir($goldenMasterDir)) {
    mkdir($goldenMasterDir, 0755, true);
}

beforeAll(function () use ($legacyUrl): void {
    try {
        $client = HttpClient::create(['timeout' => 5]);
        $client->request('GET', $legacyUrl);
    } catch (TransportExceptionInterface) {
        test()->markTestSkipped(
            'Legacy SeedDMS non disponible sur ' . $legacyUrl . '. ' .
            'Démarrer le legacy et relancer les tests de caractérisation.',
        );
    }
});

// ── Helper : capture ou compare ───────────────────────────────────────────────
function goldenMasterAssert(string $testName, string $actual, string $goldenMasterDir, bool $captureMode): void
{
    $file = $goldenMasterDir . '/' . preg_replace('/[^a-z0-9_\-]/i', '_', $testName) . '.txt';

    if ($captureMode) {
        file_put_contents($file, $actual);
        test()->markTestIncomplete('Golden master capturé pour : ' . $testName);
        return;
    }

    if (! file_exists($file)) {
        test()->fail(
            "Golden master manquant pour : {$testName}. " .
            "Lancer : GOLDEN_MASTER_MODE=capture vendor/bin/pest tests/Characterization",
        );
    }

    $expected = file_get_contents($file);
    expect($actual)->toBe($expected, "Régression détectée sur : {$testName}");
}

describe('Legacy SeedDMS — Comportement HTTP', function () use ($legacyUrl, $captureMode, $goldenMasterDir): void {

    // ── Authentification ─────────────────────────────────────────────────────

    it('retourne 200 sur la page de login', function () use ($legacyUrl): void {
        $client   = HttpClient::create(['max_redirects' => 0]);
        $response = $client->request('GET', $legacyUrl . '/out/out.Login.php');

        expect($response->getStatusCode())->toBeIn([200, 302]);
    });

    it('rejette un login avec mauvais credentials', function () use ($legacyUrl, $captureMode, $goldenMasterDir): void {
        $client = HttpClient::create(['max_redirects' => 5]);

        $response = $client->request('POST', $legacyUrl . '/op/op.Login.php', [
            'body' => [
                'login'    => 'utilisateur_inexistant_xyz',
                'pwd'      => 'mot_de_passe_invalide_xyz',
                'referuri' => '',
            ],
        ]);

        $statusCode = $response->getStatusCode();
        expect($statusCode)->toBeIn([200, 302, 401, 403]);

        $normalizedBody = normalizeHtmlForGoldenMaster($response->getContent(throw: false));
        goldenMasterAssert('login_echec', $normalizedBody, $goldenMasterDir, $captureMode);
    });

    // ── Accès non authentifié ─────────────────────────────────────────────────

    it('redirige un visiteur non authentifié vers le login', function () use ($legacyUrl): void {
        $client = HttpClient::create(['max_redirects' => 0]);

        $response = $client->request('GET', $legacyUrl . '/out/out.ViewFolder.php');
        $status   = $response->getStatusCode();

        expect($status)->toBeIn([302, 301, 200]);

        if ($status === 302) {
            expect($response->getHeaders(throw: false)['location'][0] ?? '')
                ->toContain('Login');
        }
    });

    it('retourne une erreur pour un folderid invalide (non numérique)', function () use ($legacyUrl, $captureMode, $goldenMasterDir): void {
        $client   = HttpClient::create(['max_redirects' => 5]);
        $response = $client->request('GET', $legacyUrl . '/out/out.ViewFolder.php?folderid=abc');

        expect($response->getStatusCode())->toBeIn([200, 302, 400, 404]);

        $normalizedBody = normalizeHtmlForGoldenMaster($response->getContent(throw: false));
        goldenMasterAssert('folder_id_invalide', $normalizedBody, $goldenMasterDir, $captureMode);
    });

    it('retourne une erreur pour un docid qui n\'existe pas', function () use ($legacyUrl, $captureMode, $goldenMasterDir): void {
        $client   = HttpClient::create(['max_redirects' => 5]);
        $response = $client->request('GET', $legacyUrl . '/out/out.ViewDocument.php?documentid=999999999');

        expect($response->getStatusCode())->toBeIn([200, 302, 404]);

        $normalizedBody = normalizeHtmlForGoldenMaster($response->getContent(throw: false));
        goldenMasterAssert('document_inexistant', $normalizedBody, $goldenMasterDir, $captureMode);
    });

    // ── Headers de sécurité (ce qui MANQUE dans le legacy) ───────────────────

    it('DOCUMENTE l\'absence de Content-Security-Policy dans le legacy', function () use ($legacyUrl): void {
        $client   = HttpClient::create(['max_redirects' => 5]);
        $response = $client->request('GET', $legacyUrl . '/out/out.Login.php');

        $headers = $response->getHeaders(throw: false);

        // On documente l'état actuel — ces assertions PASSENT si le legacy
        // n'a PAS ces headers (ce qu'on sait être le cas).
        // La nouvelle GED devra avoir l'inverse.
        $csp = $headers['content-security-policy'][0] ?? null;
        expect($csp)->toBeNull(
            'ATTENTION : Le legacy a ajouté un CSP. Mettre à jour le golden master.',
        );
    });

    it('DOCUMENTE l\'absence de X-Frame-Options dans le legacy', function () use ($legacyUrl): void {
        $client   = HttpClient::create(['max_redirects' => 5]);
        $response = $client->request('GET', $legacyUrl . '/out/out.Login.php');

        $headers   = $response->getHeaders(throw: false);
        $xframe    = $headers['x-frame-options'][0] ?? null;

        expect($xframe)->toBeNull(
            'ATTENTION : Le legacy a ajouté X-Frame-Options. Mettre à jour le golden master.',
        );
    });
});

// ── Helper : normalise le HTML pour comparaison stable ───────────────────────
function normalizeHtmlForGoldenMaster(string $html): string
{
    // Supprimer les tokens CSRF (changent à chaque requête)
    $html = preg_replace('/name="formtoken" value="[^"]*"/', 'name="formtoken" value="REDACTED"', $html) ?? $html;

    // Supprimer les timestamps
    $html = preg_replace('/\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}/', 'TIMESTAMP_REDACTED', $html) ?? $html;

    // Supprimer les nonces de session
    $html = preg_replace('/PHPSESSID=[a-f0-9]+/', 'PHPSESSID=REDACTED', $html) ?? $html;

    // Normaliser les espaces superflus
    $html = preg_replace('/\s+/', ' ', $html) ?? $html;

    return trim($html);
}
