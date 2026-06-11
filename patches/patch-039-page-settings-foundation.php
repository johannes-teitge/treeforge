<?php
declare(strict_types=1);

/**
 * TreeForge CMS - Patch 039
 * Page Settings Foundation
 *
 * Ziel:
 * - RootNode-/Page-Settings im Backend bearbeiten
 * - Speicherung direkt in storage/workspaces/{workspace}/pages/{page}.json
 * - Tabs: General, SEO, Social, Overview, Routing, Visibility, Advanced
 * - Visibility mit active, valid_from, valid_until und Zeitsteuerung
 */

return function (string $root, callable $log): void {

    $write = function (string $file, string $content) use ($log): void {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        if (file_exists($file)) {
            copy($file, $file . '.bak-' . date('Ymd-His'));
            $log("Backup erstellt: {$file}");
        }

        file_put_contents($file, $content);
        $log("Datei geschrieben: {$file}");
    };

    $log('Patch 039 Page Settings Foundation gestartet');

    $write($root . '/app/Core/PageSettings/PageSettingsManager.php', <<<'PHP'
<?php
declare(strict_types=1);

namespace TreeForge\Core\PageSettings;

class PageSettingsManager
{
    public function __construct(protected string $root) {}

    public function load(string $workspace = 'published', string $page = 'home'): array
    {
        $file = $this->pageFile($workspace, $page);

        if (!file_exists($file)) {
            return $this->defaults($page);
        }

        $data = json_decode((string)file_get_contents($file), true);

        if (!is_array($data)) {
            return $this->defaults($page);
        }

        return $this->normalize($data, $page);
    }

    public function save(array $pageData, string $workspace = 'published', string $page = 'home'): void
    {
        $file = $this->pageFile($workspace, $page);

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }

        $pageData = $this->normalize($pageData, $page);
        $pageData['updated_at'] = date('c');

        file_put_contents(
            $file,
            json_encode($pageData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function applySettings(array $pageData, array $input, string $page = 'home'): array
    {
        $pageData = $this->normalize($pageData, $page);

        $pageData['id'] = $page;
        $pageData['type'] = 'root';

        $pageData['title'] = trim((string)($input['general']['title'] ?? $pageData['title']));
        $pageData['slug'] = trim((string)($input['general']['slug'] ?? $pageData['slug']));
        $pageData['status'] = (string)($input['general']['status'] ?? $pageData['status']);
        $pageData['content_type'] = (string)($input['general']['content_type'] ?? $pageData['content_type']);
        $pageData['template'] = trim((string)($input['general']['template'] ?? $pageData['template']));

        $pageData['seo'] = [
            'meta_title' => trim((string)($input['seo']['meta_title'] ?? '')),
            'meta_description' => trim((string)($input['seo']['meta_description'] ?? '')),
            'keywords' => trim((string)($input['seo']['keywords'] ?? '')),
            'canonical_url' => trim((string)($input['seo']['canonical_url'] ?? '')),
            'robots' => (string)($input['seo']['robots'] ?? 'index,follow'),
        ];

        $pageData['social'] = [
            'og_title' => trim((string)($input['social']['og_title'] ?? '')),
            'og_description' => trim((string)($input['social']['og_description'] ?? '')),
            'og_image' => trim((string)($input['social']['og_image'] ?? '')),
            'twitter_card' => (string)($input['social']['twitter_card'] ?? 'summary_large_image'),
        ];

        $pageData['overview'] = [
            'teaser' => trim((string)($input['overview']['teaser'] ?? '')),
            'excerpt' => trim((string)($input['overview']['excerpt'] ?? '')),
            'featured_image' => trim((string)($input['overview']['featured_image'] ?? '')),
            'featured' => isset($input['overview']['featured']),
        ];

        $pageData['routing'] = [
            'path' => trim((string)($input['routing']['path'] ?? '')),
            'is_home' => isset($input['routing']['is_home']),
            'no_slug' => isset($input['routing']['no_slug']),
            'redirect_from' => $this->linesToArray((string)($input['routing']['redirect_from'] ?? '')),
            'redirect_to' => trim((string)($input['routing']['redirect_to'] ?? '')),
        ];

        $pageData['visibility'] = [
            'active' => isset($input['visibility']['active']),
            'valid_from' => $this->emptyToNull((string)($input['visibility']['valid_from'] ?? '')),
            'valid_until' => $this->emptyToNull((string)($input['visibility']['valid_until'] ?? '')),
            'schedule_enabled' => isset($input['visibility']['schedule_enabled']),
            'schedule' => [
                'days' => array_values((array)($input['visibility']['schedule']['days'] ?? [])),
                'time_from' => trim((string)($input['visibility']['schedule']['time_from'] ?? '')),
                'time_until' => trim((string)($input['visibility']['schedule']['time_until'] ?? '')),
                'timezone' => trim((string)($input['visibility']['schedule']['timezone'] ?? 'Europe/Berlin')),
            ],
            'outside_schedule' => (string)($input['visibility']['outside_schedule'] ?? 'hide'),
        ];

        $pageData['advanced'] = [
            'author' => trim((string)($input['advanced']['author'] ?? '')),
            'editor' => trim((string)($input['advanced']['editor'] ?? '')),
            'created_at' => $this->emptyToNull((string)($input['advanced']['created_at'] ?? ($pageData['advanced']['created_at'] ?? ''))),
            'published_at' => $this->emptyToNull((string)($input['advanced']['published_at'] ?? '')),
            'archived_at' => $this->emptyToNull((string)($input['advanced']['archived_at'] ?? '')),
            'experiments' => (array)($pageData['advanced']['experiments'] ?? []),
        ];

        if (empty($pageData['advanced']['created_at'])) {
            $pageData['advanced']['created_at'] = date('c');
        }

        return $pageData;
    }

    public function normalize(array $pageData, string $page = 'home'): array
    {
        $pageData = $this->merge($this->defaults($page), $pageData);

        $pageData['id'] = (string)($pageData['id'] ?? $page);
        $pageData['type'] = (string)($pageData['type'] ?? 'root');

        if (!isset($pageData['nodes']) && !isset($pageData['children'])) {
            $pageData['nodes'] = [];
        }

        return $pageData;
    }

    public function defaults(string $page = 'home'): array
    {
        return [
            'id' => $page,
            'type' => 'root',
            'title' => $page === 'home' ? 'Startseite' : $page,
            'slug' => $page === 'home' ? '' : $page,
            'status' => 'published',
            'content_type' => 'page',
            'template' => 'default',
            'lang' => 'de',
            'seo' => [
                'meta_title' => '',
                'meta_description' => '',
                'keywords' => '',
                'canonical_url' => '',
                'robots' => 'index,follow',
            ],
            'social' => [
                'og_title' => '',
                'og_description' => '',
                'og_image' => '',
                'twitter_card' => 'summary_large_image',
            ],
            'overview' => [
                'teaser' => '',
                'excerpt' => '',
                'featured_image' => '',
                'featured' => false,
            ],
            'routing' => [
                'path' => $page === 'home' ? '/' : '/' . $page,
                'is_home' => $page === 'home',
                'no_slug' => $page === 'home',
                'redirect_from' => [],
                'redirect_to' => '',
            ],
            'visibility' => [
                'active' => true,
                'valid_from' => null,
                'valid_until' => null,
                'schedule_enabled' => false,
                'schedule' => [
                    'days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'time_from' => '',
                    'time_until' => '',
                    'timezone' => 'Europe/Berlin',
                ],
                'outside_schedule' => 'hide',
            ],
            'advanced' => [
                'author' => '',
                'editor' => '',
                'created_at' => null,
                'published_at' => null,
                'archived_at' => null,
                'experiments' => [],
            ],
        ];
    }

    protected function pageFile(string $workspace, string $page): string
    {
        $workspace = preg_replace('/[^a-zA-Z0-9_-]/', '', $workspace) ?: 'published';
        $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page) ?: 'home';

        return $this->root . '/storage/workspaces/' . $workspace . '/pages/' . $page . '.json';
    }

    protected function linesToArray(string $value): array
    {
        return array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            preg_split('/\R/', $value) ?: []
        )));
    }

    protected function emptyToNull(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    protected function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !$this->isList($value)) {
                $base[$key] = $this->merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    protected function isList(array $array): bool
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }
}
PHP);

    $write($root . '/public/admin/page-settings/index.php', <<<'PHP'
<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../app/Core/bootstrap.php';

use TreeForge\Admin\AdminLayout;
use TreeForge\Core\PageSettings\PageSettingsManager;
use TreeForge\Core\Settings\SettingsManager;

$root = dirname(__DIR__, 3);
$page = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['page'] ?? $_POST['page'] ?? 'home')) ?: 'home';
$workspace = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($_GET['workspace'] ?? $_POST['workspace'] ?? 'published')) ?: 'published';

$settings = new SettingsManager($root);
$settingsData = $settings->all();

$pageSettings = new PageSettingsManager($root);
$pageData = $pageSettings->load($workspace, $page);

$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pageData = $pageSettings->applySettings($pageData, $_POST, $page);
        $pageSettings->save($pageData, $workspace, $page);
        $saved = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function checked(bool $value): string { return $value ? ' checked' : ''; }
function selected(string $current, string $value): string { return $current === $value ? ' selected' : ''; }
function dt(?string $value): string {
    if (!$value) return '';
    $time = strtotime($value);
    return $time === false ? '' : date('Y-m-d\TH:i', $time);
}
function lines(array $value): string { return implode("\n", $value); }

$statuses = ['draft'=>'Draft','review'=>'Review','scheduled'=>'Scheduled','published'=>'Published','archived'=>'Archived','hidden'=>'Hidden'];
$contentTypes = ['page'=>'Page','landingpage'=>'Landingpage','blog'=>'Blog','news'=>'News','faq'=>'FAQ','product'=>'Product'];
$robots = ['index,follow','noindex,follow','index,nofollow','noindex,nofollow'];
$twitterCards = ['summary','summary_large_image'];
$days = ['mon'=>'Montag','tue'=>'Dienstag','wed'=>'Mittwoch','thu'=>'Donnerstag','fri'=>'Freitag','sat'=>'Samstag','sun'=>'Sonntag'];

ob_start();
?>
<?php if ($saved): ?><div class="tf-notice success">Seiteneinstellungen wurden gespeichert.</div><?php endif; ?>
<?php if ($error !== ''): ?><div class="tf-notice error"><?= e($error) ?></div><?php endif; ?>

<form method="post" class="tf-page-settings-form">
  <input type="hidden" name="page" value="<?= e($page) ?>">
  <input type="hidden" name="workspace" value="<?= e($workspace) ?>">

  <aside class="tf-page-tabs">
    <a href="#general">General</a>
    <a href="#seo">SEO</a>
    <a href="#social">Social</a>
    <a href="#overview">Overview</a>
    <a href="#routing">Routing</a>
    <a href="#visibility">Visibility</a>
    <a href="#advanced">Advanced</a>
  </aside>

  <section class="tf-page-settings-content">
    <section id="general" class="tf-page-card">
      <h1>Page Settings</h1>
      <p>RootNode-Basisdaten für <code><?= e($page) ?></code> im Workspace <code><?= e($workspace) ?></code>.</p>

      <label><span>Titel</span><input type="text" name="general[title]" value="<?= e($pageData['title'] ?? '') ?>"></label>
      <label><span>Slug</span><input type="text" name="general[slug]" value="<?= e($pageData['slug'] ?? '') ?>"></label>

      <label><span>Status</span><select name="general[status]">
        <?php foreach ($statuses as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= selected((string)($pageData['status'] ?? 'published'), $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select></label>

      <label><span>Content Type</span><select name="general[content_type]">
        <?php foreach ($contentTypes as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= selected((string)($pageData['content_type'] ?? 'page'), $value) ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select></label>

      <label><span>Template</span><input type="text" name="general[template]" value="<?= e($pageData['template'] ?? 'default') ?>"></label>
    </section>

    <section id="seo" class="tf-page-card">
      <h2>SEO</h2>
      <p>Meta-Daten für Suchmaschinen und technische Indexierung.</p>
      <label><span>Meta Title</span><input type="text" name="seo[meta_title]" value="<?= e($pageData['seo']['meta_title'] ?? '') ?>"></label>
      <label><span>Meta Description</span><textarea name="seo[meta_description]"><?= e($pageData['seo']['meta_description'] ?? '') ?></textarea></label>
      <label><span>Keywords</span><input type="text" name="seo[keywords]" value="<?= e($pageData['seo']['keywords'] ?? '') ?>"></label>
      <label><span>Canonical URL</span><input type="url" name="seo[canonical_url]" value="<?= e($pageData['seo']['canonical_url'] ?? '') ?>"></label>
      <label><span>Robots</span><select name="seo[robots]">
        <?php foreach ($robots as $value): ?>
          <option value="<?= e($value) ?>"<?= selected((string)($pageData['seo']['robots'] ?? 'index,follow'), $value) ?>><?= e($value) ?></option>
        <?php endforeach; ?>
      </select></label>
    </section>

    <section id="social" class="tf-page-card">
      <h2>Social</h2>
      <p>OpenGraph- und Social-Media-Daten.</p>
      <label><span>OG Title</span><input type="text" name="social[og_title]" value="<?= e($pageData['social']['og_title'] ?? '') ?>"></label>
      <label><span>OG Description</span><textarea name="social[og_description]"><?= e($pageData['social']['og_description'] ?? '') ?></textarea></label>
      <label><span>OG Image</span><input type="text" name="social[og_image]" value="<?= e($pageData['social']['og_image'] ?? '') ?>"></label>
      <label><span>Twitter Card</span><select name="social[twitter_card]">
        <?php foreach ($twitterCards as $value): ?>
          <option value="<?= e($value) ?>"<?= selected((string)($pageData['social']['twitter_card'] ?? 'summary_large_image'), $value) ?>><?= e($value) ?></option>
        <?php endforeach; ?>
      </select></label>
    </section>

    <section id="overview" class="tf-page-card">
      <h2>Overview</h2>
      <p>Daten für Übersichten, Karten, Bloglisten und spätere Suchergebnisse.</p>
      <label><span>Teaser</span><input type="text" name="overview[teaser]" value="<?= e($pageData['overview']['teaser'] ?? '') ?>"></label>
      <label><span>Excerpt</span><textarea name="overview[excerpt]"><?= e($pageData['overview']['excerpt'] ?? '') ?></textarea></label>
      <label><span>Featured Image</span><input type="text" name="overview[featured_image]" value="<?= e($pageData['overview']['featured_image'] ?? '') ?>"></label>
      <label class="tf-check"><input type="checkbox" name="overview[featured]" value="1"<?= checked((bool)($pageData['overview']['featured'] ?? false)) ?>><span>Featured</span></label>
    </section>

    <section id="routing" class="tf-page-card">
      <h2>Routing</h2>
      <p>Vorbereitung für SlugManager und SEO-URLs. Die Logik folgt in einem späteren Patch.</p>
      <label><span>Path</span><input type="text" name="routing[path]" value="<?= e($pageData['routing']['path'] ?? '') ?>"></label>
      <label class="tf-check"><input type="checkbox" name="routing[is_home]" value="1"<?= checked((bool)($pageData['routing']['is_home'] ?? false)) ?>><span>Startseite</span></label>
      <label class="tf-check"><input type="checkbox" name="routing[no_slug]" value="1"<?= checked((bool)($pageData['routing']['no_slug'] ?? false)) ?>><span>Kein Slug</span></label>
      <label><span>Redirect From</span><textarea name="routing[redirect_from]" placeholder="/alte-seite&#10;/alter-pfad"><?= e(lines((array)($pageData['routing']['redirect_from'] ?? []))) ?></textarea><small>Eine URL pro Zeile</small></label>
      <label><span>Redirect To</span><input type="text" name="routing[redirect_to]" value="<?= e($pageData['routing']['redirect_to'] ?? '') ?>"></label>
    </section>

    <section id="visibility" class="tf-page-card">
      <h2>Visibility</h2>
      <p>Aktivierung, Veröffentlichungszeitraum und optionale Zeitsteuerung.</p>
      <label class="tf-check"><input type="checkbox" name="visibility[active]" value="1"<?= checked((bool)($pageData['visibility']['active'] ?? true)) ?>><span>Aktiv</span></label>
      <div class="tf-page-grid">
        <label><span>Aktiv von</span><input type="datetime-local" name="visibility[valid_from]" value="<?= e(dt($pageData['visibility']['valid_from'] ?? null)) ?>"></label>
        <label><span>Aktiv bis</span><input type="datetime-local" name="visibility[valid_until]" value="<?= e(dt($pageData['visibility']['valid_until'] ?? null)) ?>"></label>
      </div>
      <label class="tf-check"><input type="checkbox" name="visibility[schedule_enabled]" value="1"<?= checked((bool)($pageData['visibility']['schedule_enabled'] ?? false)) ?>><span>Zeitsteuerung aktivieren</span></label>
      <div class="tf-day-grid">
        <?php $scheduleDays = (array)($pageData['visibility']['schedule']['days'] ?? []); ?>
        <?php foreach ($days as $value => $label): ?>
          <label class="tf-check small"><input type="checkbox" name="visibility[schedule][days][]" value="<?= e($value) ?>"<?= checked(in_array($value, $scheduleDays, true)) ?>><span><?= e($label) ?></span></label>
        <?php endforeach; ?>
      </div>
      <div class="tf-page-grid">
        <label><span>Uhrzeit von</span><input type="time" name="visibility[schedule][time_from]" value="<?= e($pageData['visibility']['schedule']['time_from'] ?? '') ?>"></label>
        <label><span>Uhrzeit bis</span><input type="time" name="visibility[schedule][time_until]" value="<?= e($pageData['visibility']['schedule']['time_until'] ?? '') ?>"></label>
      </div>
      <label><span>Timezone</span><input type="text" name="visibility[schedule][timezone]" value="<?= e($pageData['visibility']['schedule']['timezone'] ?? 'Europe/Berlin') ?>"></label>
      <label><span>Außerhalb Zeitplan</span><select name="visibility[outside_schedule]">
        <option value="hide"<?= selected((string)($pageData['visibility']['outside_schedule'] ?? 'hide'), 'hide') ?>>Ausblenden</option>
        <option value="show"<?= selected((string)($pageData['visibility']['outside_schedule'] ?? 'hide'), 'show') ?>>Sichtbar lassen</option>
        <option value="notice"<?= selected((string)($pageData['visibility']['outside_schedule'] ?? 'hide'), 'notice') ?>>Hinweis anzeigen</option>
      </select></label>
    </section>

    <section id="advanced" class="tf-page-card">
      <h2>Advanced</h2>
      <p>Redaktionelle Metadaten und spätere Experimente/A-B-Tests.</p>
      <label><span>Author</span><input type="text" name="advanced[author]" value="<?= e($pageData['advanced']['author'] ?? '') ?>"></label>
      <label><span>Editor</span><input type="text" name="advanced[editor]" value="<?= e($pageData['advanced']['editor'] ?? '') ?>"></label>
      <div class="tf-page-grid">
        <label><span>Created At</span><input type="datetime-local" name="advanced[created_at]" value="<?= e(dt($pageData['advanced']['created_at'] ?? null)) ?>"></label>
        <label><span>Published At</span><input type="datetime-local" name="advanced[published_at]" value="<?= e(dt($pageData['advanced']['published_at'] ?? null)) ?>"></label>
      </div>
      <label><span>Archived At</span><input type="datetime-local" name="advanced[archived_at]" value="<?= e(dt($pageData['advanced']['archived_at'] ?? null)) ?>"></label>
      <div class="tf-warning">Experiments/A-B-Tests werden als Feld vorbereitet, aber noch nicht aktiv ausgewertet.</div>
    </section>

    <div class="tf-page-savebar">
      <a href="/explorer?workspace=<?= e($workspace) ?>" class="tf-page-button secondary">Zurück zum Explorer</a>
      <button type="submit" class="tf-page-button">Seiteneinstellungen speichern</button>
    </div>
  </section>
</form>
<?php
$content = ob_get_clean();

echo (new AdminLayout())->render(
    'Page Settings',
    $content,
    'page-settings',
    [
        'site_name' => (string)($settingsData['general']['site_name'] ?? 'TreeForge CMS'),
        'subtitle' => 'RootNode-Metadaten, SEO, Routing und Sichtbarkeit',
    ]
);
PHP);

    $write($root . '/public/assets/css/page-settings.css', <<<'CSS'
.tf-page-settings-form{display:grid;grid-template-columns:250px minmax(0,1fr);gap:1rem}.tf-page-tabs{background:var(--tf-cream);border:1px solid var(--tf-border);border-radius:1.1rem;padding:.75rem;height:max-content;position:sticky;top:108px}.tf-page-tabs a{display:block;padding:.75rem .85rem;color:var(--tf-dark);text-decoration:none;border-radius:.8rem;font-weight:800;margin-bottom:.25rem}.tf-page-tabs a:hover{background:rgba(216,138,34,.14);color:var(--tf-green)}.tf-page-settings-content{display:grid;gap:1rem}.tf-page-card{background:var(--tf-cream);border:1px solid var(--tf-border);border-radius:1.1rem;padding:clamp(1rem,3vw,2rem);box-shadow:0 1rem 2.8rem rgba(18,26,23,.05)}.tf-page-card h1,.tf-page-card h2{margin:0 0 .35rem;color:var(--tf-green)}.tf-page-card p{margin:0 0 1.25rem;color:var(--tf-muted)}.tf-page-card label{display:grid;gap:.35rem;margin-bottom:1rem;font-weight:850;color:var(--tf-green)}.tf-page-card input,.tf-page-card select,.tf-page-card textarea{width:100%;border:1px solid rgba(23,63,53,.22);border-radius:.8rem;padding:.75rem .85rem;font:inherit;background:#fff;color:var(--tf-dark)}.tf-page-card textarea{min-height:110px;resize:vertical}.tf-page-card small{color:var(--tf-muted);font-weight:650}.tf-check{display:flex!important;grid-template-columns:none!important;align-items:center;gap:.7rem!important}.tf-check input{width:auto;transform:scale(1.2)}.tf-check.small{margin:0;font-size:.92rem}.tf-page-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.tf-day-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.5rem;margin-bottom:1rem}.tf-page-savebar{position:sticky;bottom:0;background:rgba(245,243,234,.92);border:1px solid var(--tf-border);border-radius:1rem;padding:.8rem;display:flex;justify-content:flex-end;gap:.6rem;backdrop-filter:blur(12px)}.tf-page-button{border:0;background:var(--tf-green);color:#fff;padding:.8rem 1rem;border-radius:.8rem;font:inherit;font-weight:900;cursor:pointer;text-decoration:none;display:inline-flex}.tf-page-button.secondary{background:#fff;color:var(--tf-green);border:1px solid var(--tf-border)}@media(max-width:900px){.tf-page-settings-form,.tf-page-grid{grid-template-columns:1fr}.tf-page-tabs{position:static}.tf-day-grid{grid-template-columns:1fr 1fr}}
CSS);

    $adminCss = $root . '/public/assets/css/admin.css';
    if (file_exists($adminCss)) {
        $css = file_get_contents($adminCss);
        if (!str_contains($css, 'page-settings.css')) {
            $css .= "\n@import url('/assets/css/page-settings.css');\n";
            $write($adminCss, $css);
        }
    }

    $adminMenuFile = $root . '/app/Admin/AdminMenu.php';
    if (file_exists($adminMenuFile)) {
        $menu = file_get_contents($adminMenuFile);
        if (!str_contains($menu, "'key' => 'page-settings'")) {
            $old = "            [\n                'label' => 'Archive',\n                'href' => '/archives',\n                'icon' => '📦',\n                'key' => 'archives',\n            ],";
            $new = $old . "\n            [\n                'label' => 'Page Settings',\n                'href' => '/admin/page-settings/',\n                'icon' => '🧭',\n                'key' => 'page-settings',\n            ],";
            if (str_contains($menu, $old)) {
                $menu = str_replace($old, $new, $menu);
                $write($adminMenuFile, $menu);
            }
        }
    }

    $write($root . '/docs/treeforge/29-page-settings-foundation.md', <<<'MD'
# Page Settings Foundation

Patch 039 ergänzt erste RootNode-/Page-Settings.

## Route

```text
/admin/page-settings/
```

Optional:

```text
/admin/page-settings/?workspace=published&page=home
```

## Tabs

```text
General
SEO
Social
Overview
Routing
Visibility
Advanced
```

## Speicherung

Die Daten werden direkt in der jeweiligen Page-JSON gespeichert:

```text
storage/workspaces/{workspace}/pages/{page}.json
```

## Sichtbarkeit

Die RootNode unterstützt jetzt:

```text
active
valid_from
valid_until
schedule_enabled
schedule.days
schedule.time_from
schedule.time_until
schedule.timezone
outside_schedule
```

Damit sind spätere Szenarien möglich:

- Aktionsseiten
- Öffnungszeiten-Hinweise
- Wartungsmeldungen
- zeitlich begrenzte Landingpages
- Tagesangebote
- geplante Veröffentlichungen

## Hinweis

SlugManager und echtes Frontend-Routing werden erst in späteren Patches aktiv.
MD);

    $log('Patch 039 Page Settings Foundation fertig');
};
