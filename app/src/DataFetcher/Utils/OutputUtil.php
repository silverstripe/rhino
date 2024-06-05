<?php

namespace App\Services;

use App\DataFetcher\Misc\Logger;

/*
Assumes data comes in as:
[
    [ 'key01' => 'valA', key02' => 'valB' ],
    [ 'key01' => 'valC', key02' => 'valD' ],
]
*/
class OutputUtil
{
    public static function outputCsv(string $filename, array $data): void
    {
        $maxRows = 9999;
        $lines = [];
        $n = 0;
        $headers = count($data) > 0 ? array_keys($data[0]) : [];
        foreach ($data as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = str_replace(',', '', $row[$header]);
            }
            $lines[] = implode(',', $values);
            if (++$n >= $maxRows) {
                break;
            }
        }
        array_unshift($lines, implode(',', $headers));
        $output = implode("\n", $lines);
        self::writeToFile($filename, $output);
    }

    public static function outputHtmlTable(string $filename, array $data, string $script = ''): void
    {
        $maxRows = 9999;
        $lines = ['<tbody>'];
        $n = 0;
        $headers = count($data) > 0 ? array_keys($data[0]) : [];
        foreach ($data as $row) {
            $values = [];
            foreach ($headers as $header) {
                $values[] = htmlentities($row[$header]);
            }
            $lines[] = '<tr><td>' . implode('</td><td>', $values) . '</td></tr>';
            if (++$n >= $maxRows) {
                break;
            }
        }
        $lines[] = '</tbody>';
        $headerLine = '<thead><tr><th>' . implode('</th><th>', $headers) . '</th></tr></thead>';
        array_unshift($lines, $headerLine);
        $tableContent = implode("\n", $lines);
        $html = implode('', [
            "<table id=\"mytable\">{$tableContent}</table>",
            "<script>{$script}</script>",
        ]);
        // auto-hyperlink urls
        $rx = '#>[-a-zA-Z0-9@:%_\+.~\#?&//=]{2,256}\.[a-z]{2,4}\b(\/[-a-zA-Z0-9@:%_\+.~\#?&//=]*)?<#si';
        preg_match_all($rx, $html, $m);
        foreach ($m[0] as $url) {
            $href = trim($url, '><');
            // don't hyperlink repos such as doc.silverstripe.org
            if (preg_match('#^[a-z]+\.silverstripe\.org$#', $href)) {
                continue;
            }
            $extraText = '';
            // hack for MergeUpsProcessor
            if (strpos($href, ':needs-merge-up') !== false) {
                $extraText = "needs-merge-up<br><br>";
                $href = str_replace(':needs-merge-up', '', $href);
            }
            $html = str_replace($url, ">$extraText<a href=\"{$href}\" target=\"_blank\">link</a><", $html);
        }
        self::writeToFile($filename, $html);
    }

    private static function writeToFile(string $filename, string $output): void
    {
        file_put_contents($filename, $output);
        Logger::singleton()->log("Wrote to {$filename}");
    }
}
