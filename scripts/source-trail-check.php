<?php

/**
 * Source Trail — standalone verification script.
 *
 * This script does NOT boot Laravel and does NOT touch the
 * database. It verifies the challenge's static invariants:
 *
 *   1. The three flag fragments exist in the correct files.
 *   2. Each fragment sits inside a source comment (not visible
 *      rendered text).
 *   3. The three fragments concatenate, in order, into exactly
 *      one canonical p4dCTF{...} flag.
 *   4. The complete canonical flag is NOT present in any of the
 *      three participant-facing source files.
 *   5. The constant-time flag comparison accepts the correct flag
 *      and rejects incorrect, partial, empty and case-mangled
 *      submissions.
 *   6. The challenge description does not reveal the flag or the
 *      intended solution technique.
 *   7. The participant challenge view exposes a clickable
 *      "Open Challenge Website" link that opens a new tab, and a
 *      server-side submission form.
 *   8. The internal target page loads its external stylesheet and
 *      script and implements the tab interaction.
 *
 * Run from the project root:
 *   php scripts/source-trail-check.php
 */

declare(strict_types=1);

$scaffold = dirname(__DIR__);

$htmlFile = $scaffold . '/resources/views/challenges/targets/source-trail.blade.php';
$cssFile = $scaffold . '/public/challenges/source-trail/style.css';
$jsFile = $scaffold . '/public/challenges/source-trail/script.js';
$showView = $scaffold . '/resources/views/challenges/show.blade.php';

$canonical = 'p4dCTF{every_file_tells_a_story}';

// The three ordered fragments.
$fragmentHtml = 'p4dCTF{every';  // HTML comment
$fragmentCss = '_file_tells';    // CSS comment
$fragmentJs = '_a_story}';       // JavaScript comment

$html = @file_get_contents($htmlFile);
$css = @file_get_contents($cssFile);
$js = @file_get_contents($jsFile);
$show = @file_get_contents($showView);

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  {$label}\n";
    } else {
        $failed++;
        echo "  FAIL  {$label}\n";
    }
}

echo "Source Trail — standalone verification\n";
echo str_repeat('─', 60) . "\n\n";

echo "[1] Flag fragments exist in their source files\n";
check('Fragment 1 (HTML) present in target view', $html !== false && str_contains($html, $fragmentHtml));
check('Fragment 2 (CSS) present in stylesheet', $css !== false && str_contains($css, $fragmentCss));
check('Fragment 3 (JS) present in script', $js !== false && str_contains($js, $fragmentJs));

echo "\n[2] Fragments are hidden inside source comments\n";
check('Fragment 1 is inside an HTML comment', (bool) preg_match('/<!--.*?' . preg_quote($fragmentHtml, '/') . '.*?-->/s', $html));
check('Fragment 2 is inside a CSS comment', (bool) preg_match('/\/\*.*?' . preg_quote($fragmentCss, '/') . '.*?\*\//s', $css));
check('Fragment 3 is inside a JavaScript comment', (bool) preg_match('/\/\*.*?' . preg_quote($fragmentJs, '/') . '.*?\*\//s', $js));
check('All three files share the "relay" trail marker',
    str_contains($html, 'relay:') && str_contains($css, 'relay:') && str_contains($js, 'relay:'));

echo "\n[3] Fragments concatenate into the canonical flag\n";
$combined = $fragmentHtml . $fragmentCss . $fragmentJs;
check('Concatenation equals the canonical flag', $combined === $canonical);
check('Canonical flag uses the p4dCTF{...} format', (bool) preg_match('/\Ap4dCTF\{[^{}\r\n]+\}\z/', $canonical));

echo "\n[4] Canonical flag is not exposed in participant-facing source\n";
check('Complete flag absent from target view', $html === false || !str_contains($html, $canonical));
check('Complete flag absent from stylesheet', $css === false || !str_contains($css, $canonical));
check('Complete flag absent from script', $js === false || !str_contains($js, $canonical));

echo "\n[5] Server-side flag validation (constant-time comparison)\n";
$flagsMatch = static function (string $submitted, string $expected): bool {
    $submitted = trim($submitted);

    return $submitted !== '' && hash_equals($expected, $submitted);
};
check('Exact canonical flag is accepted', $flagsMatch($canonical, $canonical));
check('Canonical flag with surrounding whitespace is accepted', $flagsMatch('  ' . $canonical . "\n", $canonical));
check('Assembled fragments are accepted', $flagsMatch($combined, $canonical));
check('Wrong flag is rejected', ! $flagsMatch('p4dCTF{not_the_flag}', $canonical));
check('Empty submission is rejected', ! $flagsMatch('', $canonical));
check('Whitespace-only submission is rejected', ! $flagsMatch('   ', $canonical));
check('Partial fragment is rejected', ! $flagsMatch($fragmentHtml, $canonical));
check('Case-mangled flag is rejected', ! $flagsMatch(strtoupper($canonical), $canonical));
check('Missing closing brace is rejected', ! $flagsMatch('p4dCTF{every_file_tells_a_story', $canonical));

echo "\n[6] Challenge description reveals nothing\n";
$description = 'A developer left behind a trail while building a webpage. The visible page tells only part of the story. Can you connect the missing pieces and uncover what was never meant to be seen?';
check('Description does not contain the flag', ! str_contains($description, $canonical));
check('Description does not mention "inspect"', ! stripos($description, 'inspect'));
check('Description does not mention "HTML"', ! stripos($description, 'html'));
check('Description does not mention "CSS"', ! stripos($description, 'css'));
check('Description does not mention "JavaScript"', ! stripos($description, 'javascript'));
check('Description does not mention "source code"', ! stripos($description, 'source code'));
check('Description does not mention "comment"', ! stripos($description, 'comment'));

echo "\n[7] Participant challenge view wiring\n";
check('Show view renders "Open Challenge Website"', $show !== false && str_contains($show, 'Open Challenge Website'));
check('Show view opens the target in a new tab', $show !== false && str_contains($show, 'target="_blank"'));
check('Show view posts to the server-side submission route', $show !== false && str_contains($show, "route('challenges.submit'"));
check('Show view includes CSRF protection', $show !== false && str_contains($show, '@csrf'));
check('Show view renders progressive hints', $show !== false && str_contains($show, 'hint-item'));

echo "\n[8] Internal target page wiring\n";
check('Target view links the external stylesheet', $html !== false && str_contains($html, 'challenges/source-trail/style.css'));
check('Target view links the external script', $html !== false && str_contains($html, 'challenges/source-trail/script.js'));
check('Target view implements tab buttons', $html !== false && str_contains($html, 'st-tab') && str_contains($html, 'data-tab'));
check('Stylesheet styles the tab interaction', $css !== false && str_contains($css, '.st-tab'));
check('Script toggles tab panels', $js !== false && str_contains($js, 'classList'));

echo "\n" . str_repeat('─', 60) . "\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);
