<?php

namespace Tests\Unit\Generation;

use App\Models\Page;
use App\Services\Generation\RelatedPagePromptBuilder;
use App\Services\Html\BlockIndexer;
use PHPUnit\Framework\TestCase;

class RelatedPagePromptBuilderTest extends TestCase
{
    public function test_prompt_includes_exact_header_and_footer_reuse_instructions(): void
    {
        $source = new Page([
            'name' => 'Homepage',
            'html_source' => <<<'HTML'
<!-- tw:block id="block_header" type="header" label="Header" -->
<header class="px-6 py-4"><nav>Acme</nav></header>
<!-- /tw:block -->
<!-- tw:block id="block_hero" type="hero" label="Hero" -->
<section class="px-6 py-24"><h1>Homepage</h1></section>
<!-- /tw:block -->
<!-- tw:block id="block_footer" type="footer" label="Footer" -->
<footer class="px-6 py-8">Footer</footer>
<!-- /tw:block -->
HTML,
        ]);
        $target = new Page(['name' => 'Pricing']);

        $prompt = (new RelatedPagePromptBuilder(new BlockIndexer))
            ->build($source, $target, 'Create a pricing page.');

        $this->assertStringContainsString('New page name: Pricing', $prompt);
        $this->assertStringContainsString('Create a pricing page.', $prompt);
        $this->assertStringContainsString('<header class="px-6 py-4"><nav>Acme</nav></header>', $prompt);
        $this->assertStringContainsString('<footer class="px-6 py-8">Footer</footer>', $prompt);
        $this->assertStringContainsString('Paste this header into the new document unchanged', $prompt);
        $this->assertStringContainsString('Paste this footer into the new document unchanged', $prompt);
    }
}
