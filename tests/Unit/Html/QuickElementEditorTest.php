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

    public function test_replaces_inside_block_that_starts_with_style(): void
    {
        $html = '<!-- tw:block id="block_hero" type="hero" label="Hero" --><style>.hero{color:red}</style><section class="hero"><h1>Ship pages</h1><p>Fast</p></section><!-- /tw:block -->';

        $updated = $this->editor()->replace(
            $html,
            'block_hero:0',
            '<h1>Better hero</h1>',
        );

        $this->assertStringContainsString('<style>.hero{color:red}</style>', $updated);
        $this->assertStringContainsString('<section class="hero"><h1>Better hero</h1><p>Fast</p></section>', $updated);
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

    public function test_replaces_element_inside_group_wrapper(): void
    {
        $html = <<<'HTML'
<!-- tw:group id="block_features" type="features" label="Features" -->
<section><h2>Old title</h2>
  <!-- tw:block id="block_card" type="card" label="Card" -->
  <article>Card</article>
  <!-- /tw:block -->
</section>
<!-- /tw:group -->
HTML;

        $updated = $this->editor()->replace($html, 'block_features:0', '<h2>New title</h2>');

        $this->assertStringContainsString('tw:group id="block_features"', $updated);
        $this->assertStringContainsString('<h2>New title</h2>', $updated);
        $this->assertStringContainsString('tw:block id="block_card"', $updated);
    }

    private function editor(): QuickElementEditor
    {
        $blocks = new BlockIndexer;

        return new QuickElementEditor($blocks, new HtmlDocumentValidator($blocks));
    }
}
