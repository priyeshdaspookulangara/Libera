<?php
/**
 * manglish_helper.php
 * Standalone Malayalam to Manglish (Latin phonetic transliteration) Engine.
 *
 * Converts Malayalam Unicode strings into normalized lowercase English phonetic representations
 * to enable search in English/Manglish for Malayalam titles and authors.
 *
 * Examples:
 *   "ഇന്ദുലേഖ"    -> "indulekha"
 *   "ചന്തുമേനോൻ"  -> "chandumenon"
 *   "ബഷീർ"       -> "basheer"
 *   "തകഴി"        -> "thakazhi"
 */

function generate_manglish_key($malayalam_text) {
    if ($malayalam_text === null || $malayalam_text === '') {
        return '';
    }

    $text = trim((string)$malayalam_text);
    if ($text === '') return '';

    // If input contains no Malayalam characters, clean and return lowercase Latin
    if (!preg_match('/[\x{0D00}-\x{0D7F}]/u', $text)) {
        $clean = strtolower($text);
        $clean = preg_replace('/[^a-z0-9\s]/', '', $clean);
        return trim(preg_replace('/\s+/', ' ', $clean));
    }

    // List of compound conjuncts where base consonant mapping should not append inherent 'a'
    // when followed by a matra (e.g., ന്ദ + ു = nd + u = ndu, NOT ndau)
    $conjunct_bases = [
        'സ്ത്ത്യ' => 'sthy',
        'ഗ്ദ്ധ'   => 'gddh',
        'ദ്ധ'     => 'ddh',
        'ബ്ധ'     => 'bdh',
        'ന്ധ'     => 'ndh',
        'ന്ഥ'     => 'nth',
        'ണ്ഠ'     => 'ntt',
        'ണ്ഡ'     => 'nd',
        'ഞ്ച'     => 'nch',
        'ഞ്ജ'     => 'nj',
        'ഞ്ഞ'     => 'nn',
        'ങ്ങ'     => 'ng',
        'ങ്ക'     => 'nk',
        'ന്ത'     => 'nth',
        'ന്ദ'     => 'nd',
        'ന്ന'     => 'nn',
        'മ്മ'     => 'mm',
        'യ്യ'     => 'yy',
        'ല്ല'     => 'll',
        'ള്ള'     => 'll',
        'വ്വ'     => 'vv',
        'ശ്ശ'     => 'ss',
        'സ്സ'     => 'ss',
        'ക്ക'     => 'kk',
        'ഗ്ഗ'     => 'gg',
        'ച്ച'     => 'ch',
        'ജ്ജ'     => 'jj',
        'ട്ട'     => 'tt',
        'ണ്ണ'     => 'nn',
        'ത്ത'     => 'tth',
        'പ്പ'     => 'pp',
        'ബ്ബ'     => 'bb',
        'റ്റ'     => 'tt',
        'ന്റ'     => 'nt',
        'ണ്ട'     => 'nd',
        'ക്ട'     => 'kt',
        'ഗ്മ'     => 'gm',
        'ഗ്ന'     => 'gn',
        'ത്മ'     => 'tm',
        'ത്ഭ'     => 'tbh',
        'ന്മ'     => 'nm',
        'പ്ല'     => 'pl',
        'ബ്ല'     => 'bl',
        'ഗ്ല'     => 'gl',
        'ക്ല'     => 'kl',
        'ശ്ല'     => 'sl',
        'ഹ്ല'     => 'hl',
        'ബ്ര'     => 'br',
        'പ്ര'     => 'pr',
        'ത്ര'     => 'thr',
        'ക്ര'     => 'kr',
        'ഗ്ര'     => 'gr',
        'ദ്ര'     => 'dr',
        'ധ്ര'     => 'dhr',
        'ശ്ര'     => 'sr',
        'സ്ര'     => 'sr',
        'ഹൃ'     => 'hru',
        'ത്സ'     => 'ts',
        'ക്ഷ'     => 'ksh',
    ];

    // Dependent vowel signs / Matras
    $matras = [
        'ാ' => 'a',   // \x{0D3E} aa/a
        'ി' => 'i',   // \x{0D3F} i
        'ീ' => 'ee',  // \x{0D40} ee
        'ു' => 'u',   // \x{0D41} u
        'ൂ' => 'oo',  // \x{0D42} oo
        'ൃ' => 'ru',  // \x{0D43} ru
        'ൄ' => 'ru',
        'െ' => 'e',   // \x{0D46} e
        'േ' => 'e',   // \x{0D47} e
        'ൈ' => 'ai',  // \x{0D48} ai
        'ൊ' => 'o',   // \x{0D4A} o
        'ോ' => 'o',   // \x{0D4B} o
        'ൌ' => 'ou',  // \x{0D4C} ou
        'ൗ' => 'ou',  // \x{0D57}
    ];

    // 1. First, replace Conjunct + Matra / Virama
    foreach ($conjunct_bases as $conj => $base) {
        // Conjunct + Virama
        $text = str_replace($conj . '്', $base, $text);
        // Conjunct + Matra
        foreach ($matras as $m_mal => $m_eng) {
            $text = str_replace($conj . $m_mal, $base . $m_eng, $text);
        }
        // Standalone conjunct (with inherent 'a')
        $text = str_replace($conj, $base . 'a', $text);
    }

    // Handle conversational phonetic variants:
    // e.g. "anthu" -> "andu" (for Chandu, Indu)
    $text = str_replace('nthu', 'ndu', $text);

    // 2. Chillu characters (Malayalam pure consonants without vowel)
    $chillus = [
        "\xE0\xB4\xA3\xE0\xB5\x8D\xE2\x80\x8D" => 'n',  // ൺ
        "\xE0\xB4\xA8\xE0\xB5\x8D\xE2\x80\x8D" => 'n',  // ൻ
        "\xE0\xB4\xB0\xE0\xB5\x8D\xE2\x80\x8D" => 'r',  // ർ
        "\xE0\xB4\xB2\xE0\xB5\x8D\xE2\x80\x8D" => 'l',  // ൽ
        "\xE0\xB4\xB3\xE0\xB5\x8D\xE2\x80\x8D" => 'l',  // ൾ
        "\xE0\xB4\x95\xE0\xB5\x8D\xE2\x80\x8D" => 'k',  // ൿ
        'ൺ' => 'n', // U+0D7A
        'ൻ' => 'n', // U+0D7B
        'ർ' => 'r', // U+0D7C
        'ൽ' => 'l', // U+0D7D
        'ൾ' => 'l', // U+0D7E
        'ൿ' => 'k', // U+0D7F
    ];

    foreach ($chillus as $mal => $eng) {
        $text = str_replace($mal, $eng, $text);
    }

    // 3. Standalone independent vowels
    $vowels = [
        'അ' => 'a',
        'ആ' => 'a',
        'ഇ' => 'i',
        'ഈ' => 'ee',
        'ഉ' => 'u',
        'ഊ' => 'oo',
        'ഋ' => 'ru',
        'ൠ' => 'ru',
        'ഌ' => 'lu',
        'ൡ' => 'lu',
        'എ' => 'e',
        'ഏ' => 'e',
        'ഐ' => 'ai',
        'ഒ' => 'o',
        'ഓ' => 'o',
        'ഔ' => 'ou',
    ];

    foreach ($vowels as $mal => $eng) {
        $text = str_replace($mal, $eng, $text);
    }

    // 4. Special signs: Anusvara (ം) and Visarga (ഃ)
    $text = str_replace('ം', 'm', $text);
    $text = str_replace('ഃ', 'h', $text);

    // 5. Base consonants
    $consonants = [
        'ക' => 'k',
        'ഖ' => 'kh',
        'ഗ' => 'g',
        'ഘ' => 'gh',
        'ങ' => 'ng',
        'ച' => 'ch',
        'ഛ' => 'chh',
        'ജ' => 'j',
        'ഝ' => 'jh',
        'ഞ' => 'nj',
        'ട' => 't',
        'ഠ' => 'th',
        'ഡ' => 'd',
        'ഢ' => 'dh',
        'ണ' => 'n',
        'ത' => 'th',
        'ഥ' => 'th',
        'ദ' => 'd',
        'ധ' => 'dh',
        'ന' => 'n',
        'പ' => 'p',
        'ഫ' => 'ph',
        'ബ' => 'b',
        'ഭ' => 'bh',
        'മ' => 'm',
        'യ' => 'y',
        'ര' => 'r',
        'ല' => 'l',
        'വ' => 'v',
        'ശ' => 's',
        'ഷ' => 'sh',
        'സ' => 's',
        'ഹ' => 'h',
        'ള' => 'l',
        'ഴ' => 'zh',
        'റ' => 'r',
    ];

    // Consonant + Virama (്)
    foreach ($consonants as $c_mal => $c_eng) {
        $text = str_replace($c_mal . '്', $c_eng, $text);
    }

    // Consonant + Matra
    foreach ($consonants as $c_mal => $c_eng) {
        foreach ($matras as $m_mal => $m_eng) {
            $text = str_replace($c_mal . $m_mal, $c_eng . $m_eng, $text);
        }
    }

    // Consonant standalone with inherent 'a'
    foreach ($consonants as $c_mal => $c_eng) {
        $text = str_replace($c_mal, $c_eng . 'a', $text);
    }

    // Clean remaining matras / virama
    foreach ($matras as $m_mal => $m_eng) {
        $text = str_replace($m_mal, $m_eng, $text);
    }
    $text = str_replace('്', '', $text);

    // Normalize lowercase and alphanumeric
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s]/', '', $text);
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}
