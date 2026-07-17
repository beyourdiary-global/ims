<?php

if (!function_exists('getImportOptionList')) {
    function getImportOptionList($tblName, $labelField, $dbConnect)
    {
        $list = [];
        $tblName = mysqli_real_escape_string($dbConnect, $tblName);
        $labelField = mysqli_real_escape_string($dbConnect, $labelField);
        $query = "SELECT id, `$labelField` AS option_label FROM `$tblName` WHERE status = 'A' ORDER BY `$labelField` ASC";
        $result = mysqli_query($dbConnect, $query);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $list[$row['id']] = $row['option_label'];
            }
        }

        return $list;
    }
}

if (!function_exists('normalizeImportText')) {
    function normalizeImportText($text)
    {
        $text = html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Some Shopline Type3 fonts map common Chinese characters to Kangxi
        // radical code points. Normalize the radicals used by the website
        // order PDFs back to their normal CJK characters.
        $text = strtr($text, array(
            '⼀' => '一',
            '⼝' => '口',
        ));
        $normalized = preg_replace('/\s+/u', ' ', $text);
        if ($normalized === null) {
            $normalized = preg_replace('/[\x00-\x20]+/', ' ', $text);
        }
        return trim((string) $normalized);
    }
}

if (!function_exists('normalizeImportLookup')) {
    function normalizeImportLookup($text)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9]+/', '', normalizeImportText($text)));
    }
}

if (!function_exists('cleanPdfTextOperand')) {
    function cleanPdfTextOperand($text)
    {
        $text = str_replace("\x00", '', (string) $text);
        $text = strtr($text, array(
            '\\n' => ' ',
            '\\r' => ' ',
            '\\t' => ' ',
            '\\(' => '(',
            '\\)' => ')',
            '\\\\' => '\\',
        ));

        // Keep valid UTF-8 characters such as Chinese names and addresses.
        // The previous POSIX [:print:] filter treated each UTF-8 byte as a
        // non-printable character and replaced the original characters with
        // spaces before the importer could parse them.
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', ' ', $text);
        return normalizeImportText($text === null ? '' : $text);
    }
}

if (!function_exists('importPdfHexToUtf8')) {
    function importPdfHexToUtf8($hex)
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', (string) $hex);
        if ($hex === '') {
            return '';
        }
        if ((strlen($hex) % 2) === 1) {
            $hex .= '0';
        }

        $binary = @hex2bin($hex);
        if ($binary === false) {
            return '';
        }
        if (strlen($hex) > 2 && (strlen($hex) % 4) === 0 && function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($binary, 'UTF-8', 'UTF-16BE');
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }

        return cleanPdfTextOperand($binary);
    }
}

if (!function_exists('importPdfIncrementHex')) {
    function importPdfIncrementHex($hex, $step)
    {
        $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', (string) $hex));
        $step = (int) $step;
        if ($hex === '' || $step < 0 || strlen($hex) > 8) {
            return '';
        }

        return strtoupper(str_pad(dechex(hexdec($hex) + $step), strlen($hex), '0', STR_PAD_LEFT));
    }
}

if (!function_exists('importPdfExtractObjectStream')) {
    function importPdfExtractObjectStream($object)
    {
        $object = (string) $object;
        if (!preg_match('/stream[ \t]*\r?\n/', $object, $streamMatch, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $streamStart = (int) $streamMatch[0][1] + strlen($streamMatch[0][0]);
        if (preg_match('/\/Length\s+(\d+)\b/i', $object, $lengthMatch)) {
            $length = (int) $lengthMatch[1];
            if ($length >= 0 && $streamStart + $length <= strlen($object)) {
                return substr($object, $streamStart, $length);
            }
        }

        $streamBody = substr($object, $streamStart);
        $endPosition = strpos($streamBody, "\nendstream");
        if ($endPosition === false) {
            $endPosition = strpos($streamBody, "\rendstream");
        }
        return $endPosition === false ? false : substr($streamBody, 0, $endPosition);
    }
}

if (!function_exists('importPdfExtractObjects')) {
    function importPdfExtractObjects($content)
    {
        $content = (string) $content;
        $objects = array();
        if ($content === '') {
            return $objects;
        }

        $objectStarts = array();
        $offset = 0;
        foreach (explode("\n", $content) as $line) {
            $headerLine = rtrim($line, "\r");
            if (preg_match('/^([0-9]+)[ \t]+[0-9]+[ \t]+obj(?:[ \t]|$)/', $headerLine, $headerMatch)) {
                $objectStarts[] = array(
                    'id' => (string) $headerMatch[1],
                    'offset' => $offset,
                );
            }
            $offset += strlen($line) + 1;
        }

        $objectCount = count($objectStarts);
        for ($index = 0; $index < $objectCount; $index++) {
            $objectId = $objectStarts[$index]['id'];
            $objectStart = (int) $objectStarts[$index]['offset'];
            $nextObjectStart = $index + 1 < $objectCount
                ? (int) $objectStarts[$index + 1]['offset']
                : strlen($content);
            if ($nextObjectStart <= $objectStart) {
                continue;
            }

            $objects[$objectId] = substr($content, $objectStart, $nextObjectStart - $objectStart);
        }

        return $objects;
    }
}

if (!function_exists('importPdfExtractStreams')) {
    function importPdfExtractStreams($content)
    {
        $streams = array();
        foreach (importPdfExtractObjects($content) as $object) {
            $stream = importPdfExtractObjectStream($object);
            if ($stream !== false) {
                $streams[] = $stream;
            }
        }
        return $streams;
    }
}

if (!function_exists('importPdfParseUnicodeMap')) {
    function importPdfParseUnicodeMap($decoded)
    {
        $map = array();
        $codeLengths = array();
        $decoded = (string) $decoded;

        if (preg_match_all('/beginbfchar(.*?)endbfchar/si', $decoded, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (!preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($pairs as $pair) {
                    $src = strtoupper($pair[1]);
                    $dst = importPdfHexToUtf8($pair[2]);
                    if ($src !== '' && $dst !== '') {
                        $map[$src] = $dst;
                        $codeLengths[strlen($src)] = true;
                    }
                }
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/si', $decoded, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $block, $ranges, PREG_SET_ORDER)) {
                    foreach ($ranges as $range) {
                        $start = strtoupper($range[1]);
                        $end = strtoupper($range[2]);
                        $destStart = strtoupper($range[3]);
                        $total = hexdec($end) - hexdec($start);
                        if ($start === '' || $end === '' || $destStart === '' || strlen($start) !== strlen($end) || $total < 0 || $total > 1024) {
                            continue;
                        }
                        for ($offset = 0; $offset <= $total; $offset++) {
                            $src = importPdfIncrementHex($start, $offset);
                            $dst = importPdfHexToUtf8(importPdfIncrementHex($destStart, $offset));
                            if ($src !== '' && $dst !== '') {
                                $map[$src] = $dst;
                                $codeLengths[strlen($src)] = true;
                            }
                        }
                    }
                }

                if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*\[(.*?)\]/s', $block, $arrayRanges, PREG_SET_ORDER)) {
                    foreach ($arrayRanges as $range) {
                        $start = strtoupper($range[1]);
                        $end = strtoupper($range[2]);
                        preg_match_all('/<([0-9A-Fa-f]+)>/', $range[3], $destinations);
                        $destinations = isset($destinations[1]) ? $destinations[1] : array();
                        $total = min(hexdec($end) - hexdec($start), count($destinations) - 1);
                        if ($start === '' || $end === '' || strlen($start) !== strlen($end) || $total < 0 || $total > 1024) {
                            continue;
                        }
                        for ($offset = 0; $offset <= $total; $offset++) {
                            $src = importPdfIncrementHex($start, $offset);
                            $dst = importPdfHexToUtf8($destinations[$offset]);
                            if ($src !== '' && $dst !== '') {
                                $map[$src] = $dst;
                                $codeLengths[strlen($src)] = true;
                            }
                        }
                    }
                }
            }
        }

        $lengths = array_map('intval', array_keys($codeLengths));
        rsort($lengths, SORT_NUMERIC);
        return array('map' => $map, 'code_lengths' => $lengths);
    }
}

if (!function_exists('importPdfBuildUnicodeMapsByFont')) {
    function importPdfBuildUnicodeMapsByFont($content)
    {
        $objects = importPdfExtractObjects($content);

        $fontToUnicode = array();
        foreach ($objects as $objectId => $object) {
            if (stripos($object, '/Type /Font') === false) {
                continue;
            }
            if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/i', $object, $match)) {
                $fontToUnicode[$objectId] = (string) $match[1];
            }
        }

        $fontNames = array();
        foreach ($objects as $object) {
            if (!preg_match_all('/\/Font\s*<<(.*?)>>/s', $object, $fontBlocks)) {
                continue;
            }
            foreach ($fontBlocks[1] as $fontBlock) {
                if (preg_match_all('/\/([A-Za-z][A-Za-z0-9_]*)\s+(\d+)\s+\d+\s+R/', $fontBlock, $fontMatches, PREG_SET_ORDER)) {
                    foreach ($fontMatches as $fontMatch) {
                        $fontNames[$fontMatch[1]] = (string) $fontMatch[2];
                    }
                }
            }
        }

        $maps = array('by_name' => array(), 'default' => array('map' => array(), 'code_lengths' => array()));
        foreach ($objects as $object) {
            $stream = importPdfExtractObjectStream($object);
            if ($stream === false) {
                continue;
            }
            $decoded = decodePdfStream($stream);
            if ($decoded === false || $decoded === '') {
                continue;
            }
            $parsedMap = importPdfParseUnicodeMap($decoded);
            if (!empty($parsedMap['map']) && empty($maps['default']['map'])) {
                $maps['default'] = $parsedMap;
            }
        }

        foreach ($fontNames as $fontName => $fontObjectId) {
            if (!isset($fontToUnicode[$fontObjectId]) || !isset($objects[$fontToUnicode[$fontObjectId]])) {
                continue;
            }
            $unicodeObject = $objects[$fontToUnicode[$fontObjectId]];
            $stream = importPdfExtractObjectStream($unicodeObject);
            if ($stream === false) {
                continue;
            }
            $decoded = decodePdfStream($stream);
            if ($decoded === false || $decoded === '') {
                continue;
            }
            $parsedMap = importPdfParseUnicodeMap($decoded);
            if (!empty($parsedMap['map'])) {
                $maps['by_name'][$fontName] = $parsedMap;
            }
        }

        return $maps;
    }
}

if (!function_exists('importPdfDecodeToken')) {
    function importPdfDecodeToken($token, $unicodeMap = array())
    {
        $token = trim((string) $token);
        if ($token === '') {
            return '';
        }

        if ($token[0] === '<' && substr($token, -1) === '>') {
            $hex = preg_replace('/[^0-9A-Fa-f]/', '', substr($token, 1, -1));
            $map = isset($unicodeMap['map']) && is_array($unicodeMap['map']) ? $unicodeMap['map'] : array();
            $lengths = isset($unicodeMap['code_lengths']) && is_array($unicodeMap['code_lengths']) ? $unicodeMap['code_lengths'] : array();
            foreach ($lengths as $codeLength) {
                $codeLength = (int) $codeLength;
                if ($codeLength <= 0 || strlen($hex) % $codeLength !== 0) {
                    continue;
                }
                $parts = str_split(strtoupper($hex), $codeLength);
                $decoded = '';
                $hits = 0;
                foreach ($parts as $part) {
                    if (isset($map[$part])) {
                        $decoded .= $map[$part];
                        $hits++;
                    }
                }
                if ($hits > 0 && $hits >= (int) ceil(count($parts) * 0.6)) {
                    return cleanPdfTextOperand($decoded);
                }
            }
            $binary = @hex2bin($hex);
            return $binary === false ? '' : cleanPdfTextOperand($binary);
        }

        $inner = ($token[0] === '(' && substr($token, -1) === ')') ? substr($token, 1, -1) : $token;
        $inner = preg_replace_callback('/\\([0-7]{1,3})/', function ($match) {
            return chr(octdec($match[1]));
        }, $inner);
        $inner = str_replace(array('\\n', '\\r', '\\t', '\\b', '\\f', '\\(', '\\)', '\\\\'), array("\n", "\r", "\t", "\b", "\f", '(', ')', '\\'), $inner);
        return cleanPdfTextOperand(preg_replace('/\\\r\n|\\\n|\\\r/', '', $inner));
    }
}

if (!function_exists('importPdfExtractTextTokensByFont')) {
    function importPdfExtractTextTokensByFont($decoded, $unicodeMaps)
    {
        $decoded = (string) $decoded;
        if ($decoded === '' || !preg_match_all('/BT(.*?)ET/s', $decoded, $blocks)) {
            return array();
        }

        $lines = array();
        $byName = isset($unicodeMaps['by_name']) && is_array($unicodeMaps['by_name']) ? $unicodeMaps['by_name'] : array();
        $defaultMap = isset($unicodeMaps['default']) && is_array($unicodeMaps['default']) ? $unicodeMaps['default'] : array();
        foreach ($blocks[1] as $block) {
            $tokens = array();
            $events = array();

            // A single BT/ET block may switch between many embedded fonts.
            // Keep the operator offsets so every Tj/TJ token is decoded with
            // the font that was active immediately before that token.
            if (preg_match_all('/\/([A-Za-z][A-Za-z0-9_]*)\s+[-+0-9.]+\s+Tf/', $block, $fontMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($fontMatches[0] as $matchIndex => $fontMatch) {
                    $events[] = array(
                        'offset' => (int) $fontMatch[1],
                        'type' => 'font',
                        'font' => (string) $fontMatches[1][$matchIndex][0],
                    );
                }
            }
            if (preg_match_all('/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)\s*Tj/s', $block, $textMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($textMatches[0] as $matchIndex => $textMatch) {
                    $events[] = array(
                        'offset' => (int) $textMatch[1],
                        'type' => 'text',
                        'tokens' => array((string) $textMatches[1][$matchIndex][0]),
                    );
                }
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $block, $arrayMatches, PREG_OFFSET_CAPTURE)) {
                foreach ($arrayMatches[0] as $matchIndex => $arrayMatch) {
                    $arrayTokens = array();
                    if (preg_match_all('/(\((?:\\\\.|[^\\\\\)])*\)|<[0-9A-Fa-f\s]+>)/s', $arrayMatches[1][$matchIndex][0], $tokenMatches)) {
                        $arrayTokens = isset($tokenMatches[1]) ? $tokenMatches[1] : array();
                    }
                    $events[] = array(
                        'offset' => (int) $arrayMatch[1],
                        'type' => 'text',
                        'tokens' => $arrayTokens,
                    );
                }
            }

            usort($events, function ($left, $right) {
                return $left['offset'] <=> $right['offset'];
            });

            $fontName = '';
            foreach ($events as $event) {
                if ($event['type'] === 'font') {
                    $fontName = $event['font'];
                    continue;
                }

                $unicodeMap = isset($byName[$fontName]) ? $byName[$fontName] : $defaultMap;
                foreach ((array) $event['tokens'] as $token) {
                    $value = importPdfDecodeToken($token, $unicodeMap);
                    if ($value !== '') {
                        $tokens[] = $value;
                    }
                }
            }
            $line = normalizeImportText(implode('', $tokens));
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }
}

if (!function_exists('extractTextFromPdfContent')) {
    function extractTextFromPdfContent($content)
    {
        if ((string) $content === '') {
            return '';
        }

        $streams = importPdfExtractStreams($content);
        $lines = array();

        if (function_exists('importPdfBuildUnicodeMapsByFont') && function_exists('importPdfExtractTextTokensByFont')) {
            $unicodeMaps = importPdfBuildUnicodeMapsByFont($content);
            foreach ($streams as $stream) {
                $decoded = decodePdfStream($stream);
                if ($decoded === false) {
                    $decoded = (string) $stream;
                }
                $streamLines = importPdfExtractTextTokensByFont($decoded, $unicodeMaps);
                if (!empty($streamLines)) {
                    $lines = array_merge($lines, $streamLines);
                }
            }
            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }

        if (function_exists('satBuildPdfUnicodeMapsByFont') && function_exists('satExtractPdfTextTokensFromDecodedStreamByFont')) {
            $unicodeMaps = satBuildPdfUnicodeMapsByFont($content);

            foreach ($streams as $stream) {
                $decoded = decodePdfStream($stream);
                if ($decoded === false) {
                    $decoded = (string) $stream;
                }

                $streamLines = satExtractPdfTextTokensFromDecodedStreamByFont($decoded, $unicodeMaps);
                if (!empty($streamLines)) {
                    foreach ($streamLines as $line) {
                        $lines[] = $line;
                    }
                }
            }

            if (!empty($lines)) {
                return implode("\n", $lines);
            }
        }

        if (function_exists('satBuildPdfUnicodeMapFromContent') && function_exists('satExtractPdfTextTokensFromDecodedStream')) {
            $unicodeMap = satBuildPdfUnicodeMapFromContent($content);

            foreach ($streams as $stream) {
                $decoded = decodePdfStream($stream);
                if ($decoded === false) {
                    $decoded = (string) $stream;
                }

                $streamLines = satExtractPdfTextTokensFromDecodedStream($decoded, $unicodeMap);
                if (!empty($streamLines)) {
                    foreach ($streamLines as $line) {
                        $lines[] = $line;
                    }
                }
            }

            return implode("\n", $lines);
        }

        if (function_exists('extractPdfTextTokensFromDecodedStream')) {
            foreach ($streams as $stream) {
                $decoded = decodePdfStream($stream);
                if ($decoded === false) {
                    $decoded = (string) $stream;
                }

                $extractedLines = extractPdfTextTokensFromDecodedStream($decoded);
                if (!empty($extractedLines)) {
                    $lines = array_merge($lines, $extractedLines);
                }
            }

            return implode("\n", $lines);
        }

        foreach ($streams as $stream) {
            $decoded = decodePdfStream($stream);
            if ($decoded === false) {
                $decoded = (string) $stream;
            }

            if (preg_match_all('/\(([^\)]{1,500})\)\s*Tj/s', $decoded, $textMatches)) {
                foreach ($textMatches[1] as $match) {
                    $cleanLine = cleanPdfTextOperand($match);
                    if ($cleanLine !== '') {
                        $lines[] = $cleanLine;
                    }
                }
            }

            if (preg_match_all('/\[(.*?)\]\s*TJ/s', $decoded, $arrayMatches)) {
                foreach ($arrayMatches[1] as $chunk) {
                    $lineParts = array();

                    if (preg_match_all('/\(([^\)]{1,500})\)|<([0-9A-Fa-f]+)>/', $chunk, $chunkMatches, PREG_SET_ORDER)) {
                        foreach ($chunkMatches as $chunkMatch) {
                            if (isset($chunkMatch[1]) && $chunkMatch[1] !== '') {
                                $lineParts[] = cleanPdfTextOperand($chunkMatch[1]);
                            } else if (isset($chunkMatch[2]) && $chunkMatch[2] !== '') {
                                $hex = preg_replace('/[^0-9A-Fa-f]/', '', $chunkMatch[2]);
                                $binary = @hex2bin($hex);
                                if ($binary !== false) {
                                    $lineParts[] = cleanPdfTextOperand($binary);
                                }
                            }
                        }
                    }

                    $line = normalizeImportText(implode(' ', array_filter($lineParts)));
                    if ($line !== '') {
                        $lines[] = $line;
                    }
                }
            }
        }

        return implode("\n", $lines);
    }
}

if (!function_exists('extractTextFromPdfViaCommand')) {
    function extractTextFromPdfViaCommand($filePath)
    {
        if (defined('DISABLE_PDFTOTEXT_EXEC') && DISABLE_PDFTOTEXT_EXEC) {
            return '';
        }

        $filePath = trim((string) $filePath);
        if ($filePath === '' || !is_file($filePath) || !function_exists('shell_exec')) {
            return '';
        }

        $disabledFunctions = array_filter(array_map('trim', explode(',', strtolower((string) ini_get('disable_functions')))));
        if (in_array('shell_exec', $disabledFunctions, true)) {
            return '';
        }

        $escapedFile = escapeshellarg($filePath);
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        $timeoutPrefix = $isWindows ? '' : 'timeout 15 ';
        $stderrRedirect = $isWindows ? ' 2>NUL' : ' 2>/dev/null';
        $commands = array(
            $timeoutPrefix . 'pdftotext -enc UTF-8 -layout ' . $escapedFile . ' -' . $stderrRedirect,
            $timeoutPrefix . 'pdftotext -enc UTF-8 ' . $escapedFile . ' -' . $stderrRedirect,
        );

        foreach ($commands as $command) {
            $output = @shell_exec($command);
            $output = is_string($output) ? trim($output) : '';
            if ($output !== '') {
                return $output;
            }
        }

        return '';
    }
}

if (!function_exists('getPdfTextLines')) {
    function getPdfTextLines($text)
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) $text);
        $normalizedLines = array();

        foreach ($lines as $line) {
            $line = normalizeImportText($line);
            if ($line !== '') {
                $normalizedLines[] = $line;
            }
        }

        return $normalizedLines;
    }
}
