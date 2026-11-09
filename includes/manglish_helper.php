<?php
/**
 * manglish_helper.php
 * Standalone Bidirectional Malayalam <-> Manglish Phonetic Transliteration Engine.
 *
 * Capabilities:
 *   1. Malayalam -> Manglish (`generate_manglish_key`):
 *      Converts Malayalam Unicode strings into normalized lowercase Latin phonetic keys
 *      stored in `title_search_key` and `author_search_key`.
 *      Examples:
 *        "കുരുവികൾ"    -> "kuruvikal kuruvigal"
 *        "മിശ്രവിവാഹം"  -> "mishravivaham misravivaham"
 *        "തകഴി"        -> "thakazhi thakali takazhi"
 *
 *   2. Manglish -> Malayalam Unicode (`manglish_to_malayalam_terms`):
 *      Converts Latin Manglish search queries into candidate Malayalam Unicode strings.
 *      This allows searching "kuruvikal" to DIRECTLY MATCH `b.BookName LIKE '%കുരുവികൾ%'`
 *      in MySQL EVEN IF `title_search_key` has NOT been populated in the database!
 *
 *   3. Universal Query Variations (`get_manglish_search_variations`):
 *      Returns both phonetic Latin variations and candidate Malayalam Unicode strings
 *      for any search query.
 */

if (!function_exists('manglish_to_malayalam_terms')) {
function manglish_to_malayalam_terms($input) {
    $text = strtolower(trim((string)$input));
    if ($text === '') return [];

    // If input already contains Malayalam characters, return as is
    if (preg_match('/[\x{0D00}-\x{0D7F}]/u', $text)) {
        return [$text];
    }

    $clean = preg_replace('/[^a-z0-9]/', '', $text);
    if ($clean === '') return [];

    $results = [];

    // 1. High frequency and literature master dictionary
    $dict = [
        'kuruvi' => ['കുരുവി'],
        'kuruvikal' => ['കുരുവികൾ', 'കുരുവികള്‍'],
        'kuruvigal' => ['കുരുവികൾ', 'കുരുവികള്‍'],
        'thakazhi' => ['തകഴി'],
        'thakali' => ['തകഴി'],
        'takazhi' => ['തകഴി'],
        'sivasankara' => ['ശിവശങ്കര'],
        'shivashankara' => ['ശിവശങ്കര'],
        'pillai' => ['പിള്ള'],
        'pilla' => ['പിള്ള'],
        'chemmeen' => ['ചെമ്മീൻ'],
        'chemmin' => ['ചെമ്മീൻ'],
        'mish' => ['മിശ്ര', 'മിശ'],
        'mishra' => ['മിശ്ര'],
        'misra' => ['മിശ്ര'],
        'vivaham' => ['വിവാഹം'],
        'mishravivaham' => ['മിശ്രവിവാഹം'],
        'misravivaham' => ['മിശ്രവിവാഹം'],
        'balyakalasakhi' => ['ബാല്യകാലസഖി'],
        'balyakala' => ['ബാല്യകാല'],
        'sakhi' => ['സഖി'],
        'indulekha' => ['ഇന്ദുലേഖ'],
        'chandu' => ['ചന്തു'],
        'menon' => ['മേനോൻ'],
        'basheer' => ['ബഷീർ'],
        'basir' => ['ബഷീർ'],
        'vaikom' => ['വൈക്കം'],
        'muhammad' => ['മുഹമ്മദ്'],
        'kerala' => ['കേരള'],
        'sahithyam' => ['സാഹിത്യം'],
        'kavitha' => ['കവിത'],
        'kadha' => ['കഥ'],
        'katha' => ['കഥ'],
        'noval' => ['നോവൽ'],
        'novel' => ['നോവൽ'],
        'charithram' => ['ചരിത്രം'],
        'padavali' => ['പാഠാവലി'],
        'njan' => ['ഞാൻ'],
        'ente' => ['എന്റെ'],
        'ammayum' => ['അമ്മയും'],
        'amma' => ['അമ്മ'],
        'achan' => ['അച്ഛൻ'],
    ];

    if (isset($dict[$clean])) {
        foreach ($dict[$clean] as $term) {
            $results[] = $term;
        }
    }

    // 2. Dynamic Algorithmic Syllabic Transliterator (Latin -> Malayalam Unicode)
    $consonants = [
        'shsh' => 'ശ്ശ', 'ss' => 'സ്സ', 'tth' => 'ത്ത', 'nth' => 'ന്ത', 'ndh' => 'ന്ധ',
        'nd' => 'ന്ദ', 'nt' => 'ന്റ', 'nch' => 'ഞ്ച', 'nj' => 'ഞ്ജ', 'ng' => 'ങ്ങ',
        'nk' => 'ങ്ക', 'mm' => 'മ്മ', 'nn' => 'ന്ന', 'kk' => 'ക്ക', 'gg' => 'ഗ്ഗ',
        'ch' => 'ച', 'jh' => 'ഝ', 'tt' => 'ട്ട', 'pp' => 'പ്പ', 'bb' => 'ബ്ബ',
        'll' => 'ള്ള', 'vv' => 'വ്വ', 'yy' => 'യ്യ', 'ksh' => 'ക്ഷ', 'thr' => 'ത്ര',
        'shr' => 'ശ്ര', 'sr' => 'സ്ര', 'pr' => 'പ്ര', 'br' => 'ബ്ര', 'kr' => 'ക്ര',
        'gr' => 'ഗ്ര', 'dr' => 'ദ്ര', 'dhr' => 'ധ്ര', 'kh' => 'ഖ', 'gh' => 'ഘ',
        'th' => 'ത', 'dh' => 'ധ', 'ph' => 'ഫ', 'bh' => 'ഭ', 'sh' => 'ശ',
        'zh' => 'ഴ', 'k' => 'ക', 'g' => 'ഗ', 'j' => 'ജ', 't' => 'ത',
        'd' => 'ദ', 'n' => 'ന', 'p' => 'പ', 'b' => 'ബ', 'm' => 'മ',
        'y' => 'യ', 'r' => 'ര', 'l' => 'ല', 'v' => 'വ', 'w' => 'വ',
        's' => 'സ', 'h' => 'ഹ',
    ];

    $matras = [
        'aa' => 'ാ', 'a' => '',
        'ee' => 'ീ', 'i' => 'ി',
        'oo' => 'ൂ', 'u' => 'ു',
        'ai' => 'ൈ',
        'au' => 'ൗ', 'ou' => 'ൗ',
        'e' => 'െ',
        'o' => 'ൊ',
    ];

    $init_vowels = [
        'aa' => 'ആ', 'a' => 'അ',
        'ee' => 'ഈ', 'i' => 'ഇ',
        'oo' => 'ഊ', 'u' => 'ഉ',
        'ai' => 'ഐ',
        'au' => 'ഔ', 'ou' => 'ഔ',
        'e' => 'എ',
        'o' => 'ഒ',
    ];

    $chillus = [
        'l' => 'ൾ',
        'r' => 'ർ',
        'n' => 'ൻ',
        'm' => 'ം',
    ];

    $len = strlen($clean);
    $pos = 0;
    $out = '';

    while ($pos < $len) {
        // Handle plural endings: "-kal" or "-gal" -> "കൾ"
        if (substr($clean, $pos) === 'kal' || substr($clean, $pos) === 'gal') {
            $out .= 'കൾ';
            $pos += 3;
            continue;
        }

        $matched = false;

        // Try consonants
        foreach ($consonants as $latin => $c_uni) {
            $l_len = strlen($latin);
            if (substr($clean, $pos, $l_len) === $latin) {
                // If it is at the very end of word and can be a chillu:
                if ($pos + $l_len === $len && isset($chillus[$latin])) {
                    $out .= $chillus[$latin];
                    $pos += $l_len;
                    $matched = true;
                    break;
                }

                // Check next token for vowel / matra
                $v_matched = false;
                foreach ($matras as $vlatin => $m_uni) {
                    $v_len = strlen($vlatin);
                    if (substr($clean, $pos + $l_len, $v_len) === $vlatin) {
                        $out .= $c_uni . $m_uni;
                        $pos += $l_len + $v_len;
                        $v_matched = true;
                        $matched = true;
                        break;
                    }
                }
                if ($v_matched) break;

                // Consonant followed by another consonant -> virama (chandrakkala)
                $out .= $c_uni . '്';
                $pos += $l_len;
                $matched = true;
                break;
            }
        }
        if ($matched) continue;

        // Try initial vowels
        foreach ($init_vowels as $vlatin => $v_uni) {
            $v_len = strlen($vlatin);
            if (substr($clean, $pos, $v_len) === $vlatin) {
                $out .= $v_uni;
                $pos += $v_len;
                $matched = true;
                break;
            }
        }
        if ($matched) continue;

        // Default: copy
        $out .= $clean[$pos];
        $pos++;
    }

    if ($out !== '') {
        $results[] = $out;
    }

    return array_values(array_unique(array_filter($results)));
}
}

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

    // Transliterate function with flags for phonetic variants
    $transliterate = function($src, $sha_is_sh = true, $kal_is_kal = true, $zha_is_zh = true) {
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

        // Replace common plural suffixes like "കൾ" or "കള്‍" (kal vs gal)
        if (!$kal_is_kal) {
            $src = str_replace('കൾ', 'ഗൾ', $src);
            $src = str_replace('കള്‍', 'ഗള്‍', $src);
        }

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

        // 2. Chillu characters (both legacy and atomic Unicode)
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
            'ഹ' => 'h',
            'ള' => 'l',
            'ഴ' => $zha_is_zh ? 'zh' : 'l',
            'റ' => 'r',
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

    $keys = [];
    $keys[] = $transliterate($text, true, true, true);   // Standard (sh, kal, zh)
    $keys[] = $transliterate($text, false, true, true);  // s variant
    $keys[] = $transliterate($text, true, false, true);  // gal variant
    $keys[] = $transliterate($text, true, true, false);  // l variant for zh

    $unique_keys = array_values(array_unique(array_filter($keys)));
    return implode(' ', $unique_keys);
}
}

/**
 * Generates search query variations for flexible Manglish and Malayalam searching.
 * Returns both Latin Manglish variations and Candidate Malayalam Unicode strings!
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
        // Latin / English search query:
        // 1. Generate Latin phonetic variations
        $clean = strtolower($search);
        $clean = preg_replace('/[^a-z0-9\s]/', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));
        if ($clean !== '') $variations[] = $clean;

        $queue = [$clean];

        // Phonetic interchange for "sh" <-> "s"
        if (strpos($clean, 'sh') !== false) {
            $queue[] = str_replace('sh', 's', $clean);
        } elseif (strpos($clean, 's') !== false) {
            $queue[] = str_replace('s', 'sh', $clean);
        }

        // Plural suffix "-kal" <-> "-gal" / root extraction
        foreach ($queue as $item) {
            if (preg_match('/kal$/', $item)) {
                $queue[] = preg_replace('/kal$/', 'gal', $item);
                $queue[] = preg_replace('/kal$/', '', $item); // root: kuruvi
            } elseif (preg_match('/gal$/', $item)) {
                $queue[] = preg_replace('/gal$/', 'kal', $item);
                $queue[] = preg_replace('/gal$/', '', $item); // root: kuruvi
            }
        }

        // Phonetic interchange for "zh" <-> "l"
        foreach ($queue as $item) {
            if (strpos($item, 'zh') !== false) {
                $queue[] = str_replace('zh', 'l', $item);
            }
        }

        // Phonetic interchange for "th" <-> "t"
        foreach ($queue as $item) {
            if (strpos($item, 'th') !== false) {
                $queue[] = str_replace('th', 't', $item);
            }
        }

        // Interchange for "v" <-> "w"
        foreach ($queue as $item) {
            if (strpos($item, 'w') !== false) {
                $queue[] = str_replace('w', 'v', $item);
            } elseif (strpos($item, 'v') !== false) {
                $queue[] = str_replace('v', 'w', $item);
            }
        }

        foreach ($queue as $q_item) {
            $q_clean = trim($q_item);
            if ($q_clean !== '') {
                $variations[] = $q_clean;
            }
        }

        // 2. Generate Candidate Malayalam Unicode strings (Reverse transliteration)
        // so that query directly matches BookName even if search keys are null in DB!
        $mal_candidates = manglish_to_malayalam_terms($clean);
        foreach ($mal_candidates as $m_term) {
            if (!empty($m_term)) {
                $variations[] = $m_term;
            }
        }
    }

    return array_values(array_unique(array_filter($variations)));
}
}
