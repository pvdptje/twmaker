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

    public function test_insert_blocks_places_new_block_after_anchor(): void
    {
        $html = <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section>Hero</section>
<!-- /tw:block -->
<!-- tw:block id="block_features" type="features" label="Features" -->
<section>Features</section>
<!-- /tw:block -->
HTML;
        $inserted = <<<'HTML'
<!-- tw:block id="block_logos" type="logo_cloud" label="Logos" -->
<section>Logos</section>
<!-- /tw:block -->
HTML;

        $updated = (new BlockIndexer)->insertBlocks($html, 'block_hero', 'after', $inserted);
        $blocks = (new BlockIndexer)->index($updated);

        $this->assertSame(['block_hero', 'block_logos', 'block_features'], array_column($blocks, 'id'));
    }

    public function test_move_block_relocates_section_to_new_position(): void
    {
        $html = <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section>Hero</section>
<!-- /tw:block -->
<!-- tw:block id="block_features" type="features" label="Features" -->
<section>Features</section>
<!-- /tw:block -->
<!-- tw:block id="block_cta" type="cta" label="CTA" -->
<section>CTA</section>
<!-- /tw:block -->
HTML;

        $updated = (new BlockIndexer)->moveBlock($html, 'block_cta', 'block_hero', 'before');
        $blocks = (new BlockIndexer)->index($updated);

        $this->assertSame(['block_cta', 'block_hero', 'block_features'], array_column($blocks, 'id'));
    }

    public function test_remove_block_drops_only_the_targeted_marker_pair(): void
    {
        $html = <<<'HTML'
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section>Hero</section>
<!-- /tw:block -->
<!-- tw:block id="block_features" type="features" label="Features" -->
<section>Features</section>
<!-- /tw:block -->
HTML;

        $updated = (new BlockIndexer)->removeBlock($html, 'block_hero');
        $blocks = (new BlockIndexer)->index($updated);

        $this->assertSame(['block_features'], array_column($blocks, 'id'));
        $this->assertStringNotContainsString('block_hero', $updated);
    }
}
