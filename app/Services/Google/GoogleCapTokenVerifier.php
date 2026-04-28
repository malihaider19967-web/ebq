<?php

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleCapTokenVerifier
{
    /**
     * Verify a Google CAP/RISC JWT and return its decoded payload.
     *
     * @return array<string, mixed>
     */
    public function verify(string $jwt): array
    {
        [$headerB64, $payloadB64, $sigB64] = $this->splitJwt($jwt);

        $header = $this->decodeJson($this->b64UrlDecode($headerB64));
        $payload = $this->decodeJson($this->b64UrlDecode($payloadB64));
        $signature = $this->b64UrlDecode($sigB64);

        $alg = (string) ($header['alg'] ?? '');
        if ($alg !== 'RS256') {
            throw new RuntimeException('Unsupported CAP token algorithm.');
        }

        $kid = (string) ($header['kid'] ?? '');
        if ($kid === '') {
            throw new RuntimeException('Missing CAP token key id.');
        }

        $pem = $this->resolvePemForKid($kid);
        $signedPart = $headerB64.'.'.$payloadB64;
        $ok = openssl_verify($signedPart, $signature, $pem, OPENSSL_ALGO_SHA256);
        if ($ok !== 1) {
            throw new RuntimeException('CAP token signature verification failed.');
        }

        $this->validateClaims($payload);

        return $payload;
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function splitJwt(string $jwt): array
    {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid CAP token format.');
        }

        return [$parts[0], $parts[1], $parts[2]];
    }

    private function b64UrlDecode(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad > 0) {
            $value .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('CAP token decoding failed.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('CAP token payload is invalid JSON.');
        }

        return $decoded;
    }

    private function validateClaims(array $payload): void
    {
        $now = time();
        $iss = (string) ($payload['iss'] ?? '');
        $exp = (int) ($payload['exp'] ?? 0);
        $iat = (int) ($payload['iat'] ?? 0);
        $aud = $payload['aud'] ?? null;

        $allowedIssuers = config('services.google.cap_issuers', [
            'https://accounts.google.com',
            'accounts.google.com',
        ]);
        if (! is_array($allowedIssuers) || ! in_array($iss, $allowedIssuers, true)) {
            throw new RuntimeException('CAP token issuer is invalid.');
        }

        if ($exp <= 0 || $exp < $now - 30) {
            throw new RuntimeException('CAP token is expired.');
        }
        if ($iat > 0 && $iat > $now + 300) {
            throw new RuntimeException('CAP token issued-at claim is invalid.');
        }

        $expectedAudience = (string) config('services.google.cap_audience', '');
        if ($expectedAudience !== '') {
            if (is_string($aud) && $aud !== $expectedAudience) {
                throw new RuntimeException('CAP token audience mismatch.');
            }
            if (is_array($aud) && ! in_array($expectedAudience, $aud, true)) {
                throw new RuntimeException('CAP token audience mismatch.');
            }
        }
    }

    private function resolvePemForKid(string $kid): string
    {
        $jwks = Cache::remember('google_cap_jwks', now()->addHour(), function (): array {
            $url = (string) config('services.google.cap_jwks_url', 'https://www.googleapis.com/oauth2/v3/certs');
            $resp = Http::timeout(10)->acceptJson()->get($url);
            if (! $resp->successful()) {
                throw new RuntimeException('Failed to fetch Google CAP signing keys.');
            }

            $json = $resp->json();
            if (! is_array($json) || ! isset($json['keys']) || ! is_array($json['keys'])) {
                throw new RuntimeException('Google CAP signing keys response is invalid.');
            }

            return $json['keys'];
        });

        foreach ($jwks as $jwk) {
            if (! is_array($jwk) || (string) ($jwk['kid'] ?? '') !== $kid) {
                continue;
            }
            $n = (string) ($jwk['n'] ?? '');
            $e = (string) ($jwk['e'] ?? '');
            if ($n === '' || $e === '') {
                break;
            }

            return $this->jwkToPem($n, $e);
        }

        throw new RuntimeException('No Google CAP signing key found for token key id.');
    }

    private function jwkToPem(string $nB64, string $eB64): string
    {
        $modulus = $this->b64UrlDecode($nB64);
        $exponent = $this->b64UrlDecode($eB64);

        $modulusEnc = $this->asn1EncodeInteger($modulus);
        $exponentEnc = $this->asn1EncodeInteger($exponent);
        $rsaPublicKey = $this->asn1EncodeSequence($modulusEnc.$exponentEnc);

        // rsaEncryption OID + NULL params
        $algo = hex2bin('300d06092a864886f70d0101010500');
        $bitString = "\x03".$this->asn1EncodeLength(strlen($rsaPublicKey) + 1)."\x00".$rsaPublicKey;
        $spki = $this->asn1EncodeSequence($algo.$bitString);

        $pem = "-----BEGIN PUBLIC KEY-----\n";
        $pem .= chunk_split(base64_encode($spki), 64, "\n");
        $pem .= "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    private function asn1EncodeInteger(string $bytes): string
    {
        if ($bytes === '') {
            $bytes = "\x00";
        }
        if (ord($bytes[0]) > 0x7f) {
            $bytes = "\x00".$bytes;
        }

        return "\x02".$this->asn1EncodeLength(strlen($bytes)).$bytes;
    }

    private function asn1EncodeSequence(string $bytes): string
    {
        return "\x30".$this->asn1EncodeLength(strlen($bytes)).$bytes;
    }

    private function asn1EncodeLength(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }

        $temp = '';
        while ($len > 0) {
            $temp = chr($len & 0xff).$temp;
            $len >>= 8;
        }

        return chr(0x80 | strlen($temp)).$temp;
    }
}

