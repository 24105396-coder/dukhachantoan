<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Mọi tên hành động ghi vào audit_logs phải có nhãn `enums.audit_action.<action>`
 * ở cả lang/vi.json và lang/en.json (trang /quan-tri/nhat-ky tra bằng te('audit_action', …)).
 */
class AuditActionLangTest extends TestCase
{
    private const MIN_EXPECTED_ACTIONS = 38;

    public function test_every_audit_action_in_code_has_label_in_both_locales(): void
    {
        $actions = $this->actionsFromCode();
        $this->assertGreaterThanOrEqual(self::MIN_EXPECTED_ACTIONS, count($actions), 'Quét mã thiếu action, kiểm lại biểu thức.');

        foreach (['vi', 'en'] as $locale) {
            $dict = json_decode(file_get_contents(lang_path("{$locale}.json")), true, flags: JSON_THROW_ON_ERROR);
            $missing = array_values(array_filter(
                $actions,
                fn (string $a) => ! is_string($dict["enums.audit_action.{$a}"] ?? null) || trim($dict["enums.audit_action.{$a}"]) === ''
            ));
            $this->assertSame([], $missing, "lang/{$locale}.json thiếu nhãn hành động nhật ký");
        }
    }

    public function test_every_audit_log_call_uses_a_scannable_action(): void
    {
        // Lời gọi log() không dùng chuỗi trực tiếp chỉ được phép ở hai chỗ đã quét riêng bên dưới.
        $allowed = ['self::ACTION', '$action'];
        foreach ($this->phpFiles() as $file) {
            preg_match_all('/\$(?:this->)?audit->log\(\s*([^,\s]+)/', $file->getContents(), $m);
            foreach ($m[1] as $arg) {
                $this->assertTrue(
                    str_starts_with($arg, "'") || in_array($arg, $allowed, true),
                    "{$file->getRelativePathname()}: action động `{$arg}` không quét được"
                );
            }
        }
    }

    /** @return list<string> */
    private function actionsFromCode(): array
    {
        $actions = [];
        foreach ($this->phpFiles() as $file) {
            $src = $file->getContents();
            // audit->log('x.y', …)
            preg_match_all('/audit->log\(\s*\'([a-z_]+(?:\.[a-z_]+)+)\'/', $src, $m);
            $actions = [...$actions, ...$m[1]];
            // const ACTION = 'x.y' (AccessDeniedLogger)
            preg_match_all('/const\s+ACTION\s*=\s*\'([a-z_]+(?:\.[a-z_]+)+)\'/', $src, $m);
            $actions = [...$actions, ...$m[1]];
            // $action = … ? 'x.y' : 'x.z' (AlertService::close)
            preg_match_all('/\$action\s*=\s*([^;]+);/', $src, $stmts);
            foreach ($stmts[1] as $stmt) {
                preg_match_all('/\'([a-z_]+(?:\.[a-z_]+)+)\'/', $stmt, $m);
                $actions = [...$actions, ...$m[1]];
            }
        }
        $actions = array_values(array_unique($actions));
        sort($actions);

        return $actions;
    }

    private function phpFiles(): Finder
    {
        return Finder::create()->files()->in(app_path())->name('*.php');
    }
}
