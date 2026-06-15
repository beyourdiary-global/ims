<?php

if (!function_exists('getImportOptionList')) {
    function getImportOptionList($tableName, $labelField, $dbConnect)
    {
        $list = [];
        $tableName = mysqli_real_escape_string($dbConnect, $tableName);
        $labelField = mysqli_real_escape_string($dbConnect, $labelField);
        $query = "SELECT id, `$labelField` AS option_label FROM `$tableName` WHERE status = 'A' ORDER BY `$labelField` ASC";
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
        return trim(preg_replace('/\s+/u', ' ', $text));
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

        return normalizeImportText(preg_replace('/[^[:print:] ]/', ' ', $text));
    }
}

if (!function_exists('extractTextFromPdfContent')) {
    function extractTextFromPdfContent($content)
    {
        if ((string) $content === '') {
            return '';
        }

        preg_match_all('/stream\s*\r?\n(.*?)\r?\n?endstream/s', (string) $content, $streamMatches);
        $streams = isset($streamMatches[1]) && is_array($streamMatches[1]) ? $streamMatches[1] : array();
        $lines = array();

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