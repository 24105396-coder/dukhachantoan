<?php

namespace Tests\Unit;

use App\Support\RiskFactors;
use Tests\TestCase;

/** Trình bày factors của Python thành danh sách [{code, label, value, note}] theo locale. */
class RiskFactorsTest extends TestCase
{
    private function python(): array
    {
        return [
            'weather' => ['available' => true, 'score' => 15.63, 'inputs' => ['gust_kph' => 37.8, 'rain_mm' => 0, 'temp_c' => 30.6]],
            'alerts' => ['score' => 64, 'count' => 2],
            'trend' => ['previous' => 43, 'delta' => 24, 'direction' => 'up'],
            'formula' => 'score = floor(...)',
            'explain' => ['vi' => 'Gió giật 38 km/h', 'en' => 'Wind gusts 38 km/h'],
        ];
    }

    public function test_python_factors_become_localized_rows_without_formula(): void
    {
        app()->setLocale('vi');
        $rows = RiskFactors::present($this->python());

        $this->assertSame(['weather', 'alerts', 'trend', 'explain'], array_column($rows, 'code'));
        $this->assertSame(15.6, $rows[0]['value']);
        $this->assertStringContainsString('37,8 km/h', $rows[0]['note']);
        $this->assertSame('+24', $rows[2]['value']);
        $this->assertSame('Gió giật 38 km/h', $rows[3]['label']);
        $this->assertNull($rows[3]['value']);

        app()->setLocale('en');
        $this->assertSame('Wind gusts 38 km/h', RiskFactors::present($this->python())[3]['label']);
    }

    public function test_list_is_kept_and_empty_returns_null(): void
    {
        $list = [['code' => 'rain', 'label' => 'Mưa', 'value' => 10, 'note' => '']];

        $this->assertSame($list, RiskFactors::present($list));
        $this->assertNull(RiskFactors::present(null));
        $this->assertNull(RiskFactors::present([]));
        $this->assertSame(['alerts'], array_column(RiskFactors::present(['weather' => ['available' => false], 'alerts' => ['score' => 40, 'count' => 1]]), 'code'));
    }
}
