<?php

namespace Tests\Unit\Schema;

use App\Services\Schema\ElementSchemas;
use App\Services\Schema\NodeSchemas;
use App\Services\Schema\SchemaValidator;
use App\Services\Schema\SectionSchemas;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SchemaValidatorTest extends TestCase
{
    #[DataProvider('sectionProvider')]
    public function test_validates_each_section_type(string $type, array $section): void
    {
        $this->assertTrue($this->validator()->validateSectionNode($section), $type);
    }

    #[DataProvider('sectionProvider')]
    public function test_rejects_each_malformed_section_type(string $type, array $section): void
    {
        $section['children'] = [];

        $this->assertFalse($this->validator()->validateSectionNode($section), $type);
    }

    #[DataProvider('nodeProvider')]
    public function test_validates_each_node_type(string $type, array $node): void
    {
        $this->assertTrue($this->validator()->validateContentNode($node), $type);
    }

    #[DataProvider('nodeProvider')]
    public function test_rejects_each_malformed_node_type(string $type, array $node): void
    {
        unset($node['props']);

        $this->assertFalse($this->validator()->validateContentNode($node), $type);
    }

    #[DataProvider('elementProvider')]
    public function test_validates_each_element_type(string $type, array $element): void
    {
        $this->assertTrue($this->validator()->validateElementDefinition($element), $type);
    }

    #[DataProvider('elementProvider')]
    public function test_rejects_each_malformed_element_type(string $type, array $element): void
    {
        $element['default_props'] = [];

        $this->assertFalse($this->validator()->validateElementDefinition($element), $type);
    }

    public function test_validates_complete_document(): void
    {
        $document = self::document([self::section('hero')]);

        $this->assertTrue($this->validator()->validateDocument($document));
    }

    public function test_rejects_malformed_fixture_documents(): void
    {
        $fixture = json_decode(file_get_contents(__DIR__.'/../../fixtures/documents/invalid-missing-document-tree.json'), true);

        $this->assertFalse($this->validator()->validateDocument($fixture));
    }

    public function test_rejects_malformed_section_counts_without_type_error(): void
    {
        $stats = self::section('stats_band');
        $stats['props']['columns'] = ['three'];

        $footer = self::section('footer');
        $footer['props']['columns'] = ['two'];

        $validator = $this->validator();

        $this->assertFalse($validator->validateDocument(self::document([$stats, $footer])));
        $this->assertContains('document_tree.0: expected element instance count must be an integer', $validator->errors());
        $this->assertContains('document_tree.1: footer columns must be an integer', $validator->errors());
    }

    public static function sectionProvider(): array
    {
        $cases = [];
        foreach (SectionSchemas::TYPES as $type) {
            $cases[$type] = [$type, self::section($type)];
        }

        return $cases;
    }

    public static function nodeProvider(): array
    {
        $cases = [];
        foreach (NodeSchemas::TYPES as $type) {
            $cases[$type] = [$type, self::node($type)];
        }

        return $cases;
    }

    public static function elementProvider(): array
    {
        $cases = [];
        foreach (ElementSchemas::TYPES as $type) {
            $cases[$type] = [$type, self::element($type)];
        }

        return $cases;
    }

    private function validator(): SchemaValidator
    {
        return new SchemaValidator;
    }

    private static function document(array $sections): array
    {
        return [
            'schema_version' => 1,
            'page_metadata' => [
                'title' => 'Acme',
                'page_type' => 'landing',
                'goal' => 'Convince visitors to try Acme.',
                'audience' => 'Developers',
                'prompt_summary' => 'A landing page for Acme.',
                'status' => 'valid',
                'created_at' => self::date(),
                'updated_at' => self::date(),
            ],
            'design_system' => [
                'colors' => [
                    'primary' => 'blue',
                    'accent' => 'emerald',
                    'neutral' => 'neutral',
                    'background' => 'white',
                    'foreground' => 'neutral-950',
                ],
                'typography' => [
                    'heading_family' => 'sans',
                    'body_family' => 'sans',
                    'scale' => 'comfortable',
                ],
                'spacing' => [
                    'density' => 'comfortable',
                    'section_padding' => 'lg',
                ],
                'radius' => 'lg',
                'tone' => 'technical',
                'dark_mode' => false,
            ],
            'document_tree' => $sections,
            'generation_history' => [],
        ];
    }

    private static function section(string $type): array
    {
        return [
            'id' => self::id('sec_'),
            'type' => $type,
            'props' => self::sectionProps($type),
            'children' => self::sectionChildren($type),
            'locks' => self::locks(),
            'metadata' => self::metadata(),
        ];
    }

    private static function sectionProps(string $type): array
    {
        $props = [
            'background' => 'default',
            'padding' => 'lg',
            'max_width' => 'default',
            'alignment' => 'left',
        ];

        return $props + match ($type) {
            'header' => ['variant' => 'with_cta', 'sticky' => false],
            'hero' => ['variant' => 'centered', 'image_url' => null],
            'feature_grid', 'stats_band' => ['columns' => 3],
            'feature_split' => ['image_side' => 'right', 'image_url' => 'placeholder:feature'],
            'testimonial_grid' => ['columns' => 2],
            'faq' => ['layout' => 'single_column'],
            'cta_band' => ['variant' => 'centered'],
            'contact_form' => ['submit_endpoint' => null],
            'footer' => ['variant' => 'columned', 'columns' => 2],
            default => [],
        };
    }

    private static function sectionChildren(string $type): array
    {
        return match ($type) {
            'header' => [self::node('image'), self::node('element_instance'), self::node('element_instance')],
            'hero' => [self::node('badge'), self::node('heading', ['level' => 1]), self::node('text'), self::node('element_instance')],
            'logo_cloud' => [self::node('heading', ['level' => 2]), self::node('image'), self::node('image'), self::node('image'), self::node('image')],
            'feature_grid' => [self::node('heading', ['level' => 2]), self::node('text'), self::node('element_instance'), self::node('element_instance'), self::node('element_instance')],
            'feature_split' => [self::node('heading', ['level' => 2]), self::node('text'), self::node('list'), self::node('element_instance')],
            'stats_band' => [self::node('heading', ['level' => 2]), self::node('element_instance'), self::node('element_instance'), self::node('element_instance')],
            'testimonial_grid' => [self::node('heading', ['level' => 2]), self::node('element_instance'), self::node('element_instance')],
            'faq' => [self::node('heading', ['level' => 2]), self::node('heading', ['level' => 3]), self::node('text'), self::node('heading', ['level' => 3]), self::node('text'), self::node('heading', ['level' => 3]), self::node('text')],
            'cta_band' => [self::node('heading', ['level' => 2]), self::node('text'), self::node('element_instance')],
            'contact_form' => [self::node('heading', ['level' => 2]), self::node('text'), self::node('form_group'), self::node('form_group'), self::node('element_instance')],
            'footer' => [self::node('image'), self::node('text'), self::node('element_instance'), self::node('element_instance'), self::node('text')],
        };
    }

    private static function node(string $type, array $propOverrides = []): array
    {
        $node = [
            'id' => $type === 'element_instance' ? self::id('inst_') : self::id('node_'),
            'type' => $type,
            'props' => self::nodeProps($type, $propOverrides),
            'locks' => self::locks(),
            'metadata' => self::metadata($type === 'element_instance' ? 'library_instance' : 'generator'),
        ];

        if (in_array($type, NodeSchemas::CONTAINER_TYPES, true)) {
            $node['children'] = match ($type) {
                'form_group' => [self::node('text'), self::node('input')],
                'list' => [self::node('list_item')],
                'list_item' => [self::node('text')],
                default => [],
            };
        }

        return $node;
    }

    private static function nodeProps(string $type, array $overrides = []): array
    {
        $props = match ($type) {
            'container' => ['layout' => 'block', 'gap' => 'md', 'alignment' => 'start', 'justification' => 'start', 'background' => 'none', 'padding' => 'md', 'radius' => 'md'],
            'stack' => ['gap' => 'md', 'alignment' => 'left'],
            'grid' => ['columns' => 3, 'gap' => 'md'],
            'heading' => ['level' => 2, 'text' => 'Build faster', 'alignment' => 'left', 'emphasis' => 'default'],
            'text' => ['text' => 'A focused paragraph.', 'size' => 'base', 'alignment' => 'left', 'emphasis' => 'default'],
            'image' => ['src' => 'placeholder:logo', 'alt' => 'Logo', 'width' => null, 'height' => null, 'fit' => 'contain', 'radius' => 'none'],
            'button' => ['label' => 'Start', 'href' => '#', 'variant' => 'primary', 'size' => 'md'],
            'badge' => ['label' => 'New', 'tone' => 'info'],
            'link' => ['label' => 'Docs', 'href' => '#', 'emphasis' => 'default'],
            'input' => ['name' => 'email', 'input_type' => 'email', 'placeholder' => 'you@example.com', 'required' => true],
            'textarea' => ['name' => 'message', 'placeholder' => 'Message', 'rows' => 4, 'required' => true],
            'form_group' => ['layout' => 'stacked'],
            'card' => ['variant' => 'outlined', 'padding' => 'md'],
            'icon' => ['name' => 'sparkles', 'size' => 'md', 'tone' => 'accent'],
            'list' => ['style' => 'checked'],
            'list_item' => [],
            'divider' => ['weight' => 'thin', 'spacing' => 'md'],
            'element_instance' => ['library_id' => self::id('elem_'), 'overrides' => []],
        };

        return $props + $overrides;
    }

    private static function element(string $type): array
    {
        return [
            'id' => self::id('elem_'),
            'project_id' => self::id('proj_'),
            'name' => str_replace('_', ' ', $type),
            'type' => $type,
            'default_props' => self::elementProps($type),
            'preview_html_cache' => null,
            'created_at' => self::date(),
            'updated_at' => self::date(),
        ];
    }

    private static function elementProps(string $type): array
    {
        return match ($type) {
            'primary_button', 'secondary_button' => ['label' => 'Start', 'href' => '#', 'size' => 'md', 'icon' => null, 'icon_position' => 'leading'],
            'pill_badge' => ['label' => 'Beta', 'tone' => 'accent', 'leading_icon' => null],
            'feature_card' => ['icon' => null, 'heading' => 'Fast setup', 'body' => 'Ship a page quickly.', 'link' => null],
            'testimonial_card' => ['quote' => 'It worked well.', 'author_name' => 'Pat', 'author_title' => null, 'author_avatar_url' => null, 'rating' => 5],
            'stat_card' => ['value' => '3x', 'label' => 'Faster', 'trend' => 'up', 'trend_label' => '+12%'],
            'nav_link_group' => ['links' => [['label' => 'Home', 'href' => '#', 'active' => true]], 'layout' => 'horizontal'],
            'cta_group' => ['primary' => ['label' => 'Start', 'href' => '#'], 'secondary' => null, 'alignment' => 'left'],
        };
    }

    private static function locks(): array
    {
        return ['content_locked' => false, 'style_locked' => false, 'layout_locked' => false];
    }

    private static function metadata(string $createdBy = 'generator'): array
    {
        return ['created_by' => $createdBy, 'created_at' => self::date(), 'updated_at' => self::date()];
    }

    private static function id(string $prefix): string
    {
        return $prefix.'01h00000000000000000000000';
    }

    private static function date(): string
    {
        return '2026-05-20T18:00:00Z';
    }
}
