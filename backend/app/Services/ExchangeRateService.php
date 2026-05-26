<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * جلب أسعار الصرف من مصادر خارجية مجانية.
 * Frankfurter (ECB) أولاً — ثم open.er-api.com للعملات غير المدعومة مثل KWD.
 */
class ExchangeRateService
{
    /**
     * @return array{updated: int, failed: array<string>, message: string}
     */
    public function fetchAndUpdateRates(int $tenantId): array
    {
        $currencies = Currency::where('tenant_id', $tenantId)->where('is_active', true)->get();
        if ($currencies->isEmpty()) {
            return ['updated' => 0, 'failed' => [], 'message' => 'لا توجد عملات نشطة للتحديث.'];
        }

        $base = $currencies->firstWhere('is_default', true) ?? $currencies->first();
        $baseCode = strtoupper($base->code);
        $others = $currencies
            ->filter(fn ($c) => $c->id !== $base->id)
            ->pluck('code')
            ->map(fn ($c) => strtoupper($c))
            ->unique()
            ->values()
            ->all();

        $updated = 0;
        $failed = [];

        $base->update([
            'exchange_rate' => 1,
            'rate_date' => now()->toDateString(),
        ]);
        $updated++;

        if ($others === []) {
            return ['updated' => $updated, 'failed' => [], 'message' => 'تم تحديث العملة الأساسية فقط.'];
        }

        $rates = $this->fetchRatesForBase($baseCode, $others);
        if ($rates === null) {
            return [
                'updated' => $updated,
                'failed' => $others,
                'message' => 'فشل جلب الأسعار من المصادر الخارجية. تحقق من اتصال السيرفر بالإنترنت أو حدّث الأسعار يدوياً.',
            ];
        }

        $date = $rates['date'];

        foreach ($currencies as $currency) {
            if ($currency->id === $base->id) {
                continue;
            }
            $code = strtoupper($currency->code);
            if (! isset($rates['values'][$code])) {
                $failed[] = $code;

                continue;
            }
            $rateFromApi = (float) $rates['values'][$code];
            if ($rateFromApi <= 0) {
                $failed[] = $code;

                continue;
            }
            $currency->update([
                'exchange_rate' => 1 / $rateFromApi,
                'rate_date' => $date,
            ]);
            $updated++;
        }

        $providerLabel = $rates['provider'] ?? 'external';

        return [
            'updated' => $updated,
            'failed' => $failed,
            'message' => 'تم تحديث '.$updated.' عملة (المصدر: '.$providerLabel.').'
                .(count($failed) > 0 ? ' فشل: '.implode(', ', $failed) : ''),
        ];
    }

    /**
     * @param  list<string>  $targetCodes
     * @return array{values: array<string, float>, date: string, provider: string}|null
     */
    private function fetchRatesForBase(string $baseCode, array $targetCodes): ?array
    {
        $frankfurter = $this->fetchFromFrankfurter($baseCode, $targetCodes);
        if ($frankfurter !== null && $this->coversTargets($frankfurter['values'], $targetCodes)) {
            return $frankfurter;
        }

        $openEr = $this->fetchFromOpenErApi($baseCode, $targetCodes);
        if ($openEr !== null) {
            return $openEr;
        }

        return $frankfurter ?? $openEr;
    }

    /**
     * @param  list<string>  $targetCodes
     * @return array{values: array<string, float>, date: string, provider: string}|null
     */
    private function fetchFromFrankfurter(string $baseCode, array $targetCodes): ?array
    {
        $baseUrl = config('exchange.providers.frankfurter', 'https://api.frankfurter.app/latest');
        $url = $baseUrl.'?from='.$baseCode.'&to='.implode(',', $targetCodes);

        $response = $this->httpGet($url);
        if (! $response?->successful()) {
            Log::info('Frankfurter exchange rates unavailable', [
                'base' => $baseCode,
                'status' => $response?->status(),
            ]);

            return null;
        }

        $data = $response->json();
        $rates = $data['rates'] ?? [];
        if ($rates === []) {
            return null;
        }

        $values = [];
        foreach ($rates as $code => $rate) {
            $values[strtoupper($code)] = (float) $rate;
        }

        return [
            'values' => $values,
            'date' => $data['date'] ?? now()->toDateString(),
            'provider' => 'Frankfurter (ECB)',
        ];
    }

    /**
     * @param  list<string>  $targetCodes
     * @return array{values: array<string, float>, date: string, provider: string}|null
     */
    private function fetchFromOpenErApi(string $baseCode, array $targetCodes): ?array
    {
        $baseUrl = rtrim(config('exchange.providers.open_er_api', 'https://open.er-api.com/v6/latest'), '/');
        $url = $baseUrl.'/'.$baseCode;

        $response = $this->httpGet($url);
        if (! $response?->successful()) {
            Log::warning('open.er-api exchange rates failed', ['base' => $baseCode, 'status' => $response?->status()]);

            return null;
        }

        $data = $response->json();
        if (($data['result'] ?? '') !== 'success') {
            return null;
        }

        $allRates = $data['rates'] ?? [];
        $values = [];
        foreach ($targetCodes as $code) {
            $code = strtoupper($code);
            if (isset($allRates[$code])) {
                $values[$code] = (float) $allRates[$code];
            }
        }

        if ($values === []) {
            return null;
        }

        $date = isset($data['time_last_update_utc'])
            ? date('Y-m-d', strtotime($data['time_last_update_utc']))
            : now()->toDateString();

        return [
            'values' => $values,
            'date' => $date,
            'provider' => 'ExchangeRate-API',
        ];
    }

    /**
     * @param  array<string, float>  $values
     * @param  list<string>  $targetCodes
     */
    private function coversTargets(array $values, array $targetCodes): bool
    {
        foreach ($targetCodes as $code) {
            if (! isset($values[strtoupper($code)])) {
                return false;
            }
        }

        return true;
    }

    private function httpGet(string $url): ?\Illuminate\Http\Client\Response
    {
        $timeout = (int) config('exchange.timeout_seconds', 15);
        $verify = (bool) config('exchange.verify_ssl', true);

        try {
            $request = Http::timeout($timeout);
            if (! $verify) {
                $request = $request->withoutVerifying();
            }

            return $request->get($url);
        } catch (\Throwable $e) {
            Log::warning('Exchange rate HTTPS failed, retrying without SSL verification', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            try {
                return Http::timeout($timeout)->withoutVerifying()->get($url);
            } catch (\Throwable $retry) {
                Log::error('Exchange rate fetch error', ['url' => $url, 'message' => $retry->getMessage()]);

                return null;
            }
        }
    }
}
