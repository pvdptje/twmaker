<?php

namespace Tests\Unit\Html;

use App\Services\Html\DeterministicBlockMarker;
use App\Services\Html\HtmlFragmentRepairer;
use PHPUnit\Framework\TestCase;

class HtmlFragmentRepairerTest extends TestCase
{
    public function test_extracts_body_from_full_document(): void
    {
        $html = '<!doctype html><html><head><title>Demo</title></head><body><main><section>Hi</section></main></body></html>';

        $this->assertSame(
            '<main><section>Hi</section></main>',
            (new HtmlFragmentRepairer)->repair($html),
        );
    }

    public function test_closes_obvious_missing_tags(): void
    {
        $html = '<section><div><p>Almost there';

        $this->assertSame(
            '<section><div><p>Almost there</p></div></section>',
            (new HtmlFragmentRepairer)->repair($html),
        );
    }

    public function test_marker_preserves_alpine_attributes(): void
    {
        $html = '<section x-data="{ open: false }"><button @click="open = ! open" :class="{ active: open }">Toggle</button></section>';

        $marked = (new DeterministicBlockMarker)->mark($html);

        $this->assertStringContainsString('@click="open = ! open"', $marked);
        $this->assertStringContainsString(':class="{ active: open }"', $marked);
        $this->assertStringContainsString('data-tw-block="block_hero"', $marked);
    }
}
