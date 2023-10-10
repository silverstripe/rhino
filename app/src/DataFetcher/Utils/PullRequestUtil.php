<?php

namespace App\Utils;

class PullRequestUtil
{
    public static function getPullRequestType(array $files, string $author): string
    {
        $c = self::getPullRequestFileCounts($files);
        $types = array_filter($c, function($v, $k) {
            return $v['count'] > 0;
        }, ARRAY_FILTER_USE_BOTH);
        $types = array_keys($types);
        sort($types);
        if($author == 'dependabot') {
            return 'depbot';
        } elseif ($types == ['doc'] || $types == ['doc', 'image']) {
            return 'doc';
        } elseif ($types == ['config']) {
            return 'config';
        } elseif ($types == ['dist'] || $types == ['dist', 'image']) {
            return 'dist';
        }
        return 'general';
    }

    private static function getPullRequestFileCounts(array $files): array
    {
        $data = [];
        $ks = ['config', 'dist', 'doc', 'general', 'image', 'test'];
        foreach ($ks as $k) {
            $data[$k] = [
                'additions' => 0,
                'count' => 0,
                'deletions' => 0,
            ];
        }
        foreach ($files as $file) {
            $path = $file['path'];
            $k = 'general';
            if (self::isConfigFile($path)) {
                $k  = 'config';
            } elseif (self::isDistFile($path)) {
                $k  = 'dist';
            } elseif (self::isDocFile($path)) {
                $k  = 'doc';
            } elseif (self::isImageFile($path)) {
                $k  = 'image';
            } elseif (self::isTestFile($path)) {
                $k  = 'test';
            }
            $data[$k]['count']++;
            $data[$k]['additions'] += $file['additions'];
            $data[$k]['deletions'] += $file['deletions'];
        }
        return $data;
    }

    private static function isDocFile(string $path): bool
    {
        $ext = pathinfo(strtolower($path), PATHINFO_EXTENSION);
        return in_array($ext, ['md']);
    }
    
    private static function isImageFile(string $path): bool
    {
        $ext = pathinfo(strtolower($path), PATHINFO_EXTENSION);
        return in_array($ext, ['jpg', 'jpeg', 'gif', 'png']);
    }
    
    private static function isConfigFile(string $path): bool
    {
        // possiblly should treat .travis.yml and .scrutinizer as 'tooling'
        $b = preg_match('@lang/[A-Za-z]{2}.yml$@', $path);
        return $b || in_array($path, [
            '.travis.yml',
            '.scrutinizer.yml',
            'behat.yml',
            'composer.json',
            'composer.lock',
            'package.json',
            'yarn.lock',
            'phpunit.xml.dist',
            'phpcs.xml.dist',
            'webpack.config.js',
            'webpack-vars.js'
        ]);
    }
    
    private static function isDistFile(string $path): bool
    {
        $ext = pathinfo(strtolower($path), PATHINFO_EXTENSION);
        return strpos($path, '/dist/') !== false ||
            in_array($path, ['bundle.js', 'vendor.js']) ||
            in_array($ext, ['css']);
    }
    
    private static function isTestFile(string $path): bool
    {
        return (bool) preg_match('/[a-z0-9]test\.php$/', $path);
    }
}
