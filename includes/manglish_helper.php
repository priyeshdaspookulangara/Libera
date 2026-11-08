<?php
/**
 * manglish_helper.php
 * Standalone Malayalam to Manglish (Latin phonetic transliteration) Engine.
 *
 * Converts Malayalam Unicode strings into normalized lowercase English phonetic representations
 * to enable search in English/Manglish for Malayalam titles and authors.
 *
 * Supports dual-phonetic variants for Malayalam phonemes like "ശ" / "ശ്ര" / "ഷ"
 * (e.g., matching both "sh" and "s", so "മിശ്രവിവാഹം" matches both "mish" and "mis").
 *
 * Examples:
 *   "മിശ്രവിവാഹം"  -> "mishravivaham misravivaham"
 *   "ഇന്ദുലേഖ"    -> "indulekha"
 *   "ചന്തുമേനോൻ"  -> "chandumenon"
 *   "ബഷീർ"       -> "basheer"
 *   "തകഴി"        -> "thakazhi"
 */

if (!function_exists('generate_manglish_key')) {
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

    // Transliterate function with flag for treating "ശ" / "ശ്ര" as "sh" or "s"
    $transliterate = function($src, $sha_is_sh = true) {
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
            'ശ്ശ'     => $sha_is_sh ? 'shsh' : 'ss',
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
            'ശ്ല'     => $sha_is_sh ? 'shl' : 'sl',
            'ഹ്ല'     => 'hl',
            'ബ്ര'     => 'br',
            'പ്ര'     => 'pr',
            'ത്ര'     => 'thr',
            'ക്ര'     => 'kr',
            'ഗ്ര'     => 'gr',
            'ദ്ര'     => 'dr',
            'ധ്ര'     => 'dhr',
            'ശ്ര'     => $sha_is_sh ? 'shr' : 'sr',
            'സ്ര'     => 'sr',
            'ഹൃ'     => 'hru',
            'ത്സ'     => 'ts',
            'ക്ഷ'     => 'ksh',
        ];

        // Dependent vowel signs / Matras
        $matras = [
            'ാ' => 'a',   // aa/a
            'ി' => 'i',   // i
            'ീ' => 'ee',  // ee
            'ു' => 'u',   // u
            'ൂ' => 'oo',  // oo
            'ൃ' => 'ru',  // ru
            'ൄ' => 'ru',
            'െ' => 'e',   // e
            'േ' => 'e',   // e
            'ൈ' => 'ai',  // ai
            'ൊ' => 'o',   // o
            'ോ' => 'o',   // o
            'ൌ' => 'ou',  // ou
            'ൗ' => 'ou',
        ];

        // 1. Replace Conjunct + Matra / Virama
        foreach ($conjunct_bases as $conj => $base) {
            $src = str_replace($conj . '്', $base, $src);
            foreach ($matras as $m_mal => $m_eng) {
                $src = str_replace($conj . $m_mal, $base . $m_eng, $src);
            }
            $src = str_replace($conj, $base . 'a', $src);
        }

        // Conversational phonetic variants
        $src = str_replace('nthu', 'ndu', $src);

        // 2. Chillu characters
        $chillus = [
            "\xE0\xB4\xA3\xE0\xB5\x8D\xE2\x80\x8D" => 'n',  // ൺ
            "\xE0\xB4\xA8\xE0\xB5\x8D\xE2\x80\x8D" => 'n',  // ൻ
            "\xE0\xB4\xB0\xE0\xB5\x8D\xE2\x80\x8D" => 'r',  // ർ
            "\xE0\xB4\xB2\xE0\xB5\x8D\xE2\x80\x8D" => 'l',  // ൽ
            "\xE0\xB4\xB3\xE0\xB5\x8D\xE2\x80\x8D" => 'l',  // ൾ
            "\xE0\xB4\x95\xE0\xB5\x8D\xE2\x80\x8D" => 'k',  // ൿ
            'ൺ' => 'n',
            'ൻ' => 'n',
            'ർ' => 'r',
            'ൽ' => 'l',
            'ൾ' => 'l',
            'ൿ' => 'k',
        ];
        foreach ($chillus as $mal => $eng) {
            $src = str_replace($mal, $eng, $src);
        }

        // 3. Independent vowels
        $vowels = [
            'അ' => 'a', 'ആ' => 'a', 'ഇ' => 'i', 'ഈ' => 'ee',
            'ഉ' => 'u', 'ഊ' => 'oo', 'ഋ' => 'ru', 'ൠ' => 'ru',
            'ഌ' => 'lu', 'ൡ' => 'lu', 'എ' => 'e', 'ഏ' => 'e',
            'ഐ' => 'ai', 'ഒ' => 'o', 'ഓ' => 'o', 'ഔ' => 'ou',
        ];
        foreach ($vowels as $mal => $eng) {
            $src = str_replace($mal, $eng, $src);
        }

        // 4. Special signs: Anusvara (ം) and Visarga (ഃ)
        $src = str_replace('ം', 'm', $src);
        $src = str_replace('ഃ', 'h', $src);

        // 5. Base consonants
        $consonants = [
            'ക' => 'k', 'ഖ' => 'kh', 'ഗ' => 'g', 'ഘ' => 'gh', 'ങ' => 'ng',
            'ച' => 'ch', 'ഛ' => 'chh', 'ജ' => 'j', 'ഝ' => 'jh', 'ഞ' => 'nj',
            'ട' => 't', 'ഠ' => 'th', 'ഡ' => 'd', 'ഢ' => 'dh', 'ണ' => 'n',
            'ത' => 'th', 'ഥ' => 'th', 'ദ' => 'd', 'ധ' => 'dh', 'ന' => 'n',
            'പ' => 'p', 'ഫ' => 'ph', 'ബ' => 'b', 'ഭ' => 'bh', 'മ' => 'm',
            'യ' => 'y', 'ര' => 'r', 'ല' => 'l', 'വ' => 'v',
            'ശ' => $sha_is_sh ? 'sh' : 's',
            'ഷ' => 'sh',
            'സ' => 's',
            'ഹ' => 'h', 'ള' => 'l', 'ഴ' => 'zh', 'റ' => 'r',
        ];

        // Consonant + Virama
        foreach ($consonants as $c_mal => $c_eng) {
            $src = str_replace($c_mal . '്', $c_eng, $src);
        }

        // Consonant + Matra
        foreach ($consonants as $c_mal => $c_eng) {
            foreach ($matras as $m_mal => $m_eng) {
                $src = str_replace($c_mal . $m_mal, $c_eng . $m_eng, $src);
            }
        }

        // Consonant standalone with inherent 'a'
        foreach ($consonants as $c_mal => $c_eng) {
            $src = str_replace($c_mal, $c_eng . 'a', $src);
        }

        // Clean remaining matras / virama
        foreach ($matras as $m_mal => $m_eng) {
            $src = str_replace($m_mal, $m_eng, $src);
        }
        $src = str_replace('്', '', $src);

        // Normalize lowercase and alphanumeric
        $src = strtolower($src);
        $src = preg_replace('/[^a-z0-9\s]/', '', $src);
        return trim(preg_replace('/\s+/', ' ', $src));
    };

    $key_sh = $transliterate($text, true);
    $key_s  = $transliterate($text, false);

    // If both variants are identical, return single key; otherwise return both space-delimited
    if ($key_sh === $key_s) {
        return $key_sh;
    }
    return $key_sh . ' ' . $key_s;
}
}

/**
 * Generates search query variations for flexible Manglish and Malayalam searching.
 * e.g., "mish" -> ["mish", "mis"]
 *       "mis"  -> ["mis", "mish"]
 *       "മിശ്ര" -> ["മിശ്ര", "mishra", "misra"]
 */
if (!function_exists('get_manglish_search_variations')) {
function get_manglish_search_variations($search_text) {
    $search = trim((string)$search_text);
    if ($search === '') return [];

    $variations = [$search];

    if (preg_match('/[\x{0D00}-\x{0D7F}]/u', $search)) {
        // Malayalam Unicode input: transliterate to Manglish variants
        $trans = generate_manglish_key($search);
        foreach (explode(' ', $trans) as $part) {
            $part = trim($part);
            if ($part !== '') $variations[] = $part;
        }
    } else {
        // Latin / English search query
        $clean = strtolower($search);
        $clean = preg_replace('/[^a-z0-9\s]/', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        if ($clean !== '') $variations[] = $clean;

        // Phonetic interchange for "sh" <-> "s"
        if (strpos($clean, 'sh') !== false) {
            $variations[] = str_replace('sh', 's', $clean);
        } elseif (strpos($clean, 's') !== false) {
            $variations[] = str_replace('s', 'sh', $clean);
        }
    }

    return array_values(array_unique(array_filter($variations)));
}
}
