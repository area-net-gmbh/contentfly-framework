<?php
/**
 * Erhebt beide Vendor-Bäume und gibt das Inventar als Markdown aus.
 *
 * Angelegt mit 006-001-0002. Der Sinn, ein Skript statt einer abgetippten Liste zu haben:
 * Eine Tabelle im Fliesstext veraltet beim ersten `composer update`, und niemand merkt es.
 * Hier lässt sie sich jederzeit neu erzeugen und gegen die alte halten.
 *
 *     php tools/dependency-inventory.php > an_project/docs/abhaengigkeiten-inventar.md
 *
 * Erhoben wird ausschliesslich — bewertet wird in 006-001-0004. Was hier steht, sind Fakten
 * aus `installed.json`, den `composer.json` der Pakete und den generierten Autoloadern.
 *
 * Drei Dinge, die dieses Skript findet und ein blosser Blick in die `installed.json` nicht:
 *
 *   Geisterpakete  Verzeichnisse unter vendor/, die in KEINER installed.json stehen. Sie
 *                  wurden von Hand hineinkopiert; Composer weiss von ihnen nichts.
 *   Dubletten      Pakete, die in beiden Bäumen liegen — oft in inkompatiblen Majors.
 *   Namensraum-
 *   Kollisionen    dasselbe PSR-4-Präfix in beiden Autoloadern. Der Root wird zuerst
 *                  geladen und gewinnt, unabhängig davon, welche Fassung gepflegt ist.
 */

$wurzel = dirname(__DIR__);

// ── Einlesen ───────────────────────────────────────────────────────────────────────────

/** @return array<string,array<string,mixed>> Paketname → Angaben */
function paketeLesen(string $installedJson, string $vendorVerzeichnis): array
{
    if (!is_file($installedJson)) {
        return array();
    }

    $roh = json_decode((string) file_get_contents($installedJson), true);
    $liste = isset($roh['packages']) ? $roh['packages'] : $roh;

    $pakete = array();
    foreach ($liste as $p) {
        $pakete[$p['name']] = array(
            'version' => $p['version'] ?? '?',
            'php'     => $p['require']['php'] ?? '',
            'pfad'    => $vendorVerzeichnis.'/'.$p['name'],
        );
    }

    ksort($pakete);

    return $pakete;
}

/**
 * Verzeichnisse unter vendor/, die aussehen wie ein Paket (hersteller/paket).
 *
 * @return array<int,string>
 */
function verzeichnisseAlsPakete(string $vendorVerzeichnis): array
{
    $gefunden = array();

    foreach (glob($vendorVerzeichnis.'/*', GLOB_ONLYDIR) ?: array() as $hersteller) {
        $name = basename($hersteller);
        if ($name === 'composer' || $name === 'bin') {
            continue;
        }

        foreach (glob($hersteller.'/*', GLOB_ONLYDIR) ?: array() as $paket) {
            $gefunden[] = $name.'/'.basename($paket);
        }
    }

    sort($gefunden);

    return $gefunden;
}

/** PSR-4-Präfixe aus dem generierten Autoloader. @return array<string,string> */
function psr4Praefixe(string $autoloadPsr4): array
{
    if (!is_file($autoloadPsr4)) {
        return array();
    }

    preg_match_all(
        "/^\s*'([^']+)'\s*=>\s*array\(\s*(?:\\\$vendorDir\s*\.\s*)?'([^']*)'/m",
        (string) file_get_contents($autoloadPsr4),
        $treffer,
        PREG_SET_ORDER
    );

    $praefixe = array();
    foreach ($treffer as $t) {
        $praefixe[$t[1]] = $t[2];
    }

    return $praefixe;
}

/** Wird der Namensraum des Pakets im Projektcode erwähnt? Erste Näherung, kein Beweis. */
function imCodeBenutzt(string $wurzel, string $paket): bool
{
    // Der Herstellername taugt nicht als Suchbegriff (zu generisch), der Paketname schon
    // eher. Zusätzlich der CamelCase-Namensraum, wie er in use-Zeilen steht.
    list($hersteller, $name) = array_pad(explode('/', $paket, 2), 2, '');

    $begriffe = array_unique(array_filter(array(
        $paket,
        $name,
        str_replace(array('-', '_'), '', ucwords($name, '-_')),
        str_replace(array('-', '_'), '', ucwords($hersteller, '-_')),
    )));

    $verzeichnisse = array($wurzel.'/lib', $wurzel.'/custom/Classes', $wurzel.'/custom/Controller',
                           $wurzel.'/custom/Entity', $wurzel.'/custom/Command', $wurzel.'/bin');

    foreach ($verzeichnisse as $v) {
        if (!is_dir($v)) {
            continue;
        }
        foreach ($begriffe as $b) {
            if (strlen($b) < 4) {
                continue;
            }
            $befehl = sprintf('grep -rilF %s %s 2>/dev/null | head -1', escapeshellarg($b), escapeshellarg($v));
            if (trim((string) shell_exec($befehl)) !== '') {
                return true;
            }
        }
    }

    return false;
}

// ── Erheben ────────────────────────────────────────────────────────────────────────────

$baeume = array(
    'Root'      => array('vendor',        $wurzel.'/vendor'),
    'custom/'   => array('custom/vendor', $wurzel.'/custom/vendor'),
);

$inventar = array();
$geister  = array();

foreach ($baeume as $label => list($relativ, $absolut)) {
    $ausJson      = paketeLesen($absolut.'/composer/installed.json', $relativ);
    $aufDerPlatte = verzeichnisseAlsPakete($absolut);

    foreach ($ausJson as $name => $angaben) {
        $inventar[$label][$name] = $angaben + array('herkunft' => 'installed.json');
    }

    foreach (array_diff($aufDerPlatte, array_keys($ausJson)) as $name) {
        $eigenes = $absolut.'/'.$name.'/composer.json';
        $meta    = is_file($eigenes) ? json_decode((string) file_get_contents($eigenes), true) : array();

        $inventar[$label][$name] = array(
            'version'  => $meta['version'] ?? '(unbekannt)',
            'php'      => $meta['require']['php'] ?? '',
            'pfad'     => $relativ.'/'.$name,
            'herkunft' => '**von Hand**',
        );
        $geister[$label][] = $name;
    }

    ksort($inventar[$label]);
}

$psr4Root   = psr4Praefixe($wurzel.'/vendor/composer/autoload_psr4.php');
$psr4Custom = psr4Praefixe($wurzel.'/custom/vendor/composer/autoload_psr4.php');
$kollision  = array_intersect(array_keys($psr4Root), array_keys($psr4Custom));

$dubletten = array_intersect(array_keys($inventar['Root'] ?? array()), array_keys($inventar['custom/'] ?? array()));

// ── Ausgeben ───────────────────────────────────────────────────────────────────────────

$gesamt = array_sum(array_map('count', $inventar));

echo "<!-- PURPOSE: Inventar beider Vendor-Bäume. ERZEUGT von tools/dependency-inventory.php — nicht von Hand pflegen. -->\n\n";
echo "# Abhängigkeiten-Inventar\n\n";
echo "**Erzeugt am ".date('Y-m-d')." mit `php tools/dependency-inventory.php`.** Nicht von Hand\n";
echo "pflegen — neu erzeugen. Erhoben mit `006-001-0002`; die **Zuordnung** (Root · `custom/` ·\n";
echo "`require-dev` · entfällt) kommt mit `006-001-0004` dazu.\n\n";
echo "Die Spalte *benutzt?* ist eine `grep`-Näherung über `lib/`, `custom/` und `bin/` — sie\n";
echo "beantwortet die Frage „lohnt genaueres Hinsehen?\", nicht „wird gebraucht?\".\n\n";
echo "**Sie erzeugt Fehlalarme.** `twig/twig` etwa steht auf *ja*, weil das Wort „Twig\" in\n";
echo "einem Kommentar von `Command/InstallCommand.php` vorkommt — benutzt wird es nicht. Ein\n";
echo "*ja* heisst: nachsehen. Ein *—* ist die belastbarere Aussage von beiden.\n\n";
echo "**$gesamt Pakete** insgesamt.\n\n";

foreach ($inventar as $label => $pakete) {
    echo "## $label — ".count($pakete)." Pakete\n\n";
    echo "| Paket | Version | `php`-Constraint | Herkunft | benutzt? |\n";
    echo "|---|---|---|---|---|\n";

    foreach ($pakete as $name => $a) {
        $php = $a['php'] !== '' ? '`'.str_replace('|', '\|', $a['php']).'`' : '—';
        echo sprintf(
            "| `%s` | %s | %s | %s | %s |\n",
            $name,
            $a['version'],
            $php,
            $a['herkunft'],
            imCodeBenutzt($wurzel, $name) ? 'ja' : '—'
        );
    }
    echo "\n";
}

// ── Die Auffälligkeiten ────────────────────────────────────────────────────────────────

echo "## Geisterpakete\n\n";
echo "Verzeichnisse, die in **keiner** `installed.json` stehen. Von Hand hineinkopiert;\n";
echo "Composer weiss von ihnen nichts, der Autoloader schon.\n\n";
if ($geister === array()) {
    echo "Keine.\n\n";
} else {
    foreach ($geister as $label => $namen) {
        foreach ($namen as $n) {
            echo "- `$n` (in $label)\n";
        }
    }
    echo "\n";
}

echo "## Dubletten — dasselbe Paket in beiden Bäumen\n\n";
if ($dubletten === array()) {
    echo "Keine.\n\n";
} else {
    echo "| Paket | Root | `custom/` | gleicher Major? |\n|---|---|---|---|\n";
    foreach ($dubletten as $n) {
        $r = $inventar['Root'][$n]['version'];
        $c = $inventar['custom/'][$n]['version'];
        $gleich = preg_replace('/^v?(\d+)\..*/', '$1', $r) === preg_replace('/^v?(\d+)\..*/', '$1', $c);
        echo sprintf("| `%s` | %s | %s | %s |\n", $n, $r, $c, $gleich ? 'ja' : '**nein**');
    }
    echo "\n";
}

echo "## Namensraum-Kollisionen im Autoloader\n\n";
echo "Dasselbe PSR-4-Präfix in beiden generierten Autoloadern. **Der Root wird zuerst geladen\n";
echo "und gewinnt** — unabhängig davon, welche Fassung gepflegt ist. Diese Kollisionen sind aus\n";
echo "der Paketliste allein nicht sichtbar.\n\n";
if ($kollision === array()) {
    echo "Keine.\n\n";
} else {
    echo "| Präfix | Root | `custom/` |\n|---|---|---|\n";
    foreach ($kollision as $p) {
        echo sprintf("| `%s` | `%s` | `%s` |\n", rtrim($p, '\\'), $psr4Root[$p], $psr4Custom[$p]);
    }
    echo "\n";
}

// ── PHP-Grenzen ────────────────────────────────────────────────────────────────────────

echo "## Pakete mit einer oberen PHP-Grenze\n\n";
echo "Ein Constraint, der PHP 8.3 **nicht** einschliesst, macht das Paket unter Composer\n";
echo "uninstallierbar — auch wenn der eingefrorene Baum heute läuft.\n\n";
echo "| Paket | Baum | `php`-Constraint | benutzt? |\n|---|---|---|---|\n";

foreach ($inventar as $label => $pakete) {
    foreach ($pakete as $name => $a) {
        if ($a['php'] === '') {
            continue;
        }
        // Grob: enthält der Constraint nirgends eine 8er-Major-Erlaubnis und auch kein
        // offenes >=7? Dann ist er verdächtig. Die genaue Prüfung macht 006-001-0003 mit
        // Composer selbst — hier geht es darum, die Kandidaten zu finden.
        $offen      = (bool) preg_match('/>=\s*[578]/', $a['php']);
        $erlaubtAcht = (bool) preg_match('/\^8|\|\s*\^?8|8\.\d/', $a['php']);

        if (!$offen && !$erlaubtAcht) {
            echo sprintf(
                "| `%s` | %s | `%s` | %s |\n",
                $name, $label,
                str_replace('|', '\|', $a['php']),
                imCodeBenutzt($wurzel, $name) ? '**ja**' : '—'
            );
        }
    }
}
echo "\n";
echo "> Diese Liste ist eine **Vorauswahl anhand der Zeichenkette**, kein Composer-Urteil.\n";
echo "> Ob der Ist-Stack auflösbar ist, beantwortet `006-001-0003` mit einem echten\n";
echo "> Auflösungslauf.\n\n";

// ── Die Frage, an der der Epic-Zuschnitt hängt ─────────────────────────────────────────

echo "## Was Silex pinnt — und was sonst noch im Baum liegt\n\n";
echo "Epic `006` ist darauf zugeschnitten, dass der **Ist-Stack** auflösbar bleibt. Der\n";
echo "Epic-Text begründet das damit, Silex und die Symfony-3.4-Komponenten hätten nach oben\n";
echo "offene `php`-Constraints. Diese Tabelle prüft genau das — und trennt dabei, was der\n";
echo "Epic-Text zusammenwirft: die vier von Silex **gepinnten** Pakete und den Rest.\n\n";

$silexJson = $wurzel.'/vendor/silex/silex/composer.json';
$silexMeta = is_file($silexJson) ? json_decode((string) file_get_contents($silexJson), true) : array();
$gepinnt   = array();
foreach (($silexMeta['require'] ?? array()) as $abhaengig => $constraint) {
    if (strpos($abhaengig, 'symfony/') === 0) {
        $gepinnt[$abhaengig] = $constraint;
    }
}

echo "**Von `silex/silex` gepinnt** (`php: ".($silexMeta['require']['php'] ?? '?')."`):\n\n";
echo "| Paket | Silex verlangt | eigener `php`-Constraint | nach oben offen? |\n|---|---|---|---|\n";
foreach ($gepinnt as $name => $constraint) {
    $php   = $inventar['Root'][$name]['php'] ?? '—';
    $offen = (bool) preg_match('/>=\s*[0-9]/', $php);
    echo sprintf(
        "| `%s` | `%s` | `%s` | %s |\n",
        $name, str_replace('|', '\|', $constraint),
        str_replace('|', '\|', $php),
        $offen ? 'ja' : '**nein**'
    );
}

echo "\n**Weitere Symfony-Pakete im Root-Baum**, die Silex *nicht* pinnt:\n\n";
echo "| Paket | `php`-Constraint | nach oben offen? |\n|---|---|---|\n";
foreach (($inventar['Root'] ?? array()) as $name => $a) {
    if (strpos($name, 'symfony/') !== 0 || isset($gepinnt[$name]) || $a['php'] === '') {
        continue;
    }
    $offen = (bool) preg_match('/>=\s*[0-9]/', $a['php']);
    echo sprintf("| `%s` | `%s` | %s |\n", $name, str_replace('|', '\|', $a['php']), $offen ? 'ja' : '**nein**');
}
echo "\n";
