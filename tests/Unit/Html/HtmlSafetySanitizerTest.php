<?php

namespace Tests\Unit\Html;

use App\Services\Html\HtmlSafetySanitizer;
use PHPUnit\Framework\TestCase;

class HtmlSafetySanitizerTest extends TestCase
{
    public function test_removes_inline_event_handlers_without_touching_alpine_attributes(): void
    {
        $html = '<section x-data="{ open: false }"><form onsubmit="return false"><button @click="open = ! open" x-on:click="open = true" onclick="alert(1)">Send</button></form></section>';

        $clean = (new HtmlSafetySanitizer)->sanitize($html);

        $this->assertStringNotContainsString('onsubmit=', $clean);
        $this->assertStringNotContainsString('onclick=', $clean);
        $this->assertStringContainsString('@click="open = ! open"', $clean);
        $this->assertStringContainsString('x-on:click="open = true"', $clean);
    }

    public function test_neutralizes_javascript_urls(): void
    {
        $html = '<a href="javascript:alert(1)">Bad</a><form action=" javascript:submit()">Bad</form>';

        $clean = (new HtmlSafetySanitizer)->sanitize($html);

        $this->assertSame('<a href="#">Bad</a><form action="#">Bad</form>', $clean);
    }
}
