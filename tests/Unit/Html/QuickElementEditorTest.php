<?php

namespace Tests\Unit\Html;

use App\Services\Html\BlockIndexer;
use App\Services\Html\HtmlDocumentValidator;
use App\Services\Html\HtmlValidationException;
use App\Services\Html\QuickElementEditor;
use PHPUnit\Framework\TestCase;

class QuickElementEditorTest extends TestCase
{
    public function test_replaces_one_element_without_exposing_block_markers(): void
    {
        $html = '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section class="px-6 py-24"><h1>Ship pages</h1><p class="mt-4 text-lg">Fast</p></section><!-- /tw:block -->';

        $updated = $this->editor()->replace(
            $html,
            'block_hero:1',
            '<p class="mt-8 text-xl text-neutral-700">Better website copy here.</p>',
        );

        $this->assertStringContainsString('<!-- tw:block id="block_hero" type="hero" label="Hero" -->', $updated);
        $this->assertStringContainsString('<h1>Ship pages</h1>', $updated);
        $this->assertStringContainsString('<p class="mt-8 text-xl text-neutral-700">Better website copy here.</p>', $updated);
        $this->assertStringContainsString('<!-- /tw:block -->', $updated);
    }

    public function test_rejects_edits_that_include_block_markers(): void
    {
        $this->expectException(HtmlValidationException::class);

        $this->editor()->replace(
            '<!-- tw:block id="block_hero" type="hero" label="Hero" --><section>Hero</section><!-- /tw:block -->',
            'block_hero:',
            '<!-- tw:block id="oops" --><section>Bad</section><!-- /tw:block -->',
        );
    }

    private function editor(): QuickElementEditor
    {
        $blocks = new BlockIndexer;

        return new QuickElementEditor($blocks, new HtmlDocumentValidator($blocks));
    }
}
