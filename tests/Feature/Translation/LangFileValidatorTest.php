<?php

namespace Tests\Feature\Translation;

use App\Translation\Services\LangFileCatalog;
use App\Translation\Services\LangFileValidator;
use Tests\TestCase;

/**
 * The validator refuses a translation that changes a number — and must not
 * refuse good CJK, which writes months and "a minute" as numerals.
 */
class LangFileValidatorTest extends TestCase
{
    private function reject(string $source, string $translated): ?string
    {
        return app(LangFileValidator::class)->reject('k', $source, $translated);
    }

    public function test_a_named_month_may_become_its_number(): void
    {
        $this->assertNull($this->reject('Apr', '4月'));
        $this->assertNull($this->reject('January', '1月'));
        $this->assertNull($this->reject('Date — e.g. Jul 3', '日付 — 例: 7月3日'));
    }

    public function test_an_article_meaning_one_may_become_a_numeral(): void
    {
        $this->assertNull($this->reject('a month', '1か月'));
        $this->assertNull($this->reject('Takes about a minute', '約1分かかります'));
    }

    public function test_a_changed_or_invented_number_is_still_refused(): void
    {
        $this->assertSame('numbers changed', $this->reject('Date — e.g. Jul 3', '日付 — 例: 7月5日'));
        $this->assertSame('numbers changed', $this->reject('Price: 10 BHD', '価格: 12 BHD'));
        $this->assertSame('numbers changed', $this->reject('Market', '5市場'));
        $this->assertSame('numbers changed', $this->reject('Save', '保存1'));
    }

    public function test_an_empty_english_string_is_not_something_to_translate(): void
    {
        $catalog = app(LangFileCatalog::class);
        $file = $catalog->files('ja')['events::club_entry']['source'];

        $this->assertArrayHasKey('ui.payBankS', $catalog->strings($file));
        $this->assertArrayNotHasKey('ui.payBankS', $catalog->sourceStrings($file));
    }
}
