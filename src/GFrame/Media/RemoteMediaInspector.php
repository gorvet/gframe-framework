<?php

namespace GFrame\Media;

class RemoteMediaInspector
{
    public function inspect(string $url, MediaProcessor $processor): array
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (strlen($url) > 500 || !is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user'], $parts['pass']) || isset($parts['user'])
            || isset($parts['fragment']) || (isset($parts['port']) && (int)$parts['port'] !== 443)) {
            return ['status' => 'error', 'code' => 'invalid_hotlink_url'];
        }
        $host = strtolower((string)$parts['host']);
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || !preg_match('/^[a-z0-9.-]+$/', $host) || !str_contains($host, '.')) {
            return ['status' => 'error', 'code' => 'invalid_hotlink_host'];
        }
        $addresses = gethostbynamel($host);
        if (!is_array($addresses) || $addresses === []) return ['status' => 'error', 'code' => 'hotlink_probe_failed'];
        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return ['status' => 'error', 'code' => 'invalid_hotlink_host'];
            }
        }
        if (!function_exists('curl_init')) return ['status' => 'error', 'code' => 'hotlink_probe_unavailable'];
        $probe = $this->probe($url, $host, $addresses[0], true);
        if (($probe['status'] ?? 0) === 405 || ($probe['status'] ?? 0) === 501) {
            $probe = $this->probe($url, $host, $addresses[0], false);
        }
        $status = (int)($probe['status'] ?? 0);
        $mime = (string)($probe['mime'] ?? '');
        $size = (int)($probe['size'] ?? 0);
        if (empty($probe['ok']) || $status < 200 || $status >= 300) return ['status' => 'error', 'code' => 'hotlink_probe_failed'];
        $extension = $processor->classify($mime, (string)($parts['path'] ?? ''))['ext'];
        foreach ($processor->getAllowedByKindMap() as $kind => $allowed) {
            if (in_array($extension, $allowed['exts'], true) && in_array($mime, $allowed['mimes'], true)) {
                return ['status' => 'success', 'url' => $url, 'kind' => $kind, 'mime' => $mime, 'size' => $size, 'extension' => $extension, 'host' => $host];
            }
        }
        return ['status' => 'error', 'code' => 'media_not_allowed'];
    }

    private function probe(string $url, string $host, string $address, bool $head): array
    {
        $curl = curl_init($url);
        if ($curl === false) return ['status' => 'error', 'code' => 'hotlink_probe_failed'];
        curl_setopt_array($curl, [
            CURLOPT_NOBODY => $head, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 6, CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [$host . ':443:' . $address],
            CURLOPT_PROXY => '',
            CURLOPT_USERAGENT => 'GFrameMedia/1.0',
        ]);
        if (!$head) {
            $received = 0;
            curl_setopt($curl, CURLOPT_RANGE, '0-1023');
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, static function ($handle, string $chunk) use (&$received): int {
                $received += strlen($chunk);
                return $received > 4096 ? 0 : strlen($chunk);
            });
            curl_setopt($curl, CURLOPT_MAXFILESIZE, 4096);
        }
        $ok = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $mime = strtolower(trim(explode(';', (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE))[0]));
        $size = max(0, (int)curl_getinfo($curl, CURLINFO_CONTENT_LENGTH_DOWNLOAD));
        curl_close($curl);
        return ['ok' => $ok !== false, 'status' => $status, 'mime' => $mime, 'size' => $size];
    }
}
