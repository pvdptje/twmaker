<?php

namespace Tests\Unit\Html;

use App\Services\Html\BlockIndexer;
use PHPUnit\Framework\TestCase;

class BlockIndexerTest extends TestCase
{
    public function test_summary_truncation_preserves_utf8_boundaries(): void
    {
        $text = str_repeat('a', 156).'€'.str_repeat('b', 20);
        $html = '<!-- tw:block id="block_hero" type="hero" label="Hero" -->'
            .'<section><p>'.$text.'</p></section>'
            .'<!-- /tw:block -->';

        $block = (new BlockIndexer)->index($html)[0];

        $this->assertTrue(mb_check_encoding($block['summary'], 'UTF-8'));
        $this->assertStringContainsString('€', $block['summary']);
        $this->assertJson(json_encode($block, JSON_THROW_ON_ERROR));
    }
}
